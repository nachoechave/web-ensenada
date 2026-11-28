<?php

declare(strict_types=1);

use App\Support\IwindsPublicProbe as Probe;
use Illuminate\Contracts\Console\Kernel;
use Symfony\Component\Process\Process;

const APP_ROOT = '/home/ensenada/backend-laravel';
const RELEASE = '/home/ensenada/iwinds-admin-period';
const TEST_ORIGIN = 'https://prueba.ensenada.gov.ar';
const TEST_PASSWORD = 'Synthetic-password-1234';

$testIp = gethostbyname('prueba.ensenada.gov.ar');
if (! is_file('/.dockerenv') || getenv('IW_FINAL_TEST') !== 'yes' || getcwd() !== APP_ROOT
    || filter_var($testIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false
    || filter_var($testIp, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE) !== false
    || ! is_file('/tmp/staging-ca.crt')) {
    exit(1);
}

require APP_ROOT.'/vendor/autoload.php';
execute(['bash', RELEASE.'/install.sh', 'install']);
execute(['bash', RELEASE.'/install.sh', 'install']);
$app = require APP_ROOT.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
set_exception_handler(static function (Throwable $error): never {
    fwrite(STDERR, $error->getMessage().PHP_EOL);
    exit(1);
});
if (config('database.connections.mysql.host') !== 'mysql-auth'
    || config('database.connections.mysql.database') !== 'ensenada_auth_test') {
    exit(1);
}

function ensure(bool $result, string $label): void
{
    if (! $result) {
        throw new RuntimeException($label);
    }
}

function execute(array $args, bool $success = true): string
{
    $process = new Process($args, timeout: 20);
    $process->run();
    $out = $process->getOutput().$process->getErrorOutput();
    ensure($process->isSuccessful() === $success, implode(' ', $args)."\n".$out);

    return $out;
}

function command(string $operation, array $args = [], bool $success = true): string
{
    return execute([PHP_BINARY, 'deploy/iwinds/provision-staging.php', $operation, ...$args], $success);
}

function call(
    string $method,
    string $path,
    int $expected,
    ?string $cookie = null,
    string|array|null $data = null,
    bool $basic = true,
    bool $crossSite = false,
): array {
    $curl = curl_init(TEST_ORIGIN.$path);
    $headers = [];
    $sent = ['Accept: application/json', 'Origin: '.($crossSite ? 'https://outside.invalid' : TEST_ORIGIN),
        'Sec-Fetch-Site: '.($crossSite ? 'cross-site' : 'same-origin')];
    if (is_string($data)) {
        $sent[] = 'Content-Type: application/json';
    }
    if ($basic) {
        curl_setopt($curl, CURLOPT_USERPWD, 'synthetic-basic:synthetic-password');
    }
    if ($cookie !== null) {
        curl_setopt($curl, CURLOPT_COOKIE, 'WEB_ENSENADA_SESSION='.$cookie);
    }
    if ($data !== null) {
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
    }
    curl_setopt_array($curl, [
        CURLOPT_CUSTOMREQUEST => $method, CURLOPT_HTTPHEADER => $sent, CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CAINFO => '/tmp/staging-ca.crt', CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2, CURLOPT_TIMEOUT => 20,
        CURLOPT_HEADERFUNCTION => static function (CurlHandle $handle, string $line) use (&$headers): int {
            if (str_contains($line, ':')) {
                [$key, $value] = explode(':', $line, 2);
                $headers[strtolower($key)] = trim($value);
            }

            return strlen($line);
        },
    ]);
    $body = curl_exec($curl);
    $code = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    ensure($code === $expected && is_string($body), "$method $path respondió $code, se esperaba $expected.");

    return [$body, $headers];
}

function login(string $role): string
{
    [$body, $headers] = call('POST', '/api/auth/login', 200, data: json_encode([
        'email' => $role.'@example.test', 'password' => TEST_PASSWORD,
    ], JSON_THROW_ON_ERROR));
    $cookie = $headers['set-cookie'] ?? '';
    ensure(preg_match('/\AWEB_ENSENADA_SESSION=([^;]+)/', $cookie, $match) === 1
        && stripos($cookie, 'HttpOnly') !== false && stripos($cookie, 'Secure') !== false
        && stripos($cookie, 'SameSite=Strict') !== false && stripos($cookie, 'Path=/api') !== false
        && (json_decode($body, true, flags: JSON_THROW_ON_ERROR)['token'] ?? null) === '', 'Contrato de sesión staging inválido.');

    return $match[1];
}

$db = new PDO('mysql:host=mysql-auth;dbname=ensenada_auth_test', 'auth_setup', 'synthetic-setup-only-1234');
$db->exec('DELETE FROM auth_login_windows');
$db->exec("UPDATE usuarios SET token_version=1 WHERE email='super_admin@example.test'");
$readAdmin = static fn () => $db->query("SELECT id, password, token_version, activo FROM usuarios WHERE email='super_admin@example.test'")->fetch(PDO::FETCH_ASSOC);
$original = $readAdmin();
$proof = hash_hmac('sha256', $original['id'].':'.$original['token_version'].':'.$original['password'], config('auth_migration.jwt_secret'));
file_put_contents(Probe::ROTATION, $original['id'].' '.$original['token_version'].' '.$proof."\n");
chmod(Probe::ROTATION, 0600);

ensure(str_contains(execute([PHP_BINARY, 'deploy/iwinds/schema-check.php', '--privilege-profile=cpanel-dml']), 'cpanel-dml'), 'El fixture no usa cpanel-dml.');
call('GET', '/api/auth/me', 401, basic: false);
call('GET', '/api/auth/me', 503);
command('open-admin-extended', ['7'], false);
command('open-admin-extended', ['8', '--synthetic-only'], false);
command('renew-admin-extended', ['7', '--synthetic-only'], false);
command('open-full', success: false);
command('open-admin-extended', ['1', '--synthetic-only']);
$first = Probe::activeAdminPeriodExpiry(APP_ROOT);
ensure($first !== null && $first > time() + 86390, 'Falta el período de un día.');
ensure(str_contains(command('status-admin-extended'), gmdate(DATE_ATOM, $first)), 'El estado no muestra el vencimiento.');
execute(['bash', RELEASE.'/install.sh', 'rollback'], false);
execute([PHP_BINARY, '/repo/backend-laravel/tests/Stage8/PublicProbeBootstrap.php', 'bearer']);

call('GET', '/api/auth/me', 401, basic: false);
call('GET', '/api/admin/noticias', 401);
call('POST', '/api/auth/login', 403, data: '{}', crossSite: true);
$press = login('prensa');
$super = login('super_admin');
call('GET', '/api/admin/noticias', 200, $press);
foreach (['usuarios', 'hacienda', 'proveedores/documentos', 'site-content'] as $route) {
    call('GET', '/api/admin/'.$route, 403, $press);
    call('GET', '/api/admin/'.$route, 200, $super);
}
command('renew-admin-extended', ['7', '--synthetic-only']);
$renewed = Probe::activeAdminPeriodExpiry(APP_ROOT);
ensure($renewed !== null && $renewed > $first && $renewed > time() + 7 * 86400 - 10, 'La renovación no extendió la vigencia.');
command('renew-admin-extended', ['1', '--synthetic-only'], false);
call('GET', '/api/admin/noticias', 200, $press);

$png = tempnam('/tmp', 'period-photo-');
imagepng(imagecreatetruecolor(40, 30), $png);
try {
    [$uploaded] = call('POST', '/api/admin/archivos/noticias', 200, $press, ['archivo' => new CURLFile($png, 'image/png', 'period.png')]);
    $image = json_decode($uploaded, true, flags: JSON_THROW_ON_ERROR);
    ensure(preg_match('~\A/uploads/noticias/[a-f0-9-]{36}\.webp\z~', $image['url'] ?? '') === 1, 'La foto no se optimizó a WebP.');
    [$bytes] = call('GET', $image['url'], 200);
    ensure((getimagesizefromstring($bytes)['mime'] ?? '') === 'image/webp', 'No se descargó la foto optimizada.');
} finally {
    unlink($png);
}

call('POST', '/api/auth/logout', 204, $press, '{}');
call('GET', '/api/admin/noticias', 401, $press);
$press = login('prensa');
$db->prepare('UPDATE auth_sessions SET expires_at=? WHERE token_hash=?')->execute([time() - 1, hash('sha256', $press)]);
call('GET', '/api/admin/noticias', 401, $press);
$press = login('prensa');
$pressId = (int) $db->query("SELECT id FROM usuarios WHERE email='prensa@example.test'")->fetchColumn();
call('PUT', "/api/admin/usuarios/$pressId/activo", 200, $super, '{"activo":false}');
call('GET', '/api/admin/noticias', 401, $press);
call('PUT', "/api/admin/usuarios/$pressId/activo", 200, $super, '{"activo":true}');
call('GET', '/api/admin/noticias', 401, $press);

$now = time();
file_put_contents(Probe::ADMIN_MARKER, Probe::extendedAdminMarker($now - 86401, $now - 1, $proof));
call('GET', '/api/admin/usuarios', 503, $super);
command('renew-admin-extended', ['7', '--synthetic-only'], false);
execute([PHP_BINARY, '-n', 'deploy/iwinds/provision-staging.php', 'close-admin-extended']);
call('GET', '/api/admin/usuarios', 503, $super);

command('open-admin-extended', ['1', '--synthetic-only']);
call('GET', '/api/admin/usuarios', 200, $super);
$db->beginTransaction();
$db->query("SELECT name FROM auth_mutexes WHERE name='schema-ready' FOR UPDATE")->fetchColumn();
$renewal = new Process([PHP_BINARY, 'deploy/iwinds/provision-staging.php', 'renew-admin-extended', '7', '--synthetic-only'], timeout: 20);
try {
    $observer = new PDO('mysql:host=mysql-auth;dbname=ensenada_auth_test', 'auth_runtime', 'synthetic-runtime-only-1234');
    $renewal->start();
    $deadline = microtime(true) + 4;
    do {
        $waiting = $observer->query("SELECT COUNT(*) FROM information_schema.PROCESSLIST
            WHERE ID <> CONNECTION_ID() AND DB = DATABASE() AND LOWER(INFO) LIKE '%auth_mutexes%for update%'")->fetchColumn() > 0;
        if (! $waiting) {
            usleep(10000);
        }
    } while (! $waiting && $renewal->isRunning() && microtime(true) < $deadline);
    ensure($waiting, 'No se pudo reproducir la renovación pendiente de MariaDB.');
    execute([PHP_BINARY, '-n', 'deploy/iwinds/provision-staging.php', 'close-admin-extended']);
    call('GET', '/api/admin/usuarios', 503, $super);
} finally {
    $db->rollBack();
}
$renewalExit = $renewal->wait();
ensure($renewalExit === 1 && str_contains($renewal->getErrorOutput(), 'El estado cambió'),
    "La renovación pendiente no confirmó la cancelación ($renewalExit): ".$renewal->getErrorOutput());
call('GET', '/api/admin/usuarios', 503, $super);
ensure($readAdmin() === $original && ! file_exists(Probe::FULL_MARKER), 'Se modificó el administrador o el marcador completo.');
execute(['bash', RELEASE.'/install.sh', 'rollback']);
execute(['bash', RELEASE.'/install.sh', 'rollback']);
execute(['sha256sum', '-c', RELEASE.'/BASELINE.sha256']);
command('open-admin');
call('GET', '/api/admin/usuarios', 200, $super);
unlink(Probe::ADMIN_MARKER);
call('GET', '/api/admin/usuarios', 503, $super);
echo "HTTPS local: período, renovación, permisos, WebP, sesiones, cierre y reversión aprobados.\n";
