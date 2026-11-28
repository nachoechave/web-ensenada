<?php

require '/home/ensenada/backend-laravel-produccion/vendor/autoload.php';
require '/source/tests/Fixtures/PhotoFixtures.php';
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use Symfony\Component\Process\Process;
use Tests\Fixtures\PhotoFixtures;

if (getenv('ENSENADA_PRODUCTION_TEST_DISPOSABLE') !== 'true') {
    exit(1);
}
$client = new Client(['base_uri' => 'https://www.ensenada.gov.ar', 'verify' => false, 'http_errors' => false, 'cookies' => new CookieJar,
    'headers' => ['Origin' => 'https://www.ensenada.gov.ar', 'Sec-Fetch-Site' => 'same-origin']]);
$count = 0;
$assert = static function (bool $ok, string $message) use (&$count): void {
    $count++;
    if (! $ok) {
        throw new RuntimeException($message);
    }
};
$r = $client->post('/api/auth/login', ['json' => ['email' => 'prensa@example.test', 'password' => 'Synthetic-Production-Test-123!']]);
$assert($r->getStatusCode() === 200, 'login');
$upload = static fn (string $bytes, string $name = 'original.jpg') => $client->post('/api/admin/archivos/noticias', ['multipart' => [['name' => 'archivo', 'contents' => $bytes, 'filename' => $name]]]);
$im = imagecreatetruecolor(4000, 2250);
ob_start();
imagejpeg($im, null, 90);
$jpeg = ob_get_clean();
imagedestroy($im);
$r = $upload(PhotoFixtures::orientationSix($jpeg));
$assert($r->getStatusCode() === 200, 'EXIF orientación');
$oriented = json_decode((string) $r->getBody(), true)['url'];
$bytes = (string) $client->get($oriented)->getBody();
$shape = getimagesizefromstring($bytes);
$assert([$shape[0], $shape[1]] === [1080, 1920], 'rotación y tamaño');
$assert(! str_contains($bytes, "Exif\0\0"), 'sin metadatos');
$im = imagecreatetruecolor(64, 32);
imagealphablending($im, false);
imagesavealpha($im, true);
imagefill($im, 0, 0, imagecolorallocatealpha($im, 0, 0, 0, 127));
imagesetpixel($im, 0, 0, imagecolorallocatealpha($im, 255, 0, 0, 0));
ob_start();
imagepng($im);
$png = ob_get_clean();
imagedestroy($im);
$r = $upload($png, 'transparent.png');
$assert($r->getStatusCode() === 200, 'PNG transparente');
$transparent = json_decode((string) $r->getBody(), true)['url'];
$file = '/tmp/transparent-output.webp';
file_put_contents($file, (string) $client->get($transparent)->getBody());
$p = new Process(['/usr/bin/identify', '-format', '%[opaque]', $file]);
$p->mustRun();
$assert(trim(strtolower($p->getOutput())) === 'false', 'transparencia conservada');
$shape = getimagesize($file);
$assert([$shape[0], $shape[1]] === [64, 32], 'sin ampliar imágenes pequeñas');
$webp = file_get_contents($file);
$r = $upload($webp, '../../attack.php');
$assert($r->getStatusCode() === 200, 'WebP real con nombre no confiable');
$safe = json_decode((string) $r->getBody(), true)['url'];
$assert(preg_match('~^/uploads/noticias/[a-f0-9-]{36}\.webp$~D', $safe) === 1, 'nombre UUID del servidor');
$directory = '/home/ensenada/backend-laravel-produccion/storage/app/private/noticias';
$before = count(glob($directory.'/*'));
foreach (['<svg><script>bad</script></svg>', '<?php echo 1;', 'corrupt-jpeg'] as $bad) {
    $assert($upload($bad)->getStatusCode() === 400, 'archivo inválido');
}
$big = str_repeat('x', 30 * 1024 * 1024 + 1);
$assert($upload($big)->getStatusCode() === 413, '30 MiB + 1 rechazado');
unset($big);
$ihdr = pack('NNCCCCC', 20000, 20000, 8, 6, 0, 0, 0);
$bomb = "\x89PNG\r\n\x1a\n".pack('N', 13).'IHDR'.$ihdr.pack('N', crc32('IHDR'.$ihdr));
$assert($upload($bomb, 'huge.png')->getStatusCode() === 400, 'dimensiones inseguras rechazadas antes de decodificar');
$assert(count(glob($directory.'/*')) === $before, 'sin publicar archivos parciales');
$rows = json_decode((string) $client->get('/api/admin/noticias')->getBody(), true);
$news = end($rows);
foreach ($rows as $candidate) {
    if (str_starts_with($candidate['slug'], 'cronica-sintetica-desde-chromium')) {
        $news = $candidate;
        break;
    }
}
if (count($news['imagenes'] ?? []) === 3) {
    $news['imagenes'] = [...$news['imagenes'], $oriented, $transparent, $safe];
    $r = $client->put('/api/admin/noticias/'.$news['id'], ['json' => $news]);
    $assert($r->getStatusCode() === 200 && count(json_decode((string) $r->getBody(), true)['imagenes']) === 6, 'galería de seis y portada conservadas');
} else {
    $assert(count($news['imagenes'] ?? []) === 6, 'galería de seis conservada');
}
$admin = new Client(['base_uri' => 'https://www.ensenada.gov.ar', 'verify' => false, 'http_errors' => false, 'cookies' => new CookieJar,
    'headers' => ['Origin' => 'https://www.ensenada.gov.ar', 'Sec-Fetch-Site' => 'same-origin']]);
$assert($admin->post('/api/auth/login', ['json' => ['email' => 'admin@example.test', 'password' => 'Synthetic-Production-Test-123!']])->getStatusCode() === 200, 'admin CMS');
$r = $admin->post('/api/admin/archivos/sitio', ['multipart' => [['name' => 'archivo', 'contents' => $png, 'filename' => 'access.png']]]);
$assert($r->getStatusCode() === 200, 'imagen para acceso institucional');
$siteImage = json_decode((string) $r->getBody(), true)['url'];
$anonymous = new Client(['base_uri' => 'https://www.ensenada.gov.ar', 'verify' => false, 'http_errors' => false]);
$assert($anonymous->get($siteImage)->getStatusCode() === 404, 'imagen institucional sin publicar');
$content = json_decode((string) $admin->get('/api/admin/site-content')->getBody(), true);
$previous = $content;
$content['accesos'][0]['imagen'] = $siteImage;
$assert($admin->put('/api/admin/site-content', ['json' => $content])->getStatusCode() === 200, 'guardar foto de acceso');
$assert($anonymous->get($siteImage)->getStatusCode() === 200, 'foto del acceso descargable públicamente');
$admin->put('/api/admin/site-content', ['json' => $previous]);
$assert($anonymous->get($siteImage)->getStatusCode() === 404, 'quitar referencia revoca acceso público');
echo "OK $count comprobaciones de orientación, transparencia, límites, corrupción y galería\n";
