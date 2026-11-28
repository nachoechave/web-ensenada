<?php

namespace Tests\Http;

use App\Content\SiteImageEncoder;
use App\Content\SiteImageStore;
use App\News\NewsImageStore;
use GuzzleHttp\Client;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Fixtures\PhotoFixtures;
use Tests\Fixtures\SiteImageFixtures;

class SiteImagesHttpTest extends TestCase
{
    protected function setUp(): void
    {
        DisposableDatabase::connect()->exec('DELETE FROM auth_login_windows');
    }

    private function actor(string $role = 'super_admin'): Client
    {
        $client = EditorialFixture::browser();
        $this->assertSame(200, EditorialFixture::login($client, $role.'@example.test')->getStatusCode());

        return $client;
    }

    public function test_raster_formats_are_optimized_and_downloaded_with_spring_contract(): void
    {
        $admin = $this->actor();
        $public = LocalHttps::client();
        foreach ([
            [SiteImageFixtures::raster('jpg', 3200, 1600), [1920, 960], false],
            [SiteImageFixtures::raster('png'), [64, 48], false],
            [SiteImageFixtures::raster('rgba'), [64, 48], true],
            [SiteImageFixtures::raster('gif'), [64, 48], false],
            [SiteImageFixtures::raster('bmp'), [64, 48], false],
            [SiteImageFixtures::raster('wbmp'), [64, 48], false],
            [SiteImageFixtures::tiff(), [2, 2], false],
        ] as [$input, $dimensions, $alpha]) {
            $mime = 'image/webp';
            $response = SiteImageFixtures::post($admin, $dimensions === [1920, 960]
                ? $input.'synthetic-trailing-marker' : $input);
            $this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());
            $data = EditorialFixture::json($response);
            $this->assertSame(['url', 'nombreOriginal', 'contentType', 'size'], array_keys($data));
            $this->assertMatchesRegularExpression('~^/uploads/sitio/[0-9a-f-]{36}\.webp$~D', $data['url']);
            $this->assertSame('/uploads/sitio/'.$data['nombreOriginal'], $data['url']);
            $this->assertSame($mime, $data['contentType']);
            $this->assertIsInt($data['size']);
            $this->assertLessThanOrEqual(SiteImageEncoder::MAX_OUTPUT_BYTES, $data['size']);
            $file = SiteImageStore::DIRECTORY.'/'.$data['nombreOriginal'];
            $this->assertSame(0600, fileperms($file) & 0777);
            $download = $public->get($data['url']);
            $this->assertSame(200, $download->getStatusCode());
            $this->assertSame($mime, $download->getHeaderLine('Content-Type'));
            $this->assertSame('nosniff', $download->getHeaderLine('X-Content-Type-Options'));
            $bytes = (string) $download->getBody();
            $this->assertSame($data['size'], strlen($bytes));
            $this->assertStringNotContainsString('synthetic-trailing-marker', $bytes);
            $decoded = getimagesizefromstring($bytes);
            $this->assertSame($dimensions, [$decoded[0], $decoded[1]]);
            $this->assertSame($mime, $decoded['mime']);
            if ($alpha) {
                $this->assertLessThan(1, PhotoFixtures::firstPixelAlpha($bytes));
            }
            $head = $public->head($data['url']);
            $this->assertSame(200, $head->getStatusCode());
            $this->assertSame((string) $data['size'], $head->getHeaderLine('Content-Length'));
            $this->assertSame('', (string) $head->getBody());
        }
    }

    public function test_limits_invalid_content_and_transport_leave_no_images(): void
    {
        $admin = $this->actor();
        $dir = SiteImageStore::DIRECTORY;
        $before = scandir($dir);
        $valid = SiteImageFixtures::raster('jpg');
        foreach (['', '<svg onload="alert(1)"/>', '<html>script</html>', '<?php echo 1;', substr($valid, 0, -30),
            substr(SiteImageFixtures::raster('rgba'), 0, 80), 'GIF89a'.pack('vv', 15000, 15000)."\x00\x00\x00"] as $invalid) {
            $this->assertSame(400, SiteImageFixtures::post($admin, $invalid)->getStatusCode());
        }
        $this->assertSame(415, $admin->post(SiteImageFixtures::UPLOAD, ['json' => []])->getStatusCode());
        $this->assertSame(400, $admin->post(SiteImageFixtures::UPLOAD, ['multipart' => [['name' => 'otro', 'contents' => 'x']]])->getStatusCode());
        foreach ([30, 31] as $limit) {
            $response = SiteImageFixtures::post($admin, str_pad($valid, $limit * 1024 * 1024 + 1, 'x'));
            $this->assertSame(413, $response->getStatusCode());
            $this->assertSame('', (string) $response->getBody());
        }
        $this->assertSame($before, scandir($dir));
        $nearLimit = str_pad($valid, SiteImageEncoder::MAX_INPUT_BYTES, 'x');
        $this->assertSame(200, SiteImageFixtures::post($admin, $nearLimit)->getStatusCode());
    }

    public function test_png_and_webp_exif_are_applied_and_removed(): void
    {
        $admin = $this->actor();
        $png = SiteImageFixtures::raster('rgba');
        foreach ([PhotoFixtures::orientedPng($png), PhotoFixtures::orientedWebp(PhotoFixtures::webp($png))] as $input) {
            $response = SiteImageFixtures::post($admin, $input);
            $this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());
            $data = EditorialFixture::json($response);
            $body = (string) LocalHttps::client()->get($data['url'])->getBody();
            $this->assertSame([48, 64], array_slice(getimagesizefromstring($body), 0, 2));
            $this->assertStringNotContainsString('EXIF', $body);
        }
    }

    public function test_session_role_and_csrf_are_required(): void
    {
        $input = SiteImageFixtures::raster('jpg');
        $before = scandir(SiteImageStore::DIRECTORY);
        $this->assertSame(401, SiteImageFixtures::post(EditorialFixture::browser(), $input)->getStatusCode());
        foreach (['prensa', 'hacienda'] as $role) {
            $this->assertSame(403, SiteImageFixtures::post($this->actor($role), $input, ['X-Role' => 'SUPER_ADMIN'])->getStatusCode());
        }
        $admin = $this->actor();
        $this->assertSame(403, SiteImageFixtures::post($admin, $input, ['Sec-Fetch-Site' => ''])->getStatusCode());
        $this->assertSame(403, SiteImageFixtures::post($admin, $input, ['Origin' => 'https://localhost:19443', 'Sec-Fetch-Site' => 'same-site'])->getStatusCode());
        $db = DisposableDatabase::connect();
        $db->exec("UPDATE usuarios SET activo=0 WHERE email='super_admin@example.test'");
        try {
            $this->assertSame(401, SiteImageFixtures::post($admin, $input)->getStatusCode());
        } finally {
            $db->exec("UPDATE usuarios SET activo=1 WHERE email='super_admin@example.test'");
        }
        $this->assertSame(401, SiteImageFixtures::post($admin, $input)->getStatusCode());
        $this->assertSame($before, scandir(SiteImageStore::DIRECTORY));
    }

    public function test_dense_transparency_is_reduced_and_resource_failures_are_controlled(): void
    {
        $admin = $this->actor();
        $input = SiteImageFixtures::alphaPng(1400, 1200, true);
        $this->assertGreaterThan(SiteImageEncoder::MAX_OUTPUT_BYTES, strlen($input));
        $response = SiteImageFixtures::post($admin, $input);
        $this->assertSame(200, $response->getStatusCode());
        $data = EditorialFixture::json($response);
        $this->assertSame('image/webp', $data['contentType']);
        $this->assertLessThanOrEqual(SiteImageEncoder::MAX_OUTPUT_BYTES, $data['size']);
        $body = (string) LocalHttps::client()->get($data['url'])->getBody();
        $this->assertSame([1400, 1200], array_slice(getimagesizefromstring($body), 0, 2));

        $before = scandir(SiteImageStore::DIRECTORY);
        $large = SiteImageFixtures::alphaPng(12000, 12000, false);
        $this->assertSame(400, SiteImageFixtures::post($admin, $large)->getStatusCode());
        $lock = fopen(SiteImageEncoder::SCRATCH.'/.worker', 'c');
        try {
            $this->assertTrue(flock($lock, LOCK_EX | LOCK_NB));
            $this->assertSame(503, SiteImageFixtures::post($admin, SiteImageFixtures::raster('jpg'))->getStatusCode());
        } finally {
            fclose($lock);
        }
        $this->assertSame($before, scandir(SiteImageStore::DIRECTORY));
    }

    public function test_historical_names_retain_urls_without_following_links(): void
    {
        $public = LocalHttps::client();
        $dir = SiteImageStore::DIRECTORY;
        $legacy = SiteImageFixtures::raster('jpg');
        $files = ['Legacy-banner_2018.JPG', '11111111-1111-4111-8111-111111111111.jpg', 'impostor.png', 'escape.jpg'];
        $outside = tempnam('/tmp', 'synthetic-site-');
        file_put_contents($outside, $legacy);
        try {
            file_put_contents($dir.'/'.$files[0], $legacy);
            file_put_contents($dir.'/'.$files[1], $legacy);
            file_put_contents($dir.'/'.$files[2], '<script>no</script>');
            $this->assertTrue(symlink($outside, $dir.'/'.$files[3]));
            foreach (array_slice($files, 0, 2) as $name) {
                $response = $public->get('/uploads/sitio/'.$name);
                $this->assertSame(200, $response->getStatusCode());
                $this->assertSame($legacy, (string) $response->getBody());
                $this->assertSame(404, $public->get('/uploads/noticias/'.$name)->getStatusCode());
            }
            foreach ([$files[2], $files[3], '.stage-x.jpg', '%2e%2e%2f'.basename($outside), 'shell.php'] as $name) {
                $this->assertSame(404, $public->get('/uploads/sitio/'.$name)->getStatusCode());
            }
        } finally {
            foreach ($files as $file) {
                @unlink($dir.'/'.$file);
            }
            unlink($outside);
        }
    }

    public function test_storage_failures_publish_nothing_and_leave_news_untouched(): void
    {
        $admin = $this->actor();
        $dir = SiteImageStore::DIRECTORY;
        $before = scandir($dir);
        $newsBefore = scandir(NewsImageStore::DIRECTORY);
        $moved = $dir.'-test-'.bin2hex(random_bytes(5));
        $this->assertTrue(rename($dir, $moved));
        try {
            file_put_contents($dir, 'no es un directorio');
            $failed = SiteImageFixtures::post($admin, SiteImageFixtures::raster('jpg'));
            $this->assertSame(500, $failed->getStatusCode());
            $this->assertStringNotContainsString($dir, (string) $failed->getBody());
        } finally {
            unlink($dir);
            rename($moved, $dir);
        }
        $shortStream = fopen('php://temp', 'w+b');
        try {
            fwrite($shortStream, 'partial');
            rewind($shortStream);
            (new SiteImageStore)->put($shortStream, 'jpg', 100);
            $this->fail('Una escritura corta no debe publicarse.');
        } catch (RuntimeException) {
            $this->assertSame($before, scandir($dir));
            $this->assertSame($newsBefore, scandir(NewsImageStore::DIRECTORY));
        } finally {
            fclose($shortStream);
        }
        $this->assertSame(200, SiteImageFixtures::post($admin, SiteImageFixtures::raster('jpg'))->getStatusCode());
    }
}
