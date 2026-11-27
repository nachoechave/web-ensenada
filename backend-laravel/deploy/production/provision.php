<?php

declare(strict_types=1);
use App\Content\SiteContentDocument;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}
umask(0077);
require dirname(__DIR__, 2).'/vendor/autoload.php';
try {
    $op = $argv[1] ?? '';
    // getopt stops at a positional operation; use an explicit parser for the remaining options.
    $options = [];
    foreach (array_slice($argv, 2) as $arg) {
        if (! preg_match('/\A--(defaults-file|file|email|name)=(.+)\z/D', $arg, $parts) || isset($options[$parts[1]])) {
            throw new RuntimeException('Argumentos inválidos.');
        }
        $options[$parts[1]] = $parts[2];
    }
    $file = $options['defaults-file'] ?? '';
    if (is_link($file) || ! is_file($file) || (fileperms($file) & 0077) !== 0) {
        throw new RuntimeException('Archivo de conexión privado 0600 requerido.');
    }
    $cfg = parse_ini_file($file, true, INI_SCANNER_RAW)['client'] ?? [];
    if (($cfg['database'] ?? '') !== 'ensenada_web_prod' || ! in_array($cfg['host'] ?? '', ['127.0.0.1', 'localhost'], true)
        || ($cfg['user'] ?? '') === 'ensenada_web_prod_app' || empty($cfg['password'])) {
        throw new RuntimeException('Cuenta de aprovisionamiento independiente requerida.');
    }
    $pdo = new PDO('mysql:host='.$cfg['host'].';dbname=ensenada_web_prod;charset=utf8mb4', $cfg['user'], $cfg['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $pdo->beginTransaction();
    $pdo->query("SELECT name FROM auth_mutexes WHERE name='schema-ready' FOR UPDATE")->fetchColumn() === 'schema-ready' || throw new RuntimeException('Esquema incompleto.');
    if ($op === 'content') {
        $path = $options['file'] ?? '';
        if (is_link($path) || ! is_file($path)) {
            throw new RuntimeException('JSON aprobado requerido.');
        }
        $json = file_get_contents($path);
        if (! SiteContentDocument::valid($json) || strlen($json) > 65535) {
            throw new RuntimeException('Contenido inválido.');
        }
        if ($pdo->query('SELECT COUNT(*) FROM portal_site_content')->fetchColumn() != 0) {
            throw new RuntimeException('Ya existe contenido. No se sobrescribe.');
        }
        $pdo->prepare('INSERT INTO portal_site_content(id,contenido_json,updated_at) VALUES(1,?,CURRENT_TIMESTAMP)')->execute([$json]);
    } elseif ($op === 'admin') {
        $email = strtolower(trim($options['email'] ?? ''));
        $name = trim($options['name'] ?? '');
        if (! filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255 || $name === '' || mb_strlen($name) > 255) {
            throw new RuntimeException('Nombre o email inválido.');
        }
        if ($pdo->query("SELECT COUNT(*) FROM usuario_roles WHERE rol='SUPER_ADMIN'")->fetchColumn() != 0) {
            throw new RuntimeException('Ya existe SUPER_ADMIN. No se modifica.');
        }
        $tty = stream_isatty(STDIN);
        if ($tty) {
            fwrite(STDERR, 'Contraseña nueva (mínimo 16 caracteres): ');
            exec('stty -echo');
        }
        try {
            $password = rtrim((string) fgets(STDIN), "\r\n");
        } finally {
            if ($tty) {
                exec('stty echo');
                fwrite(STDERR, "\n");
            }
        }
        if (strlen($password) < 16 || strlen($password) > 72 || str_contains($password, "\0")) {
            throw new RuntimeException('Contraseña de 16 a 72 bytes requerida.');
        }
        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        unset($password);
        $pdo->prepare("INSERT INTO usuarios(nombre,email,password,activo,rol,token_version) VALUES(?,?,?,1,'SUPER_ADMIN',0)")->execute([$name, $email, $hash]);
        $pdo->prepare("INSERT INTO usuario_roles(usuario_id,rol) VALUES(?,'SUPER_ADMIN')")->execute([$pdo->lastInsertId()]);
    } else {
        throw new RuntimeException('Operación content o admin requerida.');
    }
    $pdo->commit();
    echo "Aprovisionamiento confirmado. Sin modificar registros anteriores.\n";
} catch (Throwable $error) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, "Aprovisionamiento rechazado; revisar argumentos, permisos y registros existentes.\n");
    exit(1);
}
