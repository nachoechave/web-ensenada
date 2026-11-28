<?php

namespace App\Support;

use App\Content\SiteContentDocument;
use Dotenv\Dotenv;
use PDO;
use RuntimeException;

final class SharedDatabaseAudit
{
    public const TABLES = ['usuarios', 'usuario_roles', 'noticias', 'noticia_imagenes', 'auditoria_noticias', 'portal_site_content',
        'publicaciones_hacienda', 'archivos_hacienda', 'documentos_proveedores', 'auth_sessions', 'auth_login_windows', 'auth_mutexes'];

    public const MODULES = ['noticias', 'sitio', 'hacienda', 'proveedores'];

    public static function env(string $root): array
    {
        $path = $root.'/.env';
        if (realpath($root) !== $root || is_link($path) || ! is_file($path)) {
            throw new RuntimeException('Configuración privada inexistente.');
        }
        $env = Dotenv::parse(file_get_contents($path));
        if (! isset($env['APP_KEY'],$env['AUTH_JWT_SECRET'])) {
            throw new RuntimeException('Claves de aplicación existentes requeridas.');
        }

        return $env;
    }

    public static function connect(array $config): PDO
    {
        $host = $config['DB_HOST'] ?? $config['host'] ?? '';
        $user = $config['DB_USERNAME'] ?? $config['user'] ?? '';
        $password = $config['DB_PASSWORD'] ?? $config['password'] ?? '';
        $database = $config['DB_DATABASE'] ?? $config['database'] ?? '';
        if (! in_array($host, ['localhost', '127.0.0.1'], true) || $database !== 'ensenada_web'
            || ! in_array($user, ['ensenada_web_app', 'ensenada_web_schema'], true) || $password === ''
            || (int) ($config['DB_PORT'] ?? $config['port'] ?? 3306) !== 3306) {
            throw new RuntimeException('Conexión fuera de la única base autorizada.');
        }
        $pdo = new PDO('mysql:host='.$host.';port=3306;dbname=ensenada_web;charset=utf8mb4', $user, $password,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]);
        $pdo->exec("SET SESSION time_zone='+00:00'");
        $account = $pdo->query('SELECT CURRENT_USER()')->fetchColumn();
        if (! in_array($account, [$user.'@localhost', $user.'@127.0.0.1'], true)) {
            throw new RuntimeException('Cuenta local efectiva inesperada.');
        }

        return $pdo;
    }

    public static function schemaConfig(string $file): array
    {
        if (is_link($file) || ! is_file($file) || (fileperms($file) & 0777) !== 0600) {
            throw new RuntimeException('Archivo cliente privado 0600 requerido.');
        }
        $raw = file_get_contents($file);
        $all = parse_ini_string($raw, true, INI_SCANNER_RAW);
        $client = $all['client'] ?? [];
        if (! is_array($all) || array_keys($all) !== ['client'] || preg_match('/^\s*!/m', $raw)
            || array_diff(array_keys($client), ['host', 'port', 'user', 'password', 'database']) !== []
            || ($client['user'] ?? '') !== 'ensenada_web_schema' || ($client['database'] ?? '') !== 'ensenada_web') {
            throw new RuntimeException('Se requiere ensenada_web_schema y la base existente en [client], sin inclusiones.');
        }

        return $client;
    }

    public static function schema(PDO $pdo): array
    {
        $version = $pdo->query('SELECT VERSION()')->fetchColumn();
        if (! preg_match('/^(?:5[.]5[.]5-)?10[.]11[.][0-9]+-MariaDB/i', $version)) {
            throw new RuntimeException('MariaDB 10.11 requerida.');
        }
        $integrity = $pdo->query('SELECT @@foreign_key_checks,@@unique_checks,@@check_constraint_checks')->fetch(PDO::FETCH_NUM);
        if (array_map('intval', $integrity) !== [1, 1, 1]) {
            throw new RuntimeException('Integridad de la sesión deshabilitada.');
        }
        $tables = $pdo->query('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() ORDER BY TABLE_NAME')->fetchAll(PDO::FETCH_COLUMN);
        $expected = self::TABLES;
        sort($expected);
        sort($tables);
        if ($tables !== $expected) {
            throw new RuntimeException('Las doce tablas existentes no coinciden. No se ejecuta DDL.');
        }
        $objects = [];
        foreach ($tables as $table) {
            $sql = $pdo->query('SHOW CREATE TABLE `'.$table.'`')->fetch(PDO::FETCH_NUM)[1];
            $objects[$table] = self::normalizeDdl($sql);
        }
        $triggers = $pdo->query('SELECT TRIGGER_NAME FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE() ORDER BY TRIGGER_NAME')->fetchAll(PDO::FETCH_COLUMN);
        if ($triggers !== ['revoke_auth_sessions']) {
            throw new RuntimeException('Trigger ausente, no visible o inesperado.');
        }
        $trigger = $pdo->query('SHOW CREATE TRIGGER revoke_auth_sessions')->fetch(PDO::FETCH_ASSOC);
        $objects['trigger:revoke_auth_sessions'] = self::normalizeDdl($trigger['SQL Original Statement']);
        foreach (['ROUTINES' => 'ROUTINE_SCHEMA', 'EVENTS' => 'EVENT_SCHEMA'] as $object => $column) {
            if ((int) $pdo->query('SELECT COUNT(*) FROM information_schema.'.$object.' WHERE '.$column.'=DATABASE()')->fetchColumn() !== 0) {
                throw new RuntimeException('Rutinas/eventos no contemplados.');
            }
        }
        foreach ([['usuario_roles', 'usuario_id', 'usuarios'], ['noticia_imagenes', 'noticia_id', 'noticias'],
            ['archivos_hacienda', 'publicacion_hacienda_id', 'publicaciones_hacienda'], ['auth_sessions', 'usuario_id', 'usuarios']] as [$child,$key,$parent]) {
            if ((int) $pdo->query("SELECT COUNT(*) FROM `$child` c LEFT JOIN `$parent` p ON p.id=c.`$key` WHERE p.id IS NULL")->fetchColumn() !== 0) {
                throw new RuntimeException('Relaciones huérfanas; requiere revisión, sin reparación automática.');
            }
        }

        return $objects;
    }

    public static function normalizeDdl(string $sql): string
    {
        $sql = preg_replace('/ AUTO_INCREMENT=[0-9]+/', '', $sql);
        $sql = preg_replace('/DEFINER=`[^`]+`@`[^`]+`/', 'DEFINER=CURRENT_USER', $sql);

        return preg_replace('/\s+/', ' ', trim($sql));
    }

    public static function files(string $base): array
    {
        $files = [];
        foreach (self::MODULES as $module) {
            $dir = $base.'/'.$module;
            if (realpath($dir) !== $dir || ! is_dir($dir) || (fileperms($dir) & 0077) !== 0) {
                throw new RuntimeException('Almacenamiento privado no canónico.');
            }
            foreach (new \DirectoryIterator($dir) as $file) {
                if ($file->isDot()) {
                    continue;
                }
                $name = $file->getFilename();
                $stat = lstat($file->getPathname());
                if (! $file->isFile() || $file->isLink() || $stat['nlink'] !== 1
                    || ! preg_match('/\A[A-Za-z0-9_-][A-Za-z0-9._-]{0,245}\.(?:jpg|jpeg|png|webp|pdf|doc|xls|xlsx)\z/iD', $name)) {
                    throw new RuntimeException('Archivo parcial, enlace o nombre no permitido; revisar sin borrar.');
                }
                $hash = hash_file('sha256', $file->getPathname());
                clearstatcache(true, $file->getPathname());
                $after = lstat($file->getPathname());
                foreach (['ino', 'dev', 'size', 'mtime', 'ctime', 'nlink'] as $field) {
                    if ($stat[$field] !== $after[$field]) {
                        throw new RuntimeException('Archivo cambió durante la auditoría.');
                    }
                }
                $files[$module.'/'.$name] = ['sha256' => $hash, 'size' => $stat['size']];
            }
        }
        ksort($files);

        return $files;
    }

    public static function data(PDO $pdo, array $files, ?string $auditKey = null): array
    {
        $fingerprints = [];
        $review = [];
        $public = [];
        $references = [];
        $relations = [];
        $reference = static function (mixed $value, bool $visible) use (&$references, &$public): void {
            if (! is_string($value)) {
                return;
            }
            $path = parse_url($value, PHP_URL_PATH);
            if (! is_string($path) || ! preg_match('~\A/uploads/(noticias|sitio|hacienda|proveedores)/([^/]+)\z~D', $path, $parts)) {
                return;
            }
            $key = $parts[1].'/'.$parts[2];
            $references[$key] = true;
            if ($visible) {
                $public['file:'.$key] = true;
            }
            if ($visible && preg_match('~^https?://prueba\.ensenada\.gov\.ar~i', $value)) {
                throw new RuntimeException('Referencia pública absoluta a staging: corregir con autorización antes del corte.');
            }
        };
        $pdo->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY');
        try {
            foreach (self::TABLES as $table) {
                if (in_array($table, ['auth_sessions', 'auth_login_windows'], true)) {
                    continue;
                }
                $rows = $pdo->query('SELECT * FROM `'.$table.'`')->fetchAll(PDO::FETCH_ASSOC);
                if (in_array($table, ['usuario_roles', 'noticia_imagenes', 'archivos_hacienda'], true)) {
                    $relations[$table] = $rows;
                }
                $tableRows = [];
                foreach ($rows as $row) {
                    $id = isset($row['id']) ? (string) $row['id'] : implode(':', array_map('strval', array_intersect_key($row, array_flip(['usuario_id', 'rol', 'noticia_id', 'orden', 'name']))));
                    $key = $table.':'.$id;
                    if ($table === 'usuarios') {
                        unset($row['token_version']);
                    }
                    $normalized = array_map(static fn ($v) => $v === null ? null : (string) $v, $row);
                    ksort($normalized);
                    $fingerprint = hash_hmac('sha256', json_encode($normalized, JSON_THROW_ON_ERROR), $auditKey ?? SharedDatabaseLease::key());
                    $tableRows[$key] = $fingerprint;
                    $title = $row['titulo'] ?? $row['nombre'] ?? $row['email'] ?? $key;
                    $hint = preg_match('/sint[eé]tic|example[.]|@[^ ]*[.]test\b|\bfixture\b/iu', json_encode(array_diff_key($row, ['password' => true]), JSON_UNESCAPED_UNICODE)) === 1;
                    if (in_array($table, ['usuarios', 'noticias', 'portal_site_content', 'publicaciones_hacienda', 'documentos_proveedores'], true)) {
                        $preview = $row;
                        unset($preview['password']);
                        $review[$key] = ['fingerprint' => $fingerprint, 'title' => $title, 'synthetic_hint' => $hint, 'record' => $preview,
                            'classification' => 'UNREVIEWED', 'reason' => ''];
                    }
                    $visible = match ($table) {
                        'usuarios' => (string) $row['activo'] === '1',
                        'noticias','publicaciones_hacienda' => $row['estado'] === 'PUBLICADA',
                        'portal_site_content' => true,'documentos_proveedores' => (string) $row['activo'] === '1',default => false
                    };
                    if ($visible) {
                        $public[$key] = true;
                    }
                    if ($table === 'noticias') {
                        $reference($row['imagen'], $visible);
                    }
                    if ($table === 'noticia_imagenes') {
                        $s = $pdo->prepare('SELECT estado FROM noticias WHERE id=?');
                        $s->execute([$row['noticia_id']]);
                        $reference($row['url'], $s->fetchColumn() === 'PUBLICADA');
                    }
                    if ($table === 'portal_site_content') {
                        if (! SiteContentDocument::valid($row['contenido_json'])) {
                            throw new RuntimeException('JSON institucional incompatible.');
                        }
                        $content = json_decode($row['contenido_json'], true);
                        array_walk_recursive($content, static fn ($v) => $reference($v, true));
                    }
                    if ($table === 'archivos_hacienda') {
                        $s = $pdo->prepare('SELECT estado FROM publicaciones_hacienda WHERE id=?');
                        $s->execute([$row['publicacion_hacienda_id']]);
                        $reference('/uploads/hacienda/'.$row['nombre_almacenado'], $s->fetchColumn() === 'PUBLICADA');
                    }
                    if ($table === 'documentos_proveedores') {
                        $reference('/uploads/proveedores/'.$row['nombre_almacenado'], $visible);
                    }
                }
                ksort($tableRows);
                $fingerprints[$table] = $tableRows;
            }
            foreach ($references as $key => $unused) {
                if (! isset($files[$key])) {
                    throw new RuntimeException('Documento o imagen referenciado ausente: '.$key);
                }
            }
            foreach ($files as $key => $item) {
                $review['file:'.$key] = ['fingerprint' => $item['sha256'], 'title' => $key, 'size' => $item['size'],
                    'synthetic_hint' => false, 'classification' => 'UNREVIEWED', 'reason' => ''];
            }
        } finally {
            $pdo->rollBack();
        }
        ksort($review);
        ksort($public);
        ksort($fingerprints);

        return ['data_digest' => hash('sha256', json_encode($fingerprints, JSON_THROW_ON_ERROR)), 'fingerprints' => $fingerprints, 'review' => $review, 'relations' => $relations, 'public' => array_keys($public)];
    }

    public static function snapshot(PDO $pdo, string $storage): array
    {
        $schema = self::schema($pdo);
        $reference = json_decode(file_get_contents(dirname(__DIR__, 2).'/deploy/production/schema-reference.json'), true, flags: JSON_THROW_ON_ERROR);
        if ($schema !== $reference) {
            throw new RuntimeException('El esquema difiere de Flyway/MariaDB verificado. No se altera automáticamente.');
        }
        $files = self::files($storage);
        $data = self::data($pdo, $files);

        return ['schema_digest' => hash('sha256', json_encode($schema, JSON_THROW_ON_ERROR)), ...$data, 'files' => $files,
            'files_digest' => hash('sha256', json_encode($files, JSON_THROW_ON_ERROR))];
    }

    public static function approve(array $snapshot, array $review): void
    {
        if (array_keys($snapshot['review']) !== array_keys($review)) {
            throw new RuntimeException('Revisión incompleta o con registros ajenos.');
        }
        foreach ($snapshot['review'] as $key => $item) {
            $decision = $review[$key] ?? [];
            if (($decision['fingerprint'] ?? '') !== $item['fingerprint'] || ! in_array($decision['classification'] ?? '', ['APPROVED', 'SYNTHETIC', 'PRIVATE-HISTORICAL'], true)
                || trim($decision['reason'] ?? '') === '') {
                throw new RuntimeException('Clasificación y justificación manual pendientes: '.$key);
            }
            if (in_array($key, $snapshot['public'], true) && $decision['classification'] !== 'APPROVED') {
                throw new RuntimeException('Contenido público o cuenta activa sin aprobación: '.$key);
            }
        }
    }

    public static function fullDigest(PDO $pdo, string $key): string
    {
        $orders = ['usuario_roles' => 'usuario_id,rol', 'noticia_imagenes' => 'noticia_id,orden', 'auth_sessions' => 'token_hash', 'auth_login_windows' => 'ip_hash', 'auth_mutexes' => 'name'];
        $hash = hash_init('sha256', HASH_HMAC, $key);
        $pdo->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY');
        try {
            foreach (self::TABLES as $table) {
                hash_update($hash, $table."\n");
                $query = $pdo->query('SELECT * FROM `'.$table.'` ORDER BY '.($orders[$table] ?? 'id'));
                while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
                    $row = array_map(static fn ($v) => $v === null ? null : (string) $v, $row);
                    ksort($row);
                    hash_update($hash, json_encode($row, JSON_THROW_ON_ERROR)."\n");
                }
            }
        } finally {
            $pdo->rollBack();
        }

        return hash_final($hash);
    }
}
