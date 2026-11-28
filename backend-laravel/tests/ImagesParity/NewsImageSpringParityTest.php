<?php

namespace Tests\ImagesParity;

use App\News\NewsImageEncoder;
use App\News\NewsImageStore;
use GuzzleHttp\Client;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Tests\Fixtures\NewsImageFixtures;
use Tests\Http\DisposableDatabase;
use Tests\Http\EditorialFixture;

class NewsImageSpringParityTest extends TestCase
{
    public function test_live_spring_and_laravel_match_upload_contract_and_statuses(): void
    {
        DisposableDatabase::connect()->exec('DELETE FROM auth_login_windows');
        $laravel = EditorialFixture::browser();
        $this->assertSame(200, EditorialFixture::login($laravel, 'prensa@example.test')->getStatusCode());
        $options = ['base_uri' => 'http://spring:8080', 'http_errors' => false, 'timeout' => 25];
        $springPublic = new Client($options);
        $login = EditorialFixture::login($springPublic, 'prensa@example.test');
        $this->assertSame(200, $login->getStatusCode());
        $spring = new Client([...$options, 'headers' => ['Authorization' => 'Bearer '.EditorialFixture::json($login)['token'], 'Accept' => 'application/json']]);
        $valid = ['jpg' => NewsImageFixtures::bytes('jpg'), 'png' => NewsImageFixtures::bytes('png')];
        $marker = '<?php echo "synthetic"; ?>';
        foreach ([[$spring, $springPublic], [$laravel, EditorialFixture::browser()]] as [$actor, $anonymous]) {
            foreach (['jpg' => 'image/jpeg', 'png' => 'image/png'] as $extension => $mime) {
                $response = NewsImageFixtures::post($actor, $valid[$extension].$marker, 'usuario.html', 'text/html');
                $this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());
                $data = EditorialFixture::json($response);
                if ($actor === $laravel) {
                    $extension = 'webp';
                    $mime = 'image/webp';
                }
                $this->assertEqualsCanonicalizing(['url', 'nombreOriginal', 'contentType', 'size'], array_keys($data));
                $this->assertMatchesRegularExpression('~^'.NewsImageStore::FILENAME.'$~D', $data['nombreOriginal']);
                $this->assertSame('/uploads/noticias/'.$data['nombreOriginal'], $data['url']);
                $this->assertStringEndsWith('.'.$extension, $data['url']);
                $this->assertSame($mime, $data['contentType']);
                $this->assertIsInt($data['size']);
                $download = $anonymous->get($data['url']);
                $this->assertSame(200, $download->getStatusCode());
                $this->assertSame($mime, $download->getHeaderLine('Content-Type'));
                $output = (string) $download->getBody();
                $this->assertSame($data['size'], strlen($output));
                $this->assertStringNotContainsString($marker, $output);
                $this->assertSame($mime, getimagesizefromstring($output)['mime']);
            }
        }
        foreach (['', '<svg/>', '<?php echo 1;', substr($valid['png'], 0, 45)] as $bytes) {
            $this->assertMatchingError(400, NewsImageFixtures::post($spring, $bytes), NewsImageFixtures::post($laravel, $bytes));
        }
        $larger = str_pad($valid['jpg'], NewsImageEncoder::MAX_BYTES + 1, 'x');
        $this->assertSame(400, NewsImageFixtures::post($spring, $larger)->getStatusCode());
        $this->assertSame(200, NewsImageFixtures::post($laravel, $larger)->getStatusCode());
        foreach ([30, 31] as $limit) {
            $bytes = str_pad($valid['jpg'], $limit * 1024 * 1024 + 1, 'x');
            foreach ([$spring, $laravel] as $actor) {
                $response = NewsImageFixtures::post($actor, $bytes);
                $this->assertSame(413, $response->getStatusCode());
                $this->assertSame('', (string) $response->getBody());
            }
        }
        $this->assertMatchingError(
            415,
            $spring->post(NewsImageFixtures::UPLOAD, ['json' => []]),
            $laravel->post(NewsImageFixtures::UPLOAD, ['json' => []]),
        );
        $this->assertSame(401, NewsImageFixtures::post($springPublic, $valid['jpg'])->getStatusCode());
        $this->assertSame(401, NewsImageFixtures::post(EditorialFixture::browser(), $valid['jpg'])->getStatusCode());
        $login = EditorialFixture::login($springPublic, 'hacienda@example.test');
        $this->assertSame(200, $login->getStatusCode());
        $springHacienda = new Client([...$options, 'headers' => ['Authorization' => 'Bearer '.EditorialFixture::json($login)['token']]]);
        $laravelHacienda = EditorialFixture::browser();
        $this->assertSame(200, EditorialFixture::login($laravelHacienda, 'hacienda@example.test')->getStatusCode());
        $this->assertSame(403, NewsImageFixtures::post($springHacienda, $valid['jpg'])->getStatusCode());
        $this->assertSame(403, NewsImageFixtures::post($laravelHacienda, $valid['jpg'])->getStatusCode());
    }

    private function assertMatchingError(int $status, ResponseInterface $spring, ResponseInterface $laravel): void
    {
        $this->assertSame($status, $spring->getStatusCode());
        $this->assertSame($status, $laravel->getStatusCode());
        $expected = EditorialFixture::json($spring);
        $actual = EditorialFixture::json($laravel);
        foreach ([$expected, $actual] as $error) {
            $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d+Z$/D', $error['timestamp']);
        }
        unset($expected['timestamp'], $actual['timestamp'], $actual['detail']);
        $this->assertSame($expected, $actual);
    }
}
