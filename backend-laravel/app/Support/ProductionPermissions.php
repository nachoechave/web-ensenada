<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use RuntimeException;

final class ProductionPermissions
{
    public static function verify(): void
    {
        self::verifyPdo(DB::connection()->getPdo());
    }

    public static function verifyPdo(\PDO $pdo, ?string $profile = null): void
    {
        $db = 'ensenada_web';
        if ($pdo->query('SELECT DATABASE()')->fetchColumn() !== $db) {
            throw new RuntimeException('Base de ejecución inesperada.');
        }
        self::assertGrants($pdo->query('SHOW GRANTS')->fetchAll(\PDO::FETCH_COLUMN), $pdo->query('SELECT CURRENT_USER()')->fetchColumn(), $profile ?? ProductionDatabaseProfile::selected());
        if ((int) $pdo->query("SELECT COUNT(*) FROM auth_mutexes WHERE name IN ('login','news-slug','proveedores','schema-ready')")->fetchColumn() !== 4) {
            throw new RuntimeException('Mutexes de integridad ausentes.');
        }
    }

    public static function assertGrants(array $lines, string $account, string $profile = 'per-table', bool $schema = false): void
    {
        $db = 'ensenada_web';
        if (! in_array($profile, ['per-table', 'cpanel-dml'], true)) {
            throw new RuntimeException('Perfil SQL desconocido.');
        }
        $normalize = static function (string $value): array {
            $parts = preg_split('/\s*,\s*/', str_replace('`', '', $value));
            sort($parts);

            return $parts;
        };
        $required = [];
        foreach (file(dirname(__DIR__, 2).'/deploy/iwinds/schema-grants.sql') as $line) {
            if (preg_match('/^GRANT (.+) ON `ensenada_web`[.]`([a-z_]+)` TO /D', $line, $parts)) {
                $required['`'.$db.'`.`'.$parts[2].'`'] = $normalize($parts[1]);
            }
        }
        if ($profile === 'cpanel-dml' || $schema) {
            // In schema-level GRANT, underscore is a wildcard unless escaped (MariaDB 10.11).
            $required = ['`ensenada\\_web`.*' => $schema ? ['ALL PRIVILEGES'] : ['DELETE', 'INSERT', 'SELECT', 'UPDATE']];
        }
        [$user, $host] = array_pad(explode('@', $account, 2), 2, '');
        if (($profile === 'per-table' && ! $schema && count($required) !== 12) || $user !== ($schema ? 'ensenada_web_schema' : 'ensenada_web_app') || ! in_array($host, ['localhost', '127.0.0.1'], true)) {
            throw new RuntimeException('Cuenta o plantilla de permisos inesperada.');
        }
        $actual = [];
        $recipient = '(?:`'.preg_quote($user, '/').'`@`'.preg_quote($host, '/')."`|'".preg_quote($user, '/')."'@'".preg_quote($host, '/')."')";
        foreach ($lines as $line) {
            if (str_contains(strtoupper($line), 'WITH GRANT OPTION')
                || ! preg_match('/^GRANT (.+) ON (.+) TO '.$recipient.'(?: |$)/D', $line, $parts)) {
                throw new RuntimeException('Permisos delegados o heredados no admitidos.');
            }
            if ($parts[1] === 'USAGE' && $parts[2] === '*.*') {
                continue;
            }
            if (! isset($required[$parts[2]]) || isset($actual[$parts[2]])) {
                throw new RuntimeException('Privilegios fuera del perfil SQL autorizado.');
            }
            $actual[$parts[2]] = $normalize($parts[1]);
        }
        ksort($required);
        ksort($actual);
        if ($required !== $actual) {
            throw new RuntimeException('Permisos distintos del perfil de producción.');
        }
    }

    public static function verifySchemaPdo(\PDO $pdo): void
    {
        if ($pdo->query('SELECT DATABASE()')->fetchColumn() !== 'ensenada_web') {
            throw new RuntimeException('Base de esquema inesperada.');
        }
        self::assertGrants($pdo->query('SHOW GRANTS')->fetchAll(\PDO::FETCH_COLUMN), $pdo->query('SELECT CURRENT_USER()')->fetchColumn(), 'cpanel-dml', true);
    }
}
