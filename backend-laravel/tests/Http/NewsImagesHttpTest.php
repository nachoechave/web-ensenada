<?php

namespace Tests\Http;

use App\Content\SiteImageEncoder;
use App\News\NewsImageStore;
use GuzzleHttp\Client;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Fixtures\NewsImageFixtures;
use Tests\Fixtures\PhotoFixtures;
use Tests\Fixtures\SiteImageFixtures;

class NewsImagesHttpTest extends TestCase
{
    private PDO $auth;

    protected function setUp(): void
    {
        $this->auth = DisposableDatabase::connect();
        $this->auth->exec('DELETE FROM auth_login_windows');
    }

    private function actor(string $role = 'prensa'): Client
    {
        $client = EditorialFixture::browser();
        $this->assertSame(200, EditorialFixture::login($client, $role.'@example.test')->getStatusCode());

        return $client;
    }

    public function test_images_are_detected_recoded_stored_and_served_as_generated_names(): void
    {
        $marker = '<?php echo "synthetic-marker"; ?><html>synthetic</html>';
        foreach (['jpg', 'png', 'webp'] as $extension) {
            $client = $this->actor($extension === 'jpg' ? 'prensa' : 'super_admin');
            $source = $extension === 'webp' ? PhotoFixtures::webp(NewsImageFixtures::bytes('png')) : NewsImageFixtures::bytes($extension).$marker;
            $mime = 'image/webp';
            $response = NewsImageFixtures::post($client, $source, '../../intento.php', 'text/html');
            $this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());
            $data = EditorialFixture::json($response);
            $this->assertSame(['url', 'nombreOriginal', 'contentType', 'size'], array_keys($data));
            $this->assertMatchesRegularExpression('~^'.NewsImageStore::FILENAME.'$~D', $data['nombreOriginal']);
            $this->assertStringEndsWith('.webp', $data['nombreOriginal']);
            $this->assertSame('/uploads/noticias/'.$data['nombreOriginal'], $data['url']);
            $this->assertSame($mime, $data['contentType']);
            $this->assertIsInt($data['size']);
            $this->assertSame(0600, fileperms(NewsImageStore::DIRECTORY.'/'.$data['nombreOriginal']) & 0777);

            $anonymous = LocalHttps::client();
            $download = $anonymous->get($data['url']);
            $output = (string) $download->getBody();
            $this->assertSame(200, $download->getStatusCode());
            $this->assertSame($mime, $download->getHeaderLine('Content-Type'));
            $this->assertSame('nosniff', $download->getHeaderLine('X-Content-Type-Options'));
            $this->assertSame($data['size'], strlen($output));
            $this->assertStringNotContainsString($marker, $output);
            $shape = getimagesizefromstring($output);
            $this->assertSame([32, 24], [$shape[0], $shape[1]]);
            if ($extension !== 'jpg') {
                $this->assertEqualsWithDelta(1 - 53 / 127, PhotoFixtures::firstPixelAlpha($output), 0.015);
            }
            $head = $anonymous->head($data['url']);
            $this->assertSame(200, $head->getStatusCode());
            $this->assertSame((string) $data['size'], $head->getHeaderLine('Content-Length'));
            $this->assertSame('', (string) $head->getBody());
        }
        $this->assertSame(0700, fileperms(NewsImageStore::DIRECTORY) & 0777);
    }

    public function test_invalid_and_corrupt_files_are_rejected_without_publication(): void
    {
        $client = $this->actor();
        $png = NewsImageFixtures::bytes('png');
        $jpeg = NewsImageFixtures::bytes('jpg');
        $before = scandir(NewsImageStore::DIRECTORY);
        $gif = base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7', true);
        $this->assertSame('image/gif', getimagesizefromstring($gif)['mime']);
        foreach (['', '<svg xmlns="http://www.w3.org/2000/svg"/>', '<html><script>alert(1)</script></html>', '<?php echo 1;',
            $gif, substr($png, 0, 50), substr($jpeg, 0, -25)] as $invalid) {
            $response = NewsImageFixtures::post($client, $invalid);
            $this->assertSame(400, $response->getStatusCode());
            $this->assertSame('Bad Request', EditorialFixture::json($response)['error']);
        }
        $this->assertSame(400, $client->post(NewsImageFixtures::UPLOAD, ['multipart' => [['name' => 'otro', 'contents' => 'x']]])->getStatusCode());
        $webp = PhotoFixtures::webp($png);
        $this->assertSame('VP8X', substr($webp, 12, 4));
        $animated = substr_replace($webp, chr(ord($webp[20]) | 2), 20, 1);
        $response = NewsImageFixtures::post($client, $animated);
        $this->assertSame(400, $response->getStatusCode());
        $this->assertStringContainsString('sin animación', EditorialFixture::json($response)['detail']);
        $this->assertSame(400, NewsImageFixtures::post($client, substr($webp, 0, -1))->getStatusCode());
        $this->assertSame(415, $client->post(NewsImageFixtures::UPLOAD, ['json' => ['archivo' => 'texto']])->getStatusCode());
        $this->assertSame($before, scandir(NewsImageStore::DIRECTORY));
    }

    public function test_real_byte_and_pixel_boundaries(): void
    {
        $client = $this->actor();
        $valid = NewsImageFixtures::bytes('jpg');
        $boundary = str_pad($valid, SiteImageEncoder::MAX_INPUT_BYTES, 'x');
        $this->assertSame(200, NewsImageFixtures::post($client, $boundary)->getStatusCode());
        $this->assertSame(413, NewsImageFixtures::post($client, $boundary.'x')->getStatusCode());
        unset($boundary);
        $bombHeader = substr_replace(NewsImageFixtures::bytes('png'), pack('NN', 6001, 4000), 16, 8);
        $oversized = NewsImageFixtures::post($client, $bombHeader);
        $this->assertSame(400, $oversized->getStatusCode());
        $this->assertStringContainsString('dimensiones o píxeles', EditorialFixture::json($oversized)['detail']);
        $response = NewsImageFixtures::post($client, str_pad($valid, 31 * 1024 * 1024 + 1, 'x'));
        $this->assertSame(413, $response->getStatusCode());
        $this->assertSame('', (string) $response->getBody());
    }

    public function test_high_resolution_jpeg_is_rotated_and_resized_without_exif(): void
    {
        $client = $this->actor();
        $jpeg = PhotoFixtures::orientationSix(SiteImageFixtures::raster('jpg', 6000, 4000));
        $response = NewsImageFixtures::post($client, $jpeg);
        $this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());
        $data = EditorialFixture::json($response);
        $download = LocalHttps::client()->get($data['url']);
        $bytes = (string) $download->getBody();
        $this->assertSame([1280, 1920], array_slice(getimagesizefromstring($bytes), 0, 2));
        $this->assertSame('image/webp', $download->getHeaderLine('Content-Type'));
        $this->assertStringNotContainsString('EXIF', $bytes);
        $this->assertLessThan(strlen($jpeg), strlen($bytes));
        $this->assertSame(['.', '..', '.worker'], scandir(SiteImageEncoder::SCRATCH));
    }

    public function test_permissions_revocation_and_multipart_csrf_are_enforced(): void
    {
        $bytes = NewsImageFixtures::bytes('jpg');
        $before = scandir(NewsImageStore::DIRECTORY);
        $this->assertSame(401, NewsImageFixtures::post(EditorialFixture::browser(), $bytes)->getStatusCode());
        $this->assertSame(403, NewsImageFixtures::post($this->actor('hacienda'), $bytes, headers: ['X-Role' => 'SUPER_ADMIN'])->getStatusCode());
        $editor = $this->actor();
        $this->assertSame(403, NewsImageFixtures::post($editor, $bytes, headers: ['Origin' => 'https://localhost:19443', 'Sec-Fetch-Site' => 'same-site'])->getStatusCode());
        $this->assertSame(403, NewsImageFixtures::post($editor, $bytes, headers: ['Sec-Fetch-Site' => ''])->getStatusCode());
        $admin = $this->actor('super_admin');
        $id = (int) $this->auth->query("SELECT id FROM usuarios WHERE email = 'prensa@example.test'")->fetchColumn();
        $path = '/api/admin/usuarios/'.$id.'/activo';
        $this->assertSame(200, $admin->put($path, ['json' => ['activo' => false]])->getStatusCode());
        try {
            $this->assertSame(401, NewsImageFixtures::post($editor, $bytes)->getStatusCode());
        } finally {
            $this->assertSame(200, $admin->put($path, ['json' => ['activo' => true]])->getStatusCode());
        }
        $this->assertSame(401, NewsImageFixtures::post($editor, $bytes)->getStatusCode());
        $this->assertSame($before, scandir(NewsImageStore::DIRECTORY));
    }

    public function test_public_reader_rejects_symlinks_and_non_image_paths(): void
    {
        $client = LocalHttps::client();
        $sentinel = tempnam('/tmp', 'synthetic-news-');
        file_put_contents($sentinel, 'synthetic-outside-storage');
        $filename = 'f85edcb0-e8f0-4a70-851f-26214981967a.jpg';
        $link = NewsImageStore::DIRECTORY.'/'.$filename;
        try {
            $this->assertTrue(symlink($sentinel, $link));
            $response = $client->get('/uploads/noticias/'.$filename);
            $this->assertSame(404, $response->getStatusCode());
            $this->assertStringNotContainsString('synthetic-outside-storage', (string) $response->getBody());
            foreach (['../'.basename($sentinel), '%2e%2e%2f'.basename($sentinel), 'script.php', '.stage-'.$filename, 'old-unsafe.svg'] as $suffix) {
                $response = $client->get('/uploads/noticias/'.$suffix);
                $this->assertSame(404, $response->getStatusCode());
            }
        } finally {
            unlink($link);
            unlink($sentinel);
        }
    }

    public function test_unavailable_storage_returns_generic_failure(): void
    {
        $client = $this->actor();
        $root = NewsImageStore::DIRECTORY;
        $moved = $root.'-test-'.bin2hex(random_bytes(5));
        $this->assertTrue(rename($root, $moved));
        try {
            file_put_contents($root, 'fixture no directory');
            $response = NewsImageFixtures::post($client, NewsImageFixtures::bytes('jpg'));
            $this->assertSame(500, $response->getStatusCode());
            $this->assertSame('Internal Server Error', EditorialFixture::json($response)['error']);
            $this->assertStringNotContainsString($root, (string) $response->getBody());
        } finally {
            if (is_file($root)) {
                unlink($root);
            }
            rename($moved, $root);
        }
    }

    public function test_real_enospc_publishes_nothing_and_next_upload_recovers(): void
    {
        $client = $this->actor();
        $root = NewsImageStore::DIRECTORY;
        $this->assertLessThanOrEqual(64 * 1024 * 1024, disk_total_space($root));
        $before = scandir($root);
        $fixture = $root.'/.test-fill-'.bin2hex(random_bytes(5));
        $fill = fopen($fixture, 'xb');
        try {
            $remaining = (int) disk_free_space($root);
            $chunk = str_repeat('x', 1024 * 1024);
            while ($remaining > 0) {
                $len = min(strlen($chunk), $remaining);
                $this->assertSame($len, fwrite($fill, substr($chunk, 0, $len)));
                $remaining -= $len;
            }
            $this->assertSame(0.0, disk_free_space($root));
            $response = NewsImageFixtures::post($client, NewsImageFixtures::bytes('jpg'));
            $this->assertSame(500, $response->getStatusCode());
            $this->assertSame('Internal Server Error', EditorialFixture::json($response)['error']);
        } finally {
            fclose($fill);
            unlink($fixture);
        }
        $this->assertSame($before, scandir($root));
        $this->assertSame(200, NewsImageFixtures::post($client, NewsImageFixtures::bytes('jpg'))->getStatusCode());
    }
}
