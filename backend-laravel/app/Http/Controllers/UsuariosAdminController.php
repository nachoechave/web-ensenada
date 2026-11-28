<?php

namespace App\Http\Controllers;

use App\Auth\AuthTransactions;
use App\Auth\JavaBcrypt;
use App\Auth\JwtSessions;
use App\Auth\Role;
use App\Models\Usuario;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class UsuariosAdminController extends Controller
{
    private const OPERATIONAL_ROLE_ERROR = 'Asigná exactamente un rol: PRENSA o HACIENDA';

    public function __construct(private JwtSessions $sessions, private JavaBcrypt $passwords) {}

    public function index(): JsonResponse
    {
        return response()->json(Usuario::query()->select('id', 'nombre', 'email', 'rol', 'activo')
            ->with('assignedRoles')->orderBy('id')->get()->map($this->view(...)));
    }

    public function store(Request $request): JsonResponse
    {
        $body = $request->json()->all();
        if (is_string($body['email'] ?? null)) {
            $body['email'] = mb_strtolower(trim($body['email']));
        }
        $data = Validator::make($body, [
            'nombre' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => $this->passwordRules(),
            'roles' => $this->roleRules(),
            'activo' => ['sometimes', 'boolean:strict'],
        ], [
            'nombre.required' => 'El nombre es obligatorio',
            'email.required' => 'El email es obligatorio',
            'email.email' => 'El email no es válido',
            'password.required' => 'La contraseña es obligatoria',
            'roles.required' => 'Debe asignarse al menos un rol',
        ])->validate();
        $role = $this->operationalRole($data['roles']);

        try {
            return $this->write($request, function () use ($data, $role): JsonResponse {
                abort_if(Usuario::whereRaw('LOWER(email) = ?', [$data['email']])->exists(), 409, 'Ya existe un usuario con ese email');
                $user = new Usuario;
                $user->nombre = $data['nombre'];
                $user->email = $data['email'];
                $user->password = $this->passwords->hash($data['password']);
                $user->rol = $role->value;
                $user->activo = $data['activo'] ?? false;
                $user->save();
                $user->assignedRoles()->create(['rol' => $role->value]);

                return response()->json($this->view($user), 201);
            });
        } catch (UniqueConstraintViolationException $exception) {
            abort(409, 'Ya existe un usuario con ese email');
        }
    }

    public function roles(Request $request, string $id): JsonResponse
    {
        $data = Validator::make($request->json()->all(), ['roles' => $this->roleRules()], [
            'roles.required' => 'Debe asignarse al menos un rol',
        ])->validate();

        return $this->write($request, function () use ($id, $data): JsonResponse {
            $user = $this->operationalTarget($id);
            $role = $this->operationalRole($data['roles']);
            $user->rol = $role->value;
            $user->save();
            $user->assignedRoles()->delete();
            $user->assignedRoles()->create(['rol' => $role->value]);
            $user->unsetRelation('assignedRoles');

            return response()->json($this->view($user));
        });
    }

    public function active(Request $request, string $id): JsonResponse
    {
        $data = Validator::make($request->json()->all(), ['activo' => ['required', 'boolean:strict']])->validate();

        return $this->write($request, function () use ($id, $data): JsonResponse {
            $user = $this->operationalTarget($id);
            $user->activo = $data['activo'];
            $user->save();

            return response()->json($this->view($user));
        });
    }

    public function password(Request $request, string $id): Response
    {
        $data = Validator::make($request->json()->all(), ['password' => $this->passwordRules()])->validate();

        return $this->write($request, function () use ($id, $data): Response {
            $user = $this->operationalTarget($id);
            $user->password = $this->passwords->hash($data['password']);
            $user->save();

            return response()->noContent();
        });
    }

    /** @template T of Response
     * @param  Closure(): T  $action
     * @return T
     */
    private function write(Request $request, Closure $action): Response
    {
        return AuthTransactions::run(function () use ($request, $action): Response {
            $actor = $this->sessions->authenticate($this->sessions->token($request), lockUser: true);
            abort_if($actor === null, 401, 'Sesión inválida o vencida');
            abort_unless($actor->hasAnyRole([Role::SuperAdmin->value]), 403, 'Tu cuenta no tiene permiso para esta acción');

            return $action();
        });
    }

    private function operationalTarget(string $id): Usuario
    {
        abort_unless(preg_match('/^[+-]?[0-9]+$/D', $id) === 1, 400, 'Identificador de usuario inválido');
        $number = filter_var(($id[0] === '-' ? '-' : '').(ltrim($id, '+-0') ?: '0'), FILTER_VALIDATE_INT);
        abort_if($number === false, 400, 'Identificador de usuario inválido');
        $user = $number > 0 ? Usuario::query()->lockForUpdate()->find($number) : null;
        abort_if($user === null, 404, 'Usuario no encontrado');
        $user->roleValues(lockForUpdate: true);
        abort_if($this->isProtected($user), 403, 'Los superadministradores no se modifican desde este panel');

        return $user;
    }

    private function isProtected(Usuario $user): bool
    {
        foreach ([$user->rol, ...$user->roleValues()] as $role) {
            if (in_array(strtoupper(trim($role)), ['SUPER_ADMIN', 'ROLE_SUPER_ADMIN', 'ADMIN', 'ROLE_ADMIN'], true)) {
                return true;
            }
        }

        return false;
    }

    private function roleRules(): array
    {
        return ['required', 'array', 'list'];
    }

    private function operationalRole(array $roles): Role
    {
        $first = $roles[0];
        $role = is_string($first) ? Role::tryFrom($first) : null;
        abort_unless(in_array($role, [Role::Prensa, Role::Hacienda], true), 400, self::OPERATIONAL_ROLE_ERROR);
        foreach ($roles as $value) {
            abort_if($value !== $first, 400, self::OPERATIONAL_ROLE_ERROR);
        }

        return $role;
    }

    private function passwordRules(): array
    {
        return ['bail', 'required', 'string', 'min:12', 'max:72', static function (string $attribute, string $value, Closure $fail): void {
            if (strlen($value) > 72 || str_contains($value, "\0")) {
                $fail('La contraseña debe ocupar hasta 72 bytes y no contener caracteres nulos');
            }
        }];
    }

    private function view(Usuario $user): array
    {
        $user->loadMissing('assignedRoles');
        $roles = $user->currentRoles();
        if ($this->isProtected($user) && ! in_array(Role::SuperAdmin->value, $roles, true)) {
            // Mark protected legacy accounts for Angular; this never grants permissions.
            $roles[] = Role::SuperAdmin->value;
        }
        sort($roles);

        return ['id' => $user->id, 'nombre' => $user->nombre, 'email' => $user->email, 'roles' => $roles, 'activo' => $user->activo];
    }
}
