<?php

if (getenv('ENSENADA_PRODUCTION_TEST_DISPOSABLE') !== 'true' || ! is_file('/.dockerenv')) {
    exit(1);
}
require '/home/ensenada/backend-laravel-produccion/vendor/autoload.php';
use App\Support\SharedDatabaseAudit;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use Symfony\Component\Process\Process;

const P = '/home/ensenada/backend-laravel-produccion';
const H = P.'/deploy/production/handover.php';
const C = '/home/ensenada/respaldos_privados/schema.cnf';
$checks = 0;
function ok(bool $valid, string $label): void
{
    global $checks;
    $checks++;
    if (! $valid) {
        throw new RuntimeException("FALLO $checks: $label");
    }
}
function command(array $args, bool $success = true): Process
{
    $p = new Process([PHP_BINARY, ...$args], timeout: 600);
    $p->run();
    ok($p->isSuccessful() === $success, implode(' ', array_slice($args, 0, 2)).' '.$p->getOutput().$p->getErrorOutput());

    return $p;
}
function hand(string $op, array $args = [], bool $success = true): Process
{
    return command([H, $op, ...$args], $success);
}
function http(string $host = 'www.ensenada.gov.ar', bool $basic = false): Client
{
    return new Client(['base_uri' => 'https://'.$host, 'verify' => false, 'http_errors' => false, 'allow_redirects' => false, 'auth' => $basic ? ['staging', 'synthetic-basic-only'] : null, 'cookies' => new CookieJar, 'headers' => ['Origin' => 'https://'.$host, 'Sec-Fetch-Site' => 'same-origin']]);
}
function schema(): PDO
{
    return SharedDatabaseAudit::connect(SharedDatabaseAudit::schemaConfig(C));
}
