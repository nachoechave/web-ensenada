<?php

use App\Support\ProductionDatabaseProfile;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

$arguments = array_slice($argv, 1);
if (array_diff($arguments, ['--disposable', '--privilege-profile=cpanel-dml']) !== []
    || count($arguments) !== count(array_unique($arguments))) {
    fwrite(STDERR, 'Uso: php schema-check.php [--privilege-profile=cpanel-dml]'.PHP_EOL);
    exit(1);
}
$cpanelDml = in_array('--privilege-profile=cpanel-dml', $arguments, true);
$profile = $cpanelDml ? 'cpanel-dml' : 'por tabla (schema-grants.sql)';

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
try {
    $app->make(Kernel::class)->bootstrap();
    $disposable = in_array('--disposable', $arguments, true)
        && $app->environment(['local', 'testing'])
        && config('auth_migration.disposable_mysql') === true
        && config('database.default') === 'mysql'
        && config('database.connections.mysql.username') === 'auth_runtime';
    if ($app->environment('production') && $cpanelDml) {
        if (ProductionDatabaseProfile::selected() !== 'cpanel-dml') {
            throw new RuntimeException;
        }
    }
    if ($app->environment('production') && ! $cpanelDml && ProductionDatabaseProfile::selected() !== 'per-table') {
        throw new RuntimeException('Indicar explícitamente el perfil cpanel-dml autorizado.');
    }
    if (! $app->environment(['staging', 'production']) && ! $disposable) {
        throw new RuntimeException;
    }
} catch (Throwable) {
    fwrite(STDERR, 'Configuración privada de staging incompleta.'.PHP_EOL);
    exit(1);
}

$tables = [
    'usuarios' => ['id', 'email', 'password', 'activo', 'rol', 'token_version'],
    'usuario_roles' => ['usuario_id', 'rol'],
    'noticias' => ['id', 'slug', 'estado', 'destacada'],
    'noticia_imagenes' => ['noticia_id', 'url', 'orden'],
    'auditoria_noticias' => ['id', 'noticia_id'],
    'portal_site_content' => ['id', 'contenido_json'],
    'publicaciones_hacienda' => ['id', 'estado', 'fecha_publicacion'],
    'archivos_hacienda' => ['id', 'publicacion_hacienda_id', 'nombre_almacenado'],
    'documentos_proveedores' => ['id', 'nombre_almacenado', 'orden', 'activo'],
    'auth_sessions' => ['token_hash', 'usuario_id', 'token_version', 'expires_at'],
    'auth_login_windows' => ['ip_hash', 'attempts', 'expires_at'],
    'auth_mutexes' => ['name'],
];
$ok = true;
try {
    DB::connection()->getPdo();
    $expected = config('database.connections.mysql.collation');
    $version = DB::selectOne('SELECT VERSION() AS version')->version;
    $maria = preg_match('/^(?:5[.]5[.]5-)?10[.]11[.][0-9]+-MariaDB/i', $version) === 1;
    $mysql = ! str_contains(strtolower($version), 'mariadb')
        && preg_match('/^8[.](0|4)[.]([0-9]+)/', $version, $parts) === 1
        && ($parts[1] === '4' || (int) $parts[2] >= 16);
    if ((! $maria && ! $mysql) || ($maria && $expected !== 'utf8mb4_unicode_520_ci')) {
        fwrite(STDERR, 'Motor, versión o collation fuera del perfil de esquema verificado.'.PHP_EOL);
        exit(1);
    }
    if ($cpanelDml && ! $maria) {
        fwrite(STDERR, 'El perfil cpanel-dml está validado únicamente para MariaDB 10.11.'.PHP_EOL);
        exit(1);
    }
    $integrity = DB::selectOne('SELECT @@SESSION.foreign_key_checks AS foreign_keys, @@SESSION.unique_checks AS unique_keys'
        .($maria ? ', @@SESSION.check_constraint_checks AS check_constraints' : ''));
    if ((int) $integrity->foreign_keys !== 1 || (int) $integrity->unique_keys !== 1
        || ($maria && (int) $integrity->check_constraints !== 1)) {
        fwrite(STDERR, 'La sesión de ejecución tiene controles de integridad desactivados.'.PHP_EOL);
        exit(1);
    }
    $collation = DB::table('information_schema.SCHEMATA')->whereRaw('SCHEMA_NAME = DATABASE()')->value('DEFAULT_COLLATION_NAME');
    $objects = DB::table('information_schema.TABLES')->whereRaw('TABLE_SCHEMA = DATABASE()')
        ->get(['TABLE_NAME', 'ENGINE', 'TABLE_COLLATION']);
    $definitions = $objects->where('ENGINE', 'InnoDB')->where('TABLE_COLLATION', $expected)->pluck('TABLE_NAME')->all();
    if ($collation !== $expected || array_diff(array_keys($tables), $definitions) !== []) {
        fwrite(STDERR, 'La collation o el motor InnoDB no coinciden con el perfil configurado.'.PHP_EOL);
        exit(1);
    }
    if ($cpanelDml && array_diff($objects->pluck('TABLE_NAME')->all(), array_keys($tables),
        $disposable ? ['auth_schema_baseline'] : []) !== []) {
        fwrite(STDERR, 'El perfil cpanel-dml requiere una base dedicada sin tablas ni vistas ajenas al esquema.'.PHP_EOL);
        exit(1);
    }
    echo 'OK    motor '.$version.' / '.$expected.PHP_EOL;
} catch (Throwable) {
    fwrite(STDERR, 'Falta una conexión MySQL de staging.'.PHP_EOL);
    exit(1);
}
foreach ($tables as $table => $columns) {
    try {
        DB::table($table)->select($columns)->whereRaw('1 = 0')->get();
        echo 'OK    '.$table.PHP_EOL;
    } catch (Throwable) {
        echo 'FALTA '.$table.PHP_EOL;
        $ok = false;
    }
}
try {
    $database = config('database.connections.mysql.database');
    $normalize = static function (string $privileges): array {
        $list = preg_split('/\s*,\s*/', str_replace('`', '', $privileges));
        sort($list);

        return $list;
    };
    if ($cpanelDml) {
        // MariaDB treats unescaped underscores and percent signs as wildcards in schema grants.
        $literalDatabase = strtr($database, ['_' => '\\_', '%' => '\\%']);
        $required = ['`'.$literalDatabase.'`.*' => ['DELETE', 'INSERT', 'SELECT', 'UPDATE']];
    } else {
        $required = [];
        $template = file(__DIR__.'/schema-grants.sql', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($template as $line) {
            if (preg_match('/^GRANT (.+) ON `ensenada_web`[.]`([a-z_]+)` TO /D', $line, $parts)) {
                $required['`'.$database.'`.`'.$parts[2].'`'] = $normalize($parts[1]);
            }
        }
        $expectedScopes = array_map(fn (string $table): string => '`'.$database.'`.`'.$table.'`', array_keys($tables));
        if (array_diff($expectedScopes, array_keys($required)) !== [] || count($required) !== count($expectedScopes)) {
            throw new RuntimeException('Plantilla de permisos incompleta.');
        }
        if ($disposable) {
            $required['`'.$database.'`.`auth_schema_baseline`'] = ['SELECT'];
        }
    }
    [$user, $host] = explode('@', DB::selectOne('SELECT CURRENT_USER() AS account')->account, 2);
    if ($user !== config('database.connections.mysql.username')) {
        throw new RuntimeException('Cuenta efectiva inesperada.');
    }
    $quoteAccountPart = static fn (string $part): string => sprintf("(?:`%s`|'%s')", preg_quote($part, '/'), preg_quote($part, '/'));
    $account = $quoteAccountPart($user).'@'.$quoteAccountPart($host);
    $actual = [];
    foreach (DB::select('SHOW GRANTS') as $row) {
        $line = array_values((array) $row)[0];
        if (str_contains(strtoupper($line), 'WITH GRANT OPTION')) {
            throw new RuntimeException('Permiso de delegación no permitido.');
        }
        // MariaDB includes inherited PUBLIC grants and may include authentication metadata.
        [$grant, $recipient] = array_pad(explode(' TO ', $line, 2), 2, '');
        if (! preg_match('/^'.$account.'(?: |$)/D', $recipient)) {
            throw new RuntimeException('Grant heredado o cuenta inesperada.');
        }
        if ($grant === 'GRANT USAGE ON *.*') {
            continue;
        }
        if (! preg_match('/^GRANT (.+) ON (.+)$/D', $grant, $parts)
            || ! isset($required[$parts[2]]) || isset($actual[$parts[2]])) {
            throw new RuntimeException('Permiso fuera del alcance del perfil.');
        }
        $actual[$parts[2]] = $normalize($parts[1]);
    }
    ksort($actual);
    ksort($required);
    if ($actual !== $required) {
        throw new RuntimeException('Permisos distintos del perfil.');
    }
    echo 'OK    permisos de la cuenta de ejecución / '.$profile.PHP_EOL;
} catch (Throwable) {
    fwrite(STDERR, 'Los grants efectivos de la cuenta de ejecución no coinciden con el perfil '.$profile.'.'.PHP_EOL);
    exit(1);
}
try {
    $mutexes = DB::table('auth_mutexes')->whereIn('name', ['login', 'news-slug', 'proveedores', 'schema-ready'])->count();
    $ok = $mutexes === 4 && $ok;
    echo ($mutexes === 4 ? 'OK    ' : 'FALTA ').'mutexes y marca de esquema completo'.PHP_EOL;
    if (! DB::table('portal_site_content')->where('id', 1)->exists()) {
        echo 'PENDIENTE contenido institucional id=1 para habilitar su API.'.PHP_EOL;
    }
} catch (Throwable) {
    echo 'FALTA comprobación de mutexes o contenido institucional'.PHP_EOL;
    $ok = false;
}
exit($ok ? 0 : 1);
