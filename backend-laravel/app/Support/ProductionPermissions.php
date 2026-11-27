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

    public static function verifyPdo(\PDO $pdo): void
    {
        $db = 'ensenada_web';
        if ($pdo->query('SELECT DATABASE()')->fetchColumn() !== $db) {
            throw new RuntimeException('Base de ejecución inesperada.');
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
        $account = $pdo->query('SELECT CURRENT_USER()')->fetchColumn();
        [$user, $host] = array_pad(explode('@', $account, 2), 2, '');
        if (count($required) !== 12 || $user !== 'ensenada_web_app' || ! in_array($host, ['localhost', '127.0.0.1'], true)) {
            throw new RuntimeException('Cuenta o plantilla de permisos inesperada.');
        }
        $actual = [];
        $recipient = '(?:`'.preg_quote($user, '/').'`@`'.preg_quote($host, '/')."`|'".preg_quote($user, '/')."'@'".preg_quote($host, '/')."')";
        foreach ($pdo->query('SHOW GRANTS')->fetchAll(\PDO::FETCH_COLUMN) as $line) {
            if (str_contains(strtoupper($line), 'WITH GRANT OPTION')
                || ! preg_match('/^GRANT (.+) ON (.+) TO '.$recipient.'(?: |$)/D', $line, $parts)) {
                throw new RuntimeException('Permisos delegados o heredados no admitidos.');
            }
            if ($parts[1] === 'USAGE' && $parts[2] === '*.*') {
                continue;
            }
            if (! isset($required[$parts[2]]) || isset($actual[$parts[2]])) {
                throw new RuntimeException('Producción exige privilegios mínimos por tabla.');
            }
            $actual[$parts[2]] = $normalize($parts[1]);
        }
        ksort($required);
        ksort($actual);
        if ($required !== $actual) {
            throw new RuntimeException('Permisos distintos del perfil de producción.');
        }
    }
}
