<?php

namespace Tests\Feature;

use App\Auth\JavaBcrypt;
use App\Auth\JwtSessions;
use App\Auth\Role;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class UsuariosAdminTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const ROOT = '/api/admin/usuarios';

    private const PASSWORD = 'Synthetic-password-1234';

    private const NEW_PASSWORD = 'Synthetic-reset-password-5678';

    private function api(string $method, string $path, array $data = [], ?string $token = null, array $headers = []): TestResponse
    {
        $this->unencryptedCookies = $token === null ? [] : [JwtSessions::COOKIE => $token];

        return $this->withCredentials()->json($method, 'https://localhost:18443'.$path, $data, [
            'Origin' => 'https://localhost:18443', 'Sec-Fetch-Site' => 'same-origin', ...$headers,
        ]);
    }

    private function login(Usuario $user, string $password = self::PASSWORD): string
    {
        return $this->api('POST', '/api/auth/login', ['email' => $user->email, 'password' => $password])->assertOk()->json('token');
    }

    private function admin(): Usuario
    {
        return Usuario::factory()->withRole(Role::SuperAdmin)->create();
    }

    private function createData(array $values = []): array
    {
        return ['nombre' => 'Cuenta sintética', 'email' => 'operativo@example.test', 'password' => self::PASSWORD,
            'roles' => ['PRENSA'], 'activo' => true, ...$values];
    }

    public static function deniedActors(): array
    {
        return ['no session' => [null, 401], 'PRENSA' => [Role::Prensa, 403], 'HACIENDA' => [Role::Hacienda, 403]];
    }

    #[DataProvider('deniedActors')]
    public function test_denied_actors_cannot_use_any_administrative_operation(?Role $role, int $status): void
    {
        $target = Usuario::factory()->create();
        $token = $role === null ? null : $this->login(Usuario::factory()->withRole($role)->create());
        $before = $target->fresh()->getAttributes();
        foreach ([['GET', '', []], ['POST', '', $this->createData()], ['PUT', "/$target->id/roles", ['roles' => ['HACIENDA']]],
            ['PUT', "/$target->id/activo", ['activo' => false]], ['PUT', "/$target->id/password", ['password' => self::NEW_PASSWORD]]] as [$method, $suffix, $payload]) {
            $this->api($method, self::ROOT.$suffix, [...$payload, 'actorRole' => 'SUPER_ADMIN'], $token, ['X-Role' => 'SUPER_ADMIN'])->assertStatus($status);
        }
        $this->assertTrue($before === $target->fresh()->getAttributes(), 'Una petición rechazada no puede alterar la cuenta destino.');
        $this->assertDatabaseMissing('usuarios', ['email' => 'operativo@example.test']);
        $this->assertSame(['PRENSA'], $target->currentRoles());
    }

    public function test_list_and_create_return_only_the_angular_contract_for_both_roles(): void
    {
        $admin = $this->admin();
        $token = $this->login($admin);
        $expected = [['id' => $admin->id, 'nombre' => $admin->nombre, 'email' => $admin->email, 'roles' => ['SUPER_ADMIN'], 'activo' => true]];
        foreach (['PRENSA', 'HACIENDA'] as $role) {
            $email = strtolower($role).'@example.test';
            $response = $this->api('POST', self::ROOT, $this->createData(['email' => ' '.strtoupper($email).' ', 'roles' => [$role, $role],
                'rol' => 'SUPER_ADMIN', 'token_version' => 999]), $token)->assertCreated();
            $record = ['id' => $response->json('id'), 'nombre' => 'Cuenta sintética', 'email' => $email, 'roles' => [$role], 'activo' => true];
            $response->assertExactJson($record);
            $this->assertIsInt($record['id']);
            $expected[] = $record;
            $this->assertDatabaseHas('usuarios', ['id' => $record['id'], 'email' => $email, 'rol' => $role, 'token_version' => 0]);
            $this->assertTrue(app(JavaBcrypt::class)->verify(self::PASSWORD, Usuario::findOrFail($record['id'])->password));
        }
        $this->api('GET', self::ROOT, token: $token)->assertOk()->assertExactJson($expected);
    }

    public static function invalidPayloads(): array
    {
        return [
            'bad email' => [['email' => 'not-an-email']], 'blank name' => [['nombre' => '  ']],
            'name too long' => [['nombre' => str_repeat('a', 256)]], 'short password' => [['password' => 'short']],
            'long password' => [['password' => str_repeat('a', 73)]], 'BCrypt byte limit' => [['password' => str_repeat('é', 37)]],
            'NUL' => [['password' => "synthetic\0password"]], 'array password' => [['password' => ['wrong']]],
            'invalid active' => [['activo' => 'false']], 'empty roles' => [['roles' => []]],
            'two roles' => [['roles' => ['PRENSA', 'HACIENDA']]], 'admin role' => [['roles' => ['SUPER_ADMIN']]],
            'legacy alias' => [['roles' => ['ROLE_ADMIN']]], 'non-list' => [['roles' => ['primary' => 'PRENSA']]],
        ];
    }

    #[DataProvider('invalidPayloads')]
    public function test_invalid_creation_is_a_400_and_creates_no_user(array $overrides): void
    {
        $this->api('POST', self::ROOT, $this->createData($overrides), $this->login($this->admin()))->assertBadRequest();
        $this->assertDatabaseCount('usuarios', 1);
        $this->assertDatabaseCount('usuario_roles', 1);
    }

    public function test_duplicates_conflict_and_missing_active_defaults_to_inactive(): void
    {
        Usuario::factory()->create(['email' => 'OPERATIVO@EXAMPLE.TEST']);
        $token = $this->login($this->admin());
        $this->api('POST', self::ROOT, $this->createData(['email' => '  Operativo@example.test  ']), $token)->assertConflict();
        $data = $this->createData(['email' => 'inactive@example.test']);
        unset($data['activo']);
        $this->api('POST', self::ROOT, $data, $token)->assertCreated()->assertJsonPath('activo', false);
        $this->assertDatabaseCount('usuarios', 3);
    }

    public function test_roles_change_in_both_tables_and_existing_sessions_use_live_permissions(): void
    {
        $token = $this->login($this->admin());
        $user = Usuario::factory()->create();
        $old = $this->login($user);
        $path = self::ROOT."/$user->id/roles";
        $this->api('PUT', $path, ['roles' => ['SUPER_ADMIN']], $token)->assertBadRequest();
        $this->api('PUT', $path, ['roles' => ['HACIENDA'], 'activo' => false], $token)->assertOk()->assertExactJson([
            'id' => $user->id, 'nombre' => $user->nombre, 'email' => $user->email, 'roles' => ['HACIENDA'], 'activo' => true,
        ]);
        $this->assertDatabaseHas('usuarios', ['id' => $user->id, 'rol' => 'HACIENDA']);
        $this->assertDatabaseMissing('usuario_roles', ['usuario_id' => $user->id, 'rol' => 'PRENSA']);
        $this->api('GET', '/api/auth/me', token: $old)->assertOk()->assertJsonPath('roles', ['HACIENDA']);
    }

    public function test_authorization_ignores_a_previous_super_admin_token_after_role_removal(): void
    {
        $admin = $this->admin();
        $token = $this->login($admin);
        $admin->assignedRoles()->where('rol', 'SUPER_ADMIN')->update(['rol' => 'PRENSA']);
        $this->api('GET', self::ROOT, token: $token)->assertForbidden();
        $this->api('POST', self::ROOT, $this->createData(), $token)->assertForbidden();
    }

    public function test_disabling_and_reenabling_a_user_cannot_restore_two_old_sessions(): void
    {
        $user = Usuario::factory()->create();
        $old = [$this->login($user), $this->login($user)];
        $admin = $this->login($this->admin());
        $path = self::ROOT."/$user->id/activo";
        $this->api('PUT', $path, ['activo' => false], $admin)->assertOk()->assertJsonPath('activo', false);
        $this->api('POST', '/api/auth/login', ['email' => $user->email, 'password' => self::PASSWORD])->assertForbidden();
        $this->assertDatabaseMissing('auth_sessions', ['usuario_id' => $user->id]);
        $this->api('PUT', $path, ['activo' => true], $admin)->assertOk()->assertJsonPath('activo', true);
        foreach ($old as $token) {
            $this->api('GET', '/api/auth/me', token: $token)->assertUnauthorized();
        }
        $this->assertSame(1, $user->fresh()->token_version);
        $this->assertNotContains($this->login($user), $old);
    }

    public function test_password_reset_is_empty_and_revokes_all_sessions_without_affecting_the_admin(): void
    {
        $user = Usuario::factory()->create();
        $old = [$this->login($user), $this->login($user)];
        $admin = $this->login($this->admin());
        $path = self::ROOT."/$user->id/password";
        $this->api('PUT', $path, ['password' => str_repeat('é', 37)], $admin)->assertBadRequest();
        $this->api('GET', '/api/auth/me', token: $old[0])->assertOk();
        $this->api('PUT', $path, ['password' => self::NEW_PASSWORD], $admin)->assertNoContent()->assertCookieMissing(JwtSessions::COOKIE);
        $this->assertDatabaseMissing('auth_sessions', ['usuario_id' => $user->id]);
        $this->assertSame(1, $user->fresh()->token_version);
        foreach ($old as $token) {
            $this->api('GET', '/api/auth/me', token: $token)->assertUnauthorized();
        }
        $this->api('POST', '/api/auth/login', ['email' => $user->email, 'password' => self::PASSWORD])->assertUnauthorized();
        $this->login($user, self::NEW_PASSWORD);
        $this->api('GET', self::ROOT, token: $admin)->assertOk();
    }

    public function test_super_admin_targets_and_legacy_markers_are_protected_from_every_mutation(): void
    {
        $actor = $this->admin();
        $token = $this->login($actor);
        $canonical = $this->admin();
        $canonical->forceFill(['rol' => 'PRENSA'])->save();
        $legacy = Usuario::factory()->create(['rol' => 'ROLE_ADMIN']);
        $legacy->assignedRoles()->delete();
        $inconsistent = Usuario::factory()->create();
        $inconsistent->forceFill(['rol' => 'SUPER_ADMIN'])->save();
        foreach ([$actor, $canonical, $legacy, $inconsistent] as $target) {
            $before = $target->fresh()->getAttributes();
            $roles = $target->roleValues();
            foreach (['roles' => ['roles' => ['HACIENDA']], 'activo' => ['activo' => false], 'password' => ['password' => self::NEW_PASSWORD]] as $operation => $payload) {
                $this->api('PUT', self::ROOT."/$target->id/$operation", $payload, $token)->assertForbidden();
            }
            $this->assertTrue($before === $target->fresh()->getAttributes(), 'La cuenta protegida debe conservar todos sus atributos.');
            $this->assertSame($roles, $target->fresh()->roleValues());
        }
        $rows = $this->api('GET', self::ROOT, token: $token)->assertOk()->json();
        foreach ($rows as $row) {
            $this->assertContains('SUPER_ADMIN', $row['roles']);
        }
        $this->api('POST', '/api/auth/login', ['email' => $legacy->email, 'password' => self::PASSWORD])->assertUnauthorized();
    }

    public function test_invalid_ids_payloads_origins_and_undeclared_operations_are_rejected(): void
    {
        $token = $this->login($this->admin());
        $user = Usuario::factory()->create();
        $path = self::ROOT."/$user->id";
        $this->api('PUT', self::ROOT.'/9223372036854775808/activo', ['activo' => false], $token)->assertBadRequest();
        $this->api('PUT', self::ROOT.'/abc/activo', ['activo' => false], $token)->assertBadRequest();
        $this->api('PUT', self::ROOT.'/99999/activo', ['activo' => false], $token)->assertNotFound();
        $this->api('PUT', "$path/activo?activo=0", [], $token)->assertBadRequest();
        $this->api('PUT', "$path/activo", ['activo' => 0], $token)->assertBadRequest();
        $this->api('PUT', "$path/activo", ['activo' => false], $token, ['Content-Type' => 'text/plain'])->assertUnsupportedMediaType();
        $this->api('PUT', "$path/activo", ['activo' => false], $token, ['Sec-Fetch-Site' => 'same-site'])->assertForbidden();
        $this->api('POST', self::ROOT, $this->createData(), $token, ['Sec-Fetch-Site' => ''])->assertForbidden();
        $this->api('DELETE', $path, token: $token)->assertNotFound();
        $this->api('PATCH', "$path/roles", ['roles' => ['HACIENDA']], $token)->assertMethodNotAllowed();
        $this->api('GET', '/api/admin/otros', token: $token)->assertNotFound();
        $this->assertTrue($user->fresh()->activo);
        $this->assertSame(['PRENSA'], $user->currentRoles());
        $this->assertSame(0, DB::table('usuarios')->where('email', 'operativo@example.test')->count());
    }
}
