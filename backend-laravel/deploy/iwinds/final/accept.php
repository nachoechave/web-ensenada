<?php

declare(strict_types=1);

const ORIGIN = 'https://prueba.ensenada.gov.ar';

function check(bool $passed, string $message): void
{
    if (! $passed) {
        throw new RuntimeException($message);
    }
}

function prompt(string $label, bool $hidden = false): string
{
    fwrite(STDERR, $label.': ');
    if ($hidden) {
        check(system('stty -echo', $status) !== false && $status === 0, 'La terminal no puede ocultar la entrada.');
    }
    try {
        $line = fgets(STDIN);
    } finally {
        if ($hidden) {
            system('stty echo');
            fwrite(STDERR, PHP_EOL);
        }
    }
    check(is_string($line) && rtrim($line, "\r\n") !== '', 'Entrada obligatoria.');

    return rtrim($line, "\r\n");
}

final class StagingHttp
{
    private CurlHandle $curl;

    public function __construct(private string $basic, private string $password)
    {
        $this->curl = curl_init();
        curl_setopt($this->curl, CURLOPT_COOKIEFILE, '');
    }

    public function request(
        string $method,
        string $path,
        int $expected,
        string|array|null $data = null,
        bool $basic = true,
        bool $crossSite = false,
    ): array {
        $headers = [];
        $outgoing = ['Accept: application/json', 'Origin: '.($crossSite ? 'https://outside.invalid' : ORIGIN),
            'Sec-Fetch-Site: '.($crossSite ? 'cross-site' : 'same-origin')];
        if (is_string($data)) {
            $outgoing[] = 'Content-Type: application/json';
        }
        curl_setopt_array($this->curl, [
            CURLOPT_URL => ORIGIN.$path,
            CURLOPT_HTTPAUTH => $basic ? CURLAUTH_BASIC : CURLAUTH_NONE,
            CURLOPT_USERNAME => $basic ? $this->basic : '',
            CURLOPT_PASSWORD => $basic ? $this->password : '',
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_ENCODING => '',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $outgoing,
            CURLOPT_HEADERFUNCTION => static function (CurlHandle $curl, string $line) use (&$headers): int {
                if (str_starts_with($line, 'HTTP/')) {
                    $headers = [];
                } elseif (str_contains($line, ':')) {
                    [$name, $value] = explode(':', $line, 2);
                    $headers[strtolower($name)][] = trim($value);
                }

                return strlen($line);
            },
        ]);
        if ($data === null) {
            curl_setopt($this->curl, CURLOPT_HTTPGET, true);
        } else {
            curl_setopt($this->curl, CURLOPT_POSTFIELDS, $data);
        }
        curl_setopt($this->curl, CURLOPT_NOBODY, $method === 'HEAD');
        curl_setopt($this->curl, CURLOPT_CUSTOMREQUEST, $method);
        $body = curl_exec($this->curl);
        $status = (int) curl_getinfo($this->curl, CURLINFO_RESPONSE_CODE);
        check(is_string($body) && $status === $expected, "$method $path devolvió $status, se esperaba $expected.");

        return [$body, $headers];
    }
}

function payload(array $response): stdClass
{
    $value = json_decode($response[0]);
    check($value instanceof stdClass, 'La respuesta esperada como objeto JSON tiene otro formato.');

    return $value;
}

function encode(object|array $value): string
{
    return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

function webLimits(StagingHttp $http, string $probe): void
{
    $http->request('GET', $probe, 401, basic: false);
    $data = payload($http->request('GET', $probe, 200));
    foreach (['upload_max_filesize' => 32, 'post_max_size' => 40, 'memory_limit' => 256] as $key => $mib) {
        $value = $data->{$key} ?? null;
        check(is_string($value) && @ini_parse_quantity($value) >= $mib * 1024 * 1024, "$key de PHP web debe ser al menos {$mib}M.");
    }
    check(($data->file_uploads ?? false) === true, 'PHP web tiene deshabilitada la carga de archivos.');
    echo "PHP web cumple 32M de subida, 40M de POST y 256M de memoria.\n";
}

function fixtures(string $work): array
{
    $jpeg = "$work/alta-resolucion.jpg";
    $png = "$work/transparencia.png";
    $webp = "$work/foto.webp";
    $invalid = "$work/invalida.jpg";
    $large = imagecreatetruecolor(6000, 4000);
    check($large instanceof GdImage, 'No se pudo generar el JPEG sintético.');
    imagefill($large, 0, 0, imagecolorallocate($large, 30, 90, 175));
    imagefilledrectangle($large, 0, 0, 3000, 2000, imagecolorallocate($large, 220, 170, 30));
    check(imagejpeg($large, $jpeg, 88), 'No se pudo guardar el JPEG sintético.');
    unset($large);
    $file = fopen($jpeg, 'r+b');
    check(is_resource($file), 'No se pudo abrir la prueba de 30 MiB.');
    try {
        check(ftruncate($file, 30 * 1024 * 1024), 'No se pudo generar la prueba de 30 MiB.');
    } finally {
        fclose($file);
    }
    $alpha = imagecreatetruecolor(100, 80);
    imagealphablending($alpha, false);
    imagesavealpha($alpha, true);
    imagefill($alpha, 0, 0, imagecolorallocatealpha($alpha, 20, 60, 120, 63));
    check(imagepng($alpha, $png), 'No se pudo preparar el PNG sintético.');
    // Synthetic 100 × 80 WebP with alpha. CLI GD may only support JPEG and PNG.
    $webpBytes = base64_decode('UklGRnoAAABXRUJQVlA4WAoAAAAQAAAAYwAATwAAQUxQSAoAAAABB9DAiAhERP8DVlA4IEoAAADQBACdASpkAFAAPmEwlkikIyIhIOgAgAwJaQB2AAAO7Kr5e4095N9OnTp06dOjgAD+9muf//iHOILajsU//rUyMfPuAAAAAAAAAA==', true);
    check(file_put_contents($webp, $webpBytes) === strlen($webpBytes), 'No se pudo preparar el WebP sintético.');
    check(file_put_contents($invalid, 'archivo sintetico incompatible') > 0, 'No se pudo crear el archivo inválido.');

    return [$jpeg, $png, $webp, $invalid];
}

function transparency(string $bytes, string $mime, string $work): int
{
    if ($mime === 'image/webp' && (imagetypes() & IMG_WEBP) === 0) {
        $temporary = tempnam($work, '.alpha-');
        check(is_string($temporary), 'No se pudo preparar la lectura de transparencia WebP.');
        try {
            check(file_put_contents($temporary, $bytes) === strlen($bytes), 'No se pudo escribir la muestra de transparencia.');
            $process = proc_open(
                ['/usr/bin/prlimit', '--as=536870912', '--cpu=5', '--', '/usr/bin/convert', 'webp:'.$temporary, '-define', 'png:color-type=6', 'png:-'],
                [1 => ['pipe', 'w'], 2 => ['file', '/dev/null', 'w']],
                $pipes,
                $work,
                ['MAGICK_CONFIGURE_PATH' => '/home/ensenada/backend-laravel/deploy/iwinds', 'LC_ALL' => 'C'],
            );
            check(is_resource($process), 'ImageMagick no pudo leer la muestra WebP.');
            $bytes = stream_get_contents($pipes[1]);
            fclose($pipes[1]);
            check(proc_close($process) === 0 && is_string($bytes), 'El servidor no pudo verificar la transparencia WebP.');
        } finally {
            unlink($temporary);
        }
    }
    $image = @imagecreatefromstring($bytes);
    check($image instanceof GdImage, 'La muestra de transparencia no pudo decodificarse.');

    return imagecolorsforindex($image, imagecolorat($image, 0, 0))['alpha'];
}

function upload(StagingHttp $http, string $module, string $file, array $dimensions, bool $alpha = false): string
{
    $inputMime = ['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];
    $part = ['archivo' => new CURLFile($file, $inputMime[pathinfo($file, PATHINFO_EXTENSION)], basename($file))];
    $dto = payload($http->request('POST', "/api/admin/archivos/$module", 200, $part));
    $url = $dto->url ?? '';
    check(is_string($url) && preg_match("~\A/uploads/$module/([0-9a-f-]{36})\.(jpg|png|webp)\z~D", $url, $match) === 1,
        'La URL de la imagen optimizada no coincide con el contrato.');
    [$bytes, $headers] = $http->request('GET', $url, 200);
    [$empty, $head] = $http->request('HEAD', $url, 200);
    $mime = $inputMime[$match[2]];
    $shape = @getimagesizefromstring($bytes);
    check(($headers['content-type'][0] ?? '') === $mime && ($head['content-type'][0] ?? '') === $mime
        && ($dto->contentType ?? '') === $mime && ($shape['mime'] ?? '') === $mime
        && [(int) ($shape[0] ?? 0), (int) ($shape[1] ?? 0)] === $dimensions && $empty === ''
        && ($dto->nombreOriginal ?? '') === basename($url) && ($dto->size ?? 0) === strlen($bytes)
        && (int) ($head['content-length'][0] ?? 0) === strlen($bytes)
        && strlen($bytes) <= 5 * 1024 * 1024 && (max($dimensions) < 1920 || strlen($bytes) < filesize($file)),
        'La imagen no cumple dimensiones, MIME, tamaño o descarga GET/HEAD.');
    if ($alpha) {
        check(transparency($bytes, $mime, dirname($file)) >= 45, 'La imagen optimizada perdió la transparencia.');
    }
    printf("%s %s: %s %d × %d, %d bytes.\n", $module, basename($file), $mime, ...[...$dimensions, strlen($bytes)]);

    return $url;
}

function exercise(StagingHttp $http, string $work): void
{
    $http->request('GET', '/api/auth/me', 401, basic: false);
    $http->request('GET', '/api/auth/me', 401);
    $http->request('POST', '/api/auth/login', 403, '{}', crossSite: true);
    $email = prompt('Correo del SUPER_ADMIN de staging');
    $password = prompt('Contraseña de Laravel', true);
    $login = $http->request('POST', '/api/auth/login', 200, encode(compact('email', 'password')));
    unset($password);
    $cookie = implode("\n", $login[1]['set-cookie'] ?? []);
    $identity = payload($http->request('GET', '/api/auth/me', 200));
    check(in_array('SUPER_ADMIN', $identity->roles ?? [], true) && (payload($login)->token ?? null) === ''
        && preg_match('/\AWEB_ENSENADA_SESSION=.+/m', $cookie) === 1
        && preg_match('/;\s*Secure\b/i', $cookie) === 1 && preg_match('/;\s*HttpOnly\b/i', $cookie) === 1
        && preg_match('/;\s*SameSite=Strict\b/i', $cookie) === 1 && preg_match('~;\s*Path=/api(?:;|$)~i', $cookie) === 1,
        'La sesión de Angular no cumple el contrato de cookies.');

    $siteBefore = null;
    $siteExpected = null;
    $newsId = null;
    try {
        $http->request('GET', '/api/admin/usuarios', 200);
        [$jpeg, $png, $webp, $invalid] = fixtures($work);
        $bad = ['archivo' => new CURLFile($invalid, 'image/jpeg', 'invalida.jpg')];
        $http->request('POST', '/api/admin/archivos/noticias', 403, $bad, crossSite: true);
        $http->request('POST', '/api/admin/archivos/noticias', 400, $bad);
        $http->request('POST', '/api/admin/archivos/sitio', 400, $bad);
        $cover = upload($http, 'noticias', $jpeg, [1920, 1280]);
        $gallery = upload($http, 'noticias', $webp, [100, 80], true);
        $hero = upload($http, 'sitio', $png, [100, 80], true);
        $institutionalGallery = upload($http, 'sitio', $webp, [100, 80], true);

        $news = [
            'titulo' => 'Aceptación sintética '.bin2hex(random_bytes(8)), 'bajada' => 'Prueba HTTPS',
            'contenido' => 'Registro sintético para la prueba de publicación.', 'imagen' => $cover,
            'imagenes' => [$cover, $gallery], 'categoria' => 'Pruebas', 'fechaPublicacion' => date('Y-m-d'),
            'estado' => 'BORRADOR', 'destacada' => false,
        ];
        $draft = payload($http->request('POST', '/api/admin/noticias', 201, encode($news)));
        $newsId = $draft->id ?? null;
        $slug = $draft->slug ?? '';
        check(is_int($newsId) && $newsId > 0 && is_string($slug) && preg_match('/\A[a-z0-9-]+\z/D', $slug),
            'Falta el ID o slug de la noticia sintética.');
        file_put_contents("$work/noticia-id", "$newsId\n", LOCK_EX);
        $http->request('GET', "/api/noticias/$slug", 404);
        $news['estado'] = 'PUBLICADA';
        $http->request('PUT', "/api/admin/noticias/$newsId", 200, encode($news));
        $public = payload($http->request('GET', "/api/noticias/$slug", 200));
        check(($public->imagen ?? '') === $cover && ($public->imagenes ?? []) === [$cover, $gallery],
            'No se conservaron la portada y la galería en el detalle público.');

        [$siteBefore] = $http->request('GET', '/api/admin/site-content', 200);
        $site = payload([$siteBefore]);
        check(($site->hero ?? null) instanceof stdClass && ($site->intendencia ?? null) instanceof stdClass,
            'El documento sintético no contiene hero e intendencia.');
        check(file_put_contents("$work/site-original.json", $siteBefore, LOCK_EX) === strlen($siteBefore),
            'No se pudo conservar el JSON institucional previo.');
        $site->hero->imagen = $hero;
        $site->hero->imagenes = [$hero, $institutionalGallery];
        $site->intendencia->imagen = $institutionalGallery;
        $site->intendencia->galeria = [$institutionalGallery, $hero];
        $siteExpected = encode($site);
        $http->request('PUT', '/api/admin/site-content', 200, $siteExpected);
        [$saved] = $http->request('GET', '/api/site-content', 200);
        check($saved === $siteExpected, 'Carrusel y galería institucional no se conservaron en la respuesta pública.');
        echo "Administración, portada, carrusel, galería y publicación verificados.\n";
    } finally {
        $cleanupFailed = false;
        if ($siteExpected !== null) {
            try {
                [$current] = $http->request('GET', '/api/admin/site-content', 200);
                if ($current === $siteExpected) {
                    $http->request('PUT', '/api/admin/site-content', 200, $siteBefore);
                    [$restored] = $http->request('GET', '/api/site-content', 200);
                    check($restored === trim($siteBefore, "\x00..\x20"), 'El contenido original no quedó restaurado.');
                } else {
                    check($current === $siteBefore, 'El contenido cambió durante la prueba. Se conserva la edición simultánea.');
                }
            } catch (RuntimeException $error) {
                fwrite(STDERR, $error->getMessage().PHP_EOL);
                $cleanupFailed = true;
            }
        }
        if (is_int($newsId) && $newsId > 0) {
            try {
                $http->request('DELETE', "/api/admin/noticias/$newsId", 204);
                if (isset($slug) && preg_match('/\A[a-z0-9-]+\z/D', $slug)) {
                    $http->request('GET', "/api/noticias/$slug", 404);
                }
            } catch (RuntimeException $error) {
                fwrite(STDERR, $error->getMessage().PHP_EOL);
                $cleanupFailed = true;
            }
        }
        try {
            $http->request('POST', '/api/auth/logout', 204);
            $http->request('GET', '/api/auth/me', 401);
        } catch (RuntimeException $error) {
            fwrite(STDERR, $error->getMessage().PHP_EOL);
            $cleanupFailed = true;
        }
        check(! $cleanupFailed, 'La limpieza requiere revisión antes de abrir otra ventana.');
    }
}

try {
    check(PHP_SAPI === 'cli' && $argc === 4 && stream_isatty(STDIN), 'Ejecutá la aceptación en una terminal privada.');
    [, $mode, $probe, $work] = $argv;
    check(in_array($mode, ['closed', 'exercise'], true) && preg_match('/\Aiwinds-limits-[a-f0-9]{32}\.php\z/D', $probe)
        && preg_match('~\A/home/ensenada/iwinds-final-state/accept\.[a-zA-Z0-9]{8}\z~D', $work)
        && realpath($work) === $work, 'Argumentos de aceptación inválidos.');
    umask(0077);
    $http = new StagingHttp(prompt('Usuario Basic de cPanel'), prompt('Contraseña Basic de cPanel', true));
    if ($mode === 'closed') {
        $http->request('GET', '/', 401, basic: false);
        [$index] = $http->request('GET', '/', 200);
        check(hash_equals(hash_file('sha256', __DIR__.'/photos/angular/index.html'), hash('sha256', $index)),
            'HTTPS no devuelve el índice Angular instalado.');
        foreach (glob(__DIR__.'/photos/angular/*.{js,css}', GLOB_BRACE) ?: [] as $bundle) {
            [$body, $headers] = $http->request('GET', '/'.basename($bundle), 200);
            $mime = explode(';', $headers['content-type'][0] ?? '', 2)[0];
            $types = str_ends_with($bundle, '.css') ? ['text/css'] : ['text/javascript', 'application/javascript'];
            check(in_array($mime, $types, true) && hash_equals(hash_file('sha256', $bundle), hash('sha256', $body)),
                'HTTPS no entrega el bundle Angular esperado.');
        }
        $http->request('GET', '/api/site-content', 503);
        webLimits($http, '/'.$probe);
    } else {
        exercise($http, $work);
    }
} catch (Throwable $error) {
    fwrite(STDERR, 'BLOQUEADO: '.($error instanceof RuntimeException ? $error->getMessage() : 'falló la prueba local de aceptación.').PHP_EOL);
    exit(1);
}
