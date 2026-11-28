<?php

namespace Tests\Http;

use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Promise\Utils;
use PDO;
use PHPUnit\Framework\TestCase;

/** Actual TLS requests, cookie jar and live PHP server; no Laravel test-kernel shortcuts. */
class AngularCookiesTest extends TestCase
{
    private Client $browser;

    private PDO $database;

    private int $userId;

    private string $email;

    private array $history = [];

    private ?HandlerStack $stack = null;

    private array $userIds = [];

    private static int $sequence = 0;

    private function browser(): Client
    {
        if ($this->stack === null) {
            $this->stack = HandlerStack::create();
            $this->stack->push(Middleware::history($this->history));
        }

        return new Client([
            'base_uri' => 'https://localhost:18443',
            'verify' => '/certs/root.crt',
            'curl' => [CURLOPT_RESOLVE => ['localhost:18443:'.gethostbyname('tls')]],
            'cookies' => new CookieJar,
            'handler' => $this->stack,
            'http_errors' => false,
            'timeout' => 10,
            'headers' => [
                'Accept' => 'application/json',
                'Origin' => 'https://localhost:18443',
                'Sec-Fetch-Site' => 'same-origin',
            ],
        ]);
    }

    protected function setUp(): void
    {
        $this->browser = $this->browser();
        $this->database = DisposableDatabase::connect();
        $this->database->exec('DELETE FROM auth_login_windows');
        $this->email = 'http-'.++self::$sequence.'@example.test';
        $this->userId = $this->fixtureUser($this->email);
    }

    private function fixtureUser(string $email, string $role = 'PRENSA'): int
    {
        $fixture = json_decode(file_get_contents(__DIR__.'/../Fixtures/java-bcrypt.json'), true, flags: JSON_THROW_ON_ERROR);
        $this->database->prepare('INSERT INTO usuarios(nombre,email,password,rol,activo,token_version) VALUES(?,?,?,?,1,0)')
            ->execute(['Usuario HTTP sintético', $email, $fixture['vectors'][0]['hash'], $role]);
        $id = (int) $this->database->lastInsertId();
        $this->userIds[] = $id;
        $this->database->prepare('INSERT INTO usuario_roles(usuario_id,rol) VALUES(?,?)')->execute([$id, $role]);

        return $id;
    }

    protected function tearDown(): void
    {
        foreach ($this->userIds as $id) {
            foreach (['auth_sessions', 'usuario_roles'] as $table) {
                $this->database->prepare("DELETE FROM $table WHERE usuario_id = ?")->execute([$id]);
            }
            $this->database->prepare('DELETE FROM usuarios WHERE id = ?')->execute([$id]);
        }
    }

    private function login(?Client $browser = null, string $password = 'Synthetic-password-1234'): array
    {
        $response = ($browser ?? $this->browser)->post('/api/auth/login', ['json' => ['email' => $this->email, 'password' => $password]]);
        $this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());

        return json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
    }

    private function adminBrowser(string $suffix): Client
    {
        $email = $suffix.'-'.$this->email;
        $this->fixtureUser($email, 'SUPER_ADMIN');
        $browser = $this->browser();
        $this->assertSame(200, $browser->post('/api/auth/login', ['json' => ['email' => $email, 'password' => 'Synthetic-password-1234']])->getStatusCode());

        return $browser;
    }

    public function test_competing_admins_get_one_creation_and_five_conflicts_for_the_same_email(): void
    {
        $admins = [$this->adminBrowser('admin-a'), $this->adminBrowser('admin-b')];
        $email = 'concurrent-'.$this->email;
        $requests = [];
        for ($i = 0; $i < 6; $i++) {
            $requests[] = $admins[$i % 2]->postAsync('/api/admin/usuarios', ['json' => [
                'nombre' => 'Creación simultánea', 'email' => $i % 2 ? ' '.strtoupper($email).' ' : $email,
                'roles' => ['HACIENDA'], 'activo' => true, 'password' => 'Synthetic-password-1234',
            ]]);
        }
        $responses = Utils::unwrap($requests);
        $select = $this->database->prepare('SELECT id FROM usuarios WHERE LOWER(email) = ?');
        $select->execute([$email]);
        $ids = $select->fetchAll(PDO::FETCH_COLUMN);
        array_push($this->userIds, ...$ids);
        $statuses = array_count_values(array_map(fn ($response): int => $response->getStatusCode(), $responses));
        ksort($statuses);
        $this->assertSame([201 => 1, 409 => 5], $statuses);
        $this->assertCount(1, $ids);
        $roles = $this->database->prepare('SELECT rol FROM usuario_roles WHERE usuario_id = ?');
        $roles->execute([$ids[0]]);
        $this->assertSame(['HACIENDA'], $roles->fetchAll(PDO::FETCH_COLUMN));
    }

    public function test_a_login_racing_with_deactivation_cannot_leave_a_live_session(): void
    {
        $this->login();
        $admin = $this->adminBrowser('admin');
        $racingBrowser = $this->browser();
        $path = "/api/admin/usuarios/$this->userId/activo";
        [$login, $disable] = Utils::unwrap([
            $racingBrowser->postAsync('/api/auth/login', ['json' => ['email' => $this->email, 'password' => 'Synthetic-password-1234']]),
            $admin->putAsync($path, ['json' => ['activo' => false]]),
        ]);
        $this->assertSame(200, $disable->getStatusCode());
        $this->assertContains($login->getStatusCode(), [200, 403]);
        $this->assertSame(200, $admin->put($path, ['json' => ['activo' => true]])->getStatusCode());
        $this->assertSame(401, $this->browser->get('/api/auth/me')->getStatusCode());
        if ($login->getStatusCode() === 200) {
            $this->assertSame(401, $racingBrowser->get('/api/auth/me')->getStatusCode());
        }
        $this->login();
    }

    public function test_angular_login_and_profile_use_secure_cookies_without_authorization_headers(): void
    {
        $body = $this->login();
        $cookie = $this->browser->getConfig('cookies')->toArray()[0];

        $response = $this->browser->get('/api/auth/me');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['token', 'nombre', 'email', 'rol', 'roles'], array_keys($body));
        $this->assertSame(['PRENSA'], $body['roles']);
        $this->assertTrue($cookie['Secure']);
        $this->assertTrue($cookie['HttpOnly']);
        $this->assertSame('/api', $cookie['Path']);
        $this->assertStringContainsString('samesite=strict', strtolower($this->history[0]['response']->getHeaderLine('Set-Cookie')));
        $this->assertTrue($this->history[1]['request']->hasHeader('Cookie'));
        $this->assertFalse($this->history[1]['request']->hasHeader('Authorization'));
        $this->browser->get('/admin/login');
        $this->assertFalse($this->history[2]['request']->hasHeader('Cookie'));
    }

    public function test_wrong_credentials_and_validation_preserve_401_and_400(): void
    {
        $wrong = $this->browser->post('/api/auth/login', ['json' => ['email' => $this->email, 'password' => 'wrong']]);
        $invalid = $this->browser->post('/api/auth/login', ['json' => []]);

        $this->assertSame(401, $wrong->getStatusCode());
        $this->assertSame(['mensaje' => 'Email o contraseña incorrectos'], json_decode((string) $wrong->getBody(), true));
        $this->assertSame(400, $invalid->getStatusCode());
    }

    public function test_inactive_users_receive_403_without_a_cookie(): void
    {
        $this->database->prepare('UPDATE usuarios SET activo = 0 WHERE id = ?')->execute([$this->userId]);

        $response = $this->browser->post('/api/auth/login', ['json' => ['email' => $this->email, 'password' => 'Synthetic-password-1234']]);

        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame([], $this->browser->getConfig('cookies')->toArray());
    }

    public function test_logout_clears_the_browser_cookie_and_replay_is_unauthorized(): void
    {
        $token = $this->login()['token'];

        $response = $this->browser->post('/api/auth/logout', ['json' => null]);

        $this->assertSame(204, $response->getStatusCode());
        $this->assertSame('', (string) $response->getBody());
        $this->assertSame([], $this->browser->getConfig('cookies')->toArray());
        $this->assertSame(401, $this->browser->get('/api/auth/me')->getStatusCode());
        $replay = $this->browser()->get('/api/auth/me', ['headers' => ['Cookie' => 'WEB_ENSENADA_SESSION='.$token]]);
        $this->assertSame(401, $replay->getStatusCode());
    }

    public function test_password_change_revokes_two_real_cookie_sessions(): void
    {
        $this->login();
        $other = $this->browser();
        $this->login($other);

        $response = $this->browser->put('/api/auth/me/password', ['json' => [
            'passwordActual' => 'Synthetic-password-1234', 'passwordNueva' => 'New-synthetic-password-5678',
        ]]);

        $this->assertSame(204, $response->getStatusCode());
        $this->assertSame(401, $other->get('/api/auth/me')->getStatusCode());
        $this->login(password: 'New-synthetic-password-5678');
    }

    public function test_direct_sql_deactivation_and_reactivation_permanently_revoke_the_cookie(): void
    {
        $this->login();

        $this->database->prepare('UPDATE usuarios SET activo = 0 WHERE id = ?')->execute([$this->userId]);
        $this->database->prepare('UPDATE usuarios SET activo = 1 WHERE id = ?')->execute([$this->userId]);

        $this->assertSame(401, $this->browser->get('/api/auth/me')->getStatusCode());
    }

    public function test_csrf_rejects_cross_origin_and_missing_fetch_metadata_before_login(): void
    {
        $body = ['email' => $this->email, 'password' => 'Synthetic-password-1234'];

        $crossOrigin = $this->browser->post('/api/auth/login', [
            'json' => $body, 'headers' => ['Origin' => 'https://evil.example', 'Sec-Fetch-Site' => 'cross-site'],
        ]);
        $missing = $this->browser->post('/api/auth/login', ['json' => $body, 'headers' => ['Sec-Fetch-Site' => '']]);

        $this->assertSame(403, $crossOrigin->getStatusCode());
        $this->assertSame(403, $missing->getStatusCode());
        $this->assertSame([], $this->browser->getConfig('cookies')->toArray());
    }

    public function test_csrf_rejects_a_password_change_even_when_the_attacker_supplies_a_cookie(): void
    {
        $this->login();

        $response = $this->browser->put('/api/auth/me/password', [
            'json' => ['passwordActual' => 'Synthetic-password-1234', 'passwordNueva' => 'Attacker-password-1234'],
            'headers' => ['Origin' => 'https://evil.example', 'Sec-Fetch-Site' => 'cross-site'],
        ]);

        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame(200, $this->browser->get('/api/auth/me')->getStatusCode());
    }

    public function test_the_eleventh_real_login_attempt_is_rate_limited(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $response = $this->browser->post('/api/auth/login', ['json' => ['email' => $this->email, 'password' => 'wrong']]);
            $this->assertSame(401, $response->getStatusCode());
        }

        $response = $this->browser->post('/api/auth/login', [
            'json' => ['email' => $this->email, 'password' => 'wrong'],
            'headers' => ['X-Forwarded-For' => '192.0.2.200'],
        ]);

        $this->assertSame(429, $response->getStatusCode());
        $this->assertGreaterThan(0, (int) $response->getHeaderLine('Retry-After'));
    }

    public function test_expired_sessions_are_rejected_over_http(): void
    {
        $this->login();
        $this->database->prepare('UPDATE auth_sessions SET expires_at = 1 WHERE usuario_id = ?')->execute([$this->userId]);

        $this->assertSame(401, $this->browser->get('/api/auth/me')->getStatusCode());
    }

    public function test_concurrent_requests_share_a_single_atomic_login_budget(): void
    {
        $requests = [];
        for ($i = 0; $i < 20; $i++) {
            $requests[] = $this->browser->postAsync('/api/auth/login', [
                'json' => ['email' => $this->email, 'password' => 'wrong'],
            ]);
        }

        $responses = Utils::unwrap($requests);
        $statuses = array_count_values(array_map(fn ($response): int => $response->getStatusCode(), $responses));

        $this->assertSame(10, $statuses[401] ?? 0);
        $this->assertSame(10, $statuses[429] ?? 0);
        $this->assertCount(2, $statuses);
    }

    public function test_concurrent_successful_logins_issue_distinct_live_sessions(): void
    {
        $requests = [];
        for ($i = 0; $i < 8; $i++) {
            $requests[] = $this->browser->postAsync('/api/auth/login', [
                'json' => ['email' => $this->email, 'password' => 'Synthetic-password-1234'],
            ]);
        }

        $responses = Utils::unwrap($requests);

        $tokens = [];
        foreach ($responses as $response) {
            $this->assertSame(200, $response->getStatusCode());
            $tokens[] = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR)['token'];
        }
        $this->assertCount(8, array_unique($tokens));
        foreach ($tokens as $token) {
            $this->assertSame(200, $this->browser->get('/api/auth/me', ['headers' => ['Authorization' => 'Bearer '.$token]])->getStatusCode());
        }
    }

    public function test_profile_update_rotates_the_cookie_and_preserves_roles(): void
    {
        $old = $this->login()['token'];

        $response = $this->browser->put('/api/auth/me', ['json' => [
            'nombre' => 'Nombre HTTP actualizado', 'email' => 'updated-'.$this->email, 'roles' => ['SUPER_ADMIN'],
        ]]);

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame(['PRENSA'], $data['roles']);
        $this->assertNotSame($old, $data['token']);
        $this->assertSame(200, $this->browser->get('/api/auth/me')->getStatusCode());
    }
}
