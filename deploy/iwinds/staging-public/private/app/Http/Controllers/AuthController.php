<?php

namespace App\Http\Controllers;

use App\Auth\AuthTransactions;
use App\Auth\JavaBcrypt;
use App\Auth\JwtSessions;
use App\Models\Usuario;
use App\Support\IwindsPublishedImages;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller
{
    public function __construct(private JwtSessions $sessions, private JavaBcrypt $passwords) {}

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:4096'],
        ], $this->messages());

        $this->sessions->pruneExpired();

        return AuthTransactions::run(function () use ($data): JsonResponse {
            $email = mb_strtolower(trim($data['email']));
            $usuario = Usuario::whereRaw('LOWER(email) = ?', [$email])->lockForUpdate()->first();
            $matches = $this->passwords->verify($data['password'], $usuario?->password);
            if (! $usuario || ! $matches) {
                return response()->json(['mensaje' => 'Email o contraseña incorrectos'], 401);
            }
            if (! $usuario->activo) {
                return response()->json(['mensaje' => 'Usuario desactivado'], 403);
            }
            if ($usuario->currentRoles() === []) {
                return response()->json(['mensaje' => 'Email o contraseña incorrectos'], 401);
            }

            return $this->loginResponse($usuario);
        });
    }

    public function logout(Request $request): Response
    {
        $this->sessions->revoke($this->sessions->token($request));

        return $this->withSessionCookies(response()->noContent());
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json($request->user()->profile());
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
        ], $this->messages());

        try {
            return AuthTransactions::run(function () use ($request, $data): JsonResponse {
                $usuario = $this->sessions->authenticate($this->sessions->token($request), lockUser: true);
                if (! $usuario) {
                    return response()->json(['mensaje' => 'Sesión inválida o vencida'], 401);
                }
                $email = mb_strtolower(trim($data['email']));
                if (Usuario::whereRaw('LOWER(email) = ?', [$email])->whereKeyNot($usuario->id)->exists()) {
                    return response()->json(['detail' => 'Ya existe un usuario con ese email'], 409);
                }
                $usuario->nombre = $data['nombre'];
                $usuario->email = $email;
                $usuario->save();
                $usuario->refresh();
                $this->sessions->revoke($this->sessions->token($request));

                return $this->loginResponse($usuario);
            });
        } catch (UniqueConstraintViolationException $exception) {
            return response()->json(['detail' => 'Ya existe un usuario con ese email'], 409);
        }
    }

    public function changePassword(Request $request): Response
    {
        $data = $request->validate([
            'passwordActual' => ['required', 'string', 'max:4096'],
            'passwordNueva' => ['required', 'string', 'min:12', 'max:72', function (string $attribute, mixed $value, Closure $fail): void {
                if (is_string($value) && (strlen($value) > 72 || str_contains($value, "\0"))) {
                    $fail('La contraseña debe ocupar hasta 72 bytes y no contener caracteres nulos');
                }
            }],
        ], $this->messages());

        return AuthTransactions::run(function () use ($request, $data): Response {
            $usuario = $this->sessions->authenticate($this->sessions->token($request), lockUser: true);
            if (! $usuario) {
                return response()->json(['mensaje' => 'Sesión inválida o vencida'], 401);
            }
            if (! $this->passwords->verify($data['passwordActual'], $usuario->password)) {
                return response()->json(['detail' => 'La contraseña actual no es correcta'], 400);
            }
            $usuario->password = $this->passwords->hash($data['passwordNueva']);
            $usuario->save();

            return $this->withSessionCookies(response()->noContent());
        });
    }

    private function loginResponse(Usuario $usuario): JsonResponse
    {
        $token = $this->sessions->issue($usuario);

        return $this->withSessionCookies(
            response()->json(['token' => app()->environment('staging') ? '' : $token, ...$usuario->profile()]),
            $token,
        );
    }

    private function withSessionCookies(Response $response, string $token = ''): Response
    {
        $cookie = $this->sessions->cookie($token);
        $response->headers->setCookie($cookie);
        if (defined('ENSENADA_IWINDS_STAGING_MODE') && ENSENADA_IWINDS_STAGING_MODE === 'web-admin') {
            $response->headers->setCookie(Cookie::create(IwindsPublishedImages::PREVIEW_COOKIE)
                ->withValue(IwindsPublishedImages::encodePreview($token))
                ->withPath('/uploads')->withExpires($cookie->getExpiresTime())
                ->withSecure(true)->withHttpOnly(true)->withSameSite(Cookie::SAMESITE_STRICT));
        }

        return $response;
    }

    /** @return array<string,string> */
    private function messages(): array
    {
        return [
            'email.required' => 'El email es obligatorio',
            'email.email' => 'El email no es válido',
            'nombre.required' => 'El nombre es obligatorio',
            'password.required' => 'La contraseña es obligatoria',
            'passwordActual.required' => 'La contraseña actual es obligatoria',
            'passwordNueva.required' => 'La nueva contraseña es obligatoria',
            'passwordNueva.min' => 'La nueva contraseña debe tener al menos 12 caracteres',
            'passwordNueva.max' => 'La nueva contraseña debe tener hasta 72 caracteres',
            '*.string' => 'El valor debe ser texto',
        ];
    }
}
