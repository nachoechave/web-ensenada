<?php

namespace Tests\Feature;

use App\Auth\JavaBcrypt;
use App\Auth\JwtSessions;
use App\Auth\Role;
use App\Http\Middleware\AuthenticateSession;
use App\Http\Middleware\RequireRole;
use App\Models\Usuario;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const PASSWORD = 'Synthetic-password-1234';

    private function api(string $method, string $path, array $data = [], ?string $token = null, array $headers = []): TestResponse
    {
        $this->unencryptedCookies = $token === null ? [] : [JwtSessions::COOKIE => $token];
        $this->withCredentials();

        return $this->json($method, 'https://localhost:18443'.$path, $data, [
            'Sec-Fetch-Site' => 'same-origin',
            'Origin' => 'https://localhost:18443',
            ...$headers,
        ]);
    }

    private function login(Usuario $usuario): string
    {
        return $this->api('POST', '/api/auth/login', [
            'email' => $usuario->email, 'password' => self::PASSWORD,
        ])->assertOk()->json('token');
    }

    public function test_login_sets_the_exact_json_contract_and_secure_cookie(): void
    {
        $usuario = Usuario::factory()->create(['email' => 'prensa@example.test']);

        $response = $this->api('POST', '/api/auth/login', [
            'email' => 'PRENSA@example.test', 'password' => self::PASSWORD,
            'roles' => ['SUPER_ADMIN'], 'activo' => true,
        ]);

        $response->assertOk()->assertJsonStructure(['token', 'nombre', 'email', 'rol', 'roles'])
            ->assertJsonPath('roles', ['PRENSA'])->assertJsonMissingPath('password')
            ->assertJsonMissingPath('token_version')->assertHeader('Cache-Control', 'no-store, private');
        $this->assertCount(5, $response->json());
        $cookie = $response->getCookie(JwtSessions::COOKIE, false);
        $this->assertTrue($cookie->isSecure());
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertSame('strict', $cookie->getSameSite());
        $this->assertSame('/api', $cookie->getPath());
        $this->assertNull($cookie->getDomain());
        $this->assertSame($response->json('token'), $cookie->getValue());
        $this->assertDatabaseHas('auth_sessions', ['usuario_id' => $usuario->id, 'token_hash' => hash('sha256', $cookie->getValue())]);
        $this->assertDatabaseMissing('auth_sessions', ['token_hash' => $cookie->getValue()]);
    }

    public function test_login_returns_401_for_wrong_password_without_a_session(): void
    {
        $usuario = Usuario::factory()->create();

        $this->api('POST', '/api/auth/login', ['email' => $usuario->email, 'password' => 'Incorrect-password'])
            ->assertUnauthorized()->assertExactJson(['mensaje' => 'Email o contraseña incorrectos']);
        $this->assertDatabaseCount('auth_sessions', 0);
    }

    public function test_login_returns_401_for_an_unknown_email(): void
    {
        $this->api('POST', '/api/auth/login', ['email' => 'unknown@example.test', 'password' => self::PASSWORD])
            ->assertUnauthorized()->assertExactJson(['mensaje' => 'Email o contraseña incorrectos']);
        $this->assertDatabaseCount('auth_sessions', 0);
    }

    public function test_login_returns_403_for_an_inactive_user(): void
    {
        $usuario = Usuario::factory()->inactive()->create();

        $this->api('POST', '/api/auth/login', ['email' => $usuario->email, 'password' => self::PASSWORD])
            ->assertForbidden()->assertExactJson(['mensaje' => 'Usuario desactivado']);
        $this->assertDatabaseCount('auth_sessions', 0);
    }

    public static function invalidLogins(): array
    {
        return [
            'missing' => [[], 'email', 'El email es obligatorio'],
            'invalid email' => [['email' => 'invalid', 'password' => self::PASSWORD], 'email', 'El email no es válido'],
            'empty password' => [['email' => 'a@example.test', 'password' => ''], 'password', 'La contraseña es obligatoria'],
            'password array' => [['email' => 'a@example.test', 'password' => []], 'password', 'La contraseña es obligatoria'],
        ];
    }

    #[DataProvider('invalidLogins')]
    public function test_login_returns_400_for_invalid_data(array $data, string $field, string $message): void
    {
        $this->api('POST', '/api/auth/login', $data)->assertBadRequest()->assertJsonPath("errors.$field.0", $message);
        $this->assertDatabaseCount('auth_sessions', 0);
    }

    public function test_me_uses_only_the_cookie_and_exposes_no_credentials(): void
    {
        $usuario = Usuario::factory()->create();
        $token = $this->login($usuario);

        $this->api('GET', '/api/auth/me', token: $token)->assertOk()->assertExactJson([
            'nombre' => $usuario->nombre, 'email' => $usuario->email, 'rol' => 'PRENSA', 'roles' => ['PRENSA'],
        ]);
    }

    public function test_me_returns_401_without_a_session(): void
    {
        $this->api('GET', '/api/auth/me')->assertUnauthorized();
    }

    public function test_non_json_login_returns_415_and_creates_no_session(): void
    {
        Usuario::factory()->create(['email' => 'prensa@example.test']);

        $this->post('https://localhost:18443/api/auth/login', ['email' => 'prensa@example.test', 'password' => self::PASSWORD], [
            'Sec-Fetch-Site' => 'same-origin', 'Origin' => 'https://localhost:18443',
        ])->assertUnsupportedMediaType();
        $this->assertDatabaseCount('auth_sessions', 0);
    }

    public function test_http_is_rejected_even_with_same_origin_fetch_metadata(): void
    {
        $this->getJson('http://localhost:18443/api/auth/me')->assertForbidden();
    }

    public function test_a_direct_token_version_increment_revokes_the_session(): void
    {
        $usuario = Usuario::factory()->create();
        $token = $this->login($usuario);
        DB::table('usuarios')->where('id', $usuario->id)->increment('token_version');

        $this->api('GET', '/api/auth/me', token: $token)->assertUnauthorized();
    }

    public function test_unknown_legacy_roles_do_not_grant_super_admin_access(): void
    {
        $usuario = Usuario::factory()->create();
        DB::table('usuario_roles')->where('usuario_id', $usuario->id)->delete();
        DB::table('usuarios')->where('id', $usuario->id)->update(['rol' => 'ADMIN']);

        $this->api('POST', '/api/auth/login', ['email' => $usuario->email, 'password' => self::PASSWORD])->assertUnauthorized();
        $this->assertDatabaseCount('auth_sessions', 0);
    }

    public function test_bearer_compatibility_and_header_precedence_are_preserved(): void
    {
        $token = $this->login(Usuario::factory()->create());

        $this->api('GET', '/api/auth/me', headers: ['Authorization' => 'Bearer '.$token])->assertOk();
        $this->api('GET', '/api/auth/me', token: $token, headers: ['Authorization' => 'Bearer invalid'])->assertUnauthorized();
    }

    public function test_staging_keeps_the_json_shape_and_uses_only_the_http_only_cookie(): void
    {
        $usuario = Usuario::factory()->create();
        $this->app->instance('env', 'staging');

        $login = $this->api('POST', '/api/auth/login', ['email' => $usuario->email, 'password' => self::PASSWORD]);
        $login->assertOk()->assertExactJson(['token' => '', ...$usuario->profile()]);
        $cookie = $login->getCookie(JwtSessions::COOKIE, false);
        $this->assertTrue($cookie->isHttpOnly());
        $token = $cookie->getValue();
        $this->assertNotSame('', $token);
        $this->api('GET', '/api/auth/me', headers: ['Authorization' => 'Bearer '.$token])->assertUnauthorized();
        $this->api('GET', '/api/auth/me', token: $token, headers: ['Authorization' => 'Basic c3ludGhldGljOnRlc3Q='])->assertUnauthorized();
        $this->api('GET', '/api/auth/me', token: $token)->assertOk();
        $updated = $this->api('PUT', '/api/auth/me', ['email' => $usuario->email, 'nombre' => 'Perfil de prueba'], $token);
        $updated->assertOk()->assertJsonPath('token', '');
        $this->assertNotSame($token, $updated->getCookie(JwtSessions::COOKIE, false)->getValue());
    }

    public function test_login_and_logout_recover_from_a_corrupt_cookie(): void
    {
        $usuario = Usuario::factory()->create();

        $this->api('POST', '/api/auth/login', ['email' => $usuario->email, 'password' => self::PASSWORD], 'invalid')->assertOk();
        $this->api('POST', '/api/auth/logout', token: 'invalid')->assertNoContent();
    }

    public function test_logout_revokes_only_that_session_and_clears_the_cookie(): void
    {
        $usuario = Usuario::factory()->create();
        $first = $this->login($usuario);
        $second = $this->login($usuario);

        $response = $this->api('POST', '/api/auth/logout', token: $first)->assertNoContent();

        $this->assertLessThan(time(), $response->getCookie(JwtSessions::COOKIE, false)->getExpiresTime());
        $this->api('GET', '/api/auth/me', token: $first)->assertUnauthorized();
        $this->api('GET', '/api/auth/me', token: $second)->assertOk();
        $this->api('POST', '/api/auth/logout')->assertNoContent();
    }

    public function test_expired_database_session_returns_401(): void
    {
        $token = $this->login(Usuario::factory()->create());
        DB::table('auth_sessions')->update(['expires_at' => time() - 1]);

        $this->api('GET', '/api/auth/me', token: $token)->assertUnauthorized();
    }

    public static function invalidClaims(): array
    {
        return [
            'issuer' => [['iss' => 'another-service']],
            'expiry' => [['exp' => 1]],
            'version' => [['tokenVersion' => 99]],
            'missing immutable identity' => [['uid' => null]],
            'wrong immutable identity' => [['uid' => 99999]],
            'subject' => [['sub' => 'another@example.test']],
            'missing expiry' => [['exp' => null]],
            'future issue' => [['iat' => 4102444800]],
        ];
    }

    #[DataProvider('invalidClaims')]
    public function test_invalid_signed_claims_return_401(array $changes): void
    {
        $token = $this->login(Usuario::factory()->create());
        $claims = (array) JWT::decode($token, new Key(config('auth_migration.jwt_secret'), 'HS256'));
        $forged = JWT::encode(array_replace($claims, $changes), config('auth_migration.jwt_secret'), 'HS256');
        DB::table('auth_sessions')->where('token_hash', hash('sha256', $token))->update(['token_hash' => hash('sha256', $forged)]);

        $this->api('GET', '/api/auth/me', token: $forged)->assertUnauthorized();
    }

    public function test_a_tampered_signature_returns_401(): void
    {
        $token = $this->login(Usuario::factory()->create());
        $parts = explode('.', $token);
        $parts[2] = str_repeat('a', 43);

        $this->api('GET', '/api/auth/me', token: implode('.', $parts))->assertUnauthorized();
    }

    public function test_deactivation_and_reactivation_do_not_restore_old_sessions_even_with_direct_sql(): void
    {
        $usuario = Usuario::factory()->create();
        $token = $this->login($usuario);

        DB::table('usuarios')->where('id', $usuario->id)->update(['activo' => false]);
        DB::table('usuarios')->where('id', $usuario->id)->update(['activo' => true]);

        $this->api('GET', '/api/auth/me', token: $token)->assertUnauthorized();
        $this->assertDatabaseCount('auth_sessions', 0);
        $this->assertSame(1, $usuario->fresh()->token_version);
    }

    public function test_password_change_revokes_all_sessions_and_requires_login_again(): void
    {
        $usuario = Usuario::factory()->create();
        $first = $this->login($usuario);
        $second = $this->login($usuario);

        $this->api('PUT', '/api/auth/me/password', [
            'passwordActual' => self::PASSWORD, 'passwordNueva' => 'New-synthetic-password-5678',
        ], $first)->assertNoContent();

        $this->assertDatabaseCount('auth_sessions', 0);
        $this->assertTrue(app(JavaBcrypt::class)->verify('New-synthetic-password-5678', $usuario->fresh()->password));
        $this->assertSame(1, $usuario->fresh()->token_version);
        $this->api('GET', '/api/auth/me', token: $first)->assertUnauthorized();
        $this->api('GET', '/api/auth/me', token: $second)->assertUnauthorized();
        $this->api('POST', '/api/auth/login', ['email' => $usuario->email, 'password' => 'New-synthetic-password-5678'])->assertOk();
    }

    public function test_case_only_password_hash_changes_cannot_restore_an_old_session(): void
    {
        $vectors = json_decode(file_get_contents(__DIR__.'/../Fixtures/java-bcrypt.json'), true, flags: JSON_THROW_ON_ERROR);
        $hash = $vectors['vectors'][0]['hash'];
        $usuario = Usuario::factory()->create(['password' => $hash]);
        $token = $this->login($usuario);
        $changed = substr($hash, 0, 7).preg_replace_callback('/[a-zA-Z]/', fn ($match) => ctype_lower($match[0]) ? strtoupper($match[0]) : strtolower($match[0]), substr($hash, 7), 1);
        $this->assertNotSame($hash, $changed);

        DB::table('usuarios')->where('id', $usuario->id)->update(['password' => $changed]);
        DB::table('usuarios')->where('id', $usuario->id)->update(['password' => $hash]);

        $this->api('GET', '/api/auth/me', token: $token)->assertUnauthorized();
        $this->assertSame(2, $usuario->fresh()->token_version);
        $this->assertDatabaseCount('auth_sessions', 0);
    }

    public function test_wrong_current_password_returns_400_without_revocation(): void
    {
        $token = $this->login(Usuario::factory()->create());

        $this->api('PUT', '/api/auth/me/password', ['passwordActual' => 'wrong', 'passwordNueva' => 'New-synthetic-password-5678'], $token)
            ->assertBadRequest()->assertJsonPath('detail', 'La contraseña actual no es correcta');
        $this->api('GET', '/api/auth/me', token: $token)->assertOk();
    }

    public static function invalidPasswords(): array
    {
        return [
            'missing' => [null],
            'too short' => ['short'],
            'too long' => [str_repeat('a', 73)],
            'too many UTF8 bytes' => [str_repeat('á', 37)],
            'nul byte' => ["Password-null\0-1234"],
            'array' => [['not text']],
        ];
    }

    #[DataProvider('invalidPasswords')]
    public function test_invalid_new_password_returns_400_without_changing_data(mixed $password): void
    {
        $usuario = Usuario::factory()->create();
        $token = $this->login($usuario);

        $this->api('PUT', '/api/auth/me/password', ['passwordActual' => self::PASSWORD, 'passwordNueva' => $password], $token)
            ->assertBadRequest()->assertJsonValidationErrors('passwordNueva');
        $this->assertSame($usuario->password, $usuario->fresh()->password);
        $this->assertDatabaseCount('auth_sessions', 1);
    }

    public function test_profile_updates_preserve_contract_and_cannot_escalate_permissions(): void
    {
        $usuario = Usuario::factory()->create();
        $token = $this->login($usuario);

        $response = $this->api('PUT', '/api/auth/me', [
            'nombre' => 'Nombre actualizado', 'email' => 'NUEVO@example.test',
            'roles' => ['SUPER_ADMIN'], 'activo' => false, 'token_version' => 999,
        ], $token)->assertOk()->assertJsonPath('roles', ['PRENSA'])->assertJsonPath('email', 'nuevo@example.test');

        $this->assertDatabaseHas('usuarios', ['id' => $usuario->id, 'nombre' => 'Nombre actualizado', 'email' => 'nuevo@example.test', 'activo' => true]);
        $this->api('GET', '/api/auth/me', token: $token)->assertUnauthorized();
        $this->api('GET', '/api/auth/me', token: $response->json('token'))->assertOk();
    }

    public function test_profile_returns_409_for_a_case_insensitive_duplicate_email(): void
    {
        $usuario = Usuario::factory()->create();
        Usuario::factory()->create(['email' => 'used@example.test']);
        $token = $this->login($usuario);

        $this->api('PUT', '/api/auth/me', ['nombre' => 'Updated', 'email' => 'USED@example.test'], $token)->assertConflict();
        $this->assertSame($usuario->email, $usuario->fresh()->email);
        $this->api('GET', '/api/auth/me', token: $token)->assertOk();
    }

    public function test_an_old_email_assigned_to_another_user_does_not_transfer_a_session(): void
    {
        $usuario = Usuario::factory()->create(['email' => 'old@example.test']);
        $token = $this->login($usuario);
        $this->api('PUT', '/api/auth/me', ['nombre' => 'Updated', 'email' => 'new@example.test'], $token)->assertOk();
        Usuario::factory()->withRole(Role::SuperAdmin)->create(['email' => 'old@example.test']);

        $this->api('GET', '/api/auth/me', token: $token)->assertUnauthorized();
    }

    public static function rejectedOrigins(): array
    {
        return [
            'cross site' => [['Sec-Fetch-Site' => 'cross-site', 'Origin' => 'https://evil.example']],
            'sibling' => [['Sec-Fetch-Site' => 'same-site', 'Origin' => 'https://sibling.localhost']],
            'no fetch metadata' => [['Sec-Fetch-Site' => '', 'Origin' => 'https://localhost:18443']],
            'inconsistent origin' => [['Origin' => 'https://evil.example']],
            'null origin' => [['Origin' => 'null']],
        ];
    }

    #[DataProvider('rejectedOrigins')]
    public function test_csrf_returns_403_before_login_or_logout_for_untrusted_origins(array $headers): void
    {
        $this->api('POST', '/api/auth/login', [], headers: $headers)->assertForbidden();
        $this->api('POST', '/api/auth/logout', headers: $headers)->assertForbidden();
        $this->assertDatabaseCount('auth_sessions', 0);
    }

    public function test_csrf_protects_authenticated_password_changes(): void
    {
        $usuario = Usuario::factory()->create();
        $token = $this->login($usuario);

        $this->api('PUT', '/api/auth/me/password', ['passwordActual' => self::PASSWORD, 'passwordNueva' => 'New-synthetic-password-5678'], $token, ['Sec-Fetch-Site' => 'cross-site'])
            ->assertForbidden();
        $this->assertSame($usuario->password, $usuario->fresh()->password);
        $this->assertDatabaseCount('auth_sessions', 1);
    }

    public function test_the_eleventh_attempt_returns_429_with_retry_after_and_window_expires(): void
    {
        $this->freezeTime();
        for ($i = 0; $i < 10; $i++) {
            $this->api('POST', '/api/auth/login', ['email' => 'none@example.test', 'password' => 'wrong'])->assertUnauthorized();
        }

        $this->api('POST', '/api/auth/login', [], headers: ['X-Forwarded-For' => '192.0.2.200'])
            ->assertTooManyRequests()->assertHeader('Retry-After', '300')
            ->assertExactJson(['mensaje' => 'Demasiados intentos. Esperá antes de volver a iniciar sesión.']);
        $this->travel(301)->seconds();
        $this->api('POST', '/api/auth/login', ['email' => 'none@example.test', 'password' => 'wrong'])->assertUnauthorized();
    }

    public static function roleMatrix(): array
    {
        $matrix = [];
        foreach (Role::cases() as $actual) {
            foreach (Role::cases() as $required) {
                $matrix[$actual->value.' to '.$required->value] = [$actual, $required, $actual === Role::SuperAdmin || $actual === $required];
            }
        }

        return $matrix;
    }

    #[DataProvider('roleMatrix')]
    public function test_role_middleware_enforces_the_permission_matrix(Role $actual, Role $required, bool $allowed): void
    {
        Route::middleware(['api', AuthenticateSession::class, RequireRole::class.':'.$required->value])
            ->get('/api/auth/test-permission', fn () => response()->json(['ok' => true]));
        $token = $this->login(Usuario::factory()->withRole($actual)->create());

        $response = $this->api('GET', '/api/auth/test-permission', token: $token);

        $allowed ? $response->assertOk() : $response->assertForbidden();
    }

    public function test_permissions_are_read_from_database_instead_of_jwt_claims(): void
    {
        Route::middleware(['api', AuthenticateSession::class, RequireRole::class.':PRENSA'])
            ->get('/api/auth/test-permission', fn () => response()->json(['ok' => true]));
        $usuario = Usuario::factory()->create();
        $token = $this->login($usuario);
        DB::table('usuario_roles')->where('usuario_id', $usuario->id)->update(['rol' => 'HACIENDA']);

        $this->api('GET', '/api/auth/me', token: $token)->assertOk()->assertJsonPath('roles', ['HACIENDA']);
        $this->api('GET', '/api/auth/test-permission', token: $token)->assertForbidden();
    }
}
