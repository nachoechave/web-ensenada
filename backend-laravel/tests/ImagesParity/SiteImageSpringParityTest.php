<?php

namespace Tests\ImagesParity;

use App\Content\SiteImageEncoder;
use App\Content\SiteImageStore;
use GuzzleHttp\Client;
use PHPUnit\Framework\TestCase;
use Tests\Fixtures\SiteImageFixtures;
use Tests\Http\DisposableDatabase;
use Tests\Http\EditorialFixture;
use Tests\Http\LocalHttps;

class SiteImageSpringParityTest extends TestCase
{
    public function test_the_live_backends_match_the_site_image_contract(): void
    {
        DisposableDatabase::connect()->exec('DELETE FROM auth_login_windows');
        $laravel = EditorialFixture::browser();
        $this->assertSame(200, EditorialFixture::login($laravel, 'super_admin@example.test')->getStatusCode());
        $options = ['base_uri' => 'http://spring:8080', 'http_errors' => false, 'timeout' => 25, 'headers' => ['Accept' => 'application/json']];
        $springPublic = new Client($options);
        $login = EditorialFixture::login($springPublic, 'super_admin@example.test');
        $this->assertSame(200, $login->getStatusCode());
        $spring = new Client([...$options, 'headers' => [...$options['headers'], 'Authorization' => 'Bearer '.EditorialFixture::json($login)['token']]]);
        $public = LocalHttps::client();

        foreach ([
            [SiteImageFixtures::raster('jpg', 3210, 1600), 'image/jpeg', [2560, 1276]],
            [SiteImageFixtures::raster('png'), 'image/jpeg', [64, 48]],
            [SiteImageFixtures::raster('rgba'), 'image/png', [64, 48]],
            [SiteImageFixtures::raster('gif'), 'image/jpeg', [64, 48]],
            [SiteImageFixtures::raster('bmp'), 'image/jpeg', [64, 48]],
            [SiteImageFixtures::raster('wbmp'), 'image/jpeg', [64, 48]],
            [SiteImageFixtures::tiff(), 'image/jpeg', [2, 2]],
        ] as [$input, $springMime, $springDimensions]) {
            foreach ([[$spring, $springPublic], [$laravel, $public]] as [$actor, $anonymous]) {
                $response = SiteImageFixtures::post($actor, $input);
                $this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());
                $data = EditorialFixture::json($response);
                $this->assertEqualsCanonicalizing(['url', 'nombreOriginal', 'contentType', 'size'], array_keys($data));
                $mime = $actor === $spring ? $springMime : 'image/webp';
                $dimensions = $actor === $laravel && $springDimensions[0] === 2560 ? [1920, 957] : $springDimensions;
                $this->assertMatchesRegularExpression('~^[a-f0-9-]{36}\.(jpg|png|webp)$~D', $data['nombreOriginal']);
                $this->assertSame('/uploads/sitio/'.$data['nombreOriginal'], $data['url']);
                $this->assertSame($mime, $data['contentType']);
                $this->assertIsInt($data['size']);
                $this->assertLessThanOrEqual(SiteImageEncoder::MAX_OUTPUT_BYTES, $data['size']);
                $download = $anonymous->get($data['url']);
                $this->assertSame(200, $download->getStatusCode());
                $output = (string) $download->getBody();
                $this->assertSame($data['size'], strlen($output));
                $decoded = getimagesizefromstring($output);
                $this->assertSame($dimensions, [$decoded[0], $decoded[1]]);
                $this->assertSame($mime, $decoded['mime']);
                if ($actor === $spring) {
                    $this->assertHistoricalCopy($public, $data, $output);
                }
            }
        }
        foreach ([$spring, $laravel] as $actor) {
            $this->assertSame(400, SiteImageFixtures::post($actor, '<svg onload="alert(1)"/>')->getStatusCode());
            $this->assertSame(415, $actor->post(SiteImageFixtures::UPLOAD, ['json' => []])->getStatusCode());
            $this->assertSame(401, SiteImageFixtures::post($actor === $spring ? $springPublic : EditorialFixture::browser(), '<svg/>')->getStatusCode());
            foreach ([30, 31] as $limit) {
                $response = SiteImageFixtures::post($actor, str_repeat('x', $limit * 1024 * 1024 + 1));
                $this->assertSame(413, $response->getStatusCode());
                $this->assertSame('', (string) $response->getBody());
            }
        }
    }

    private function assertHistoricalCopy(Client $public, array $data, string $output): void
    {
        $file = SiteImageStore::DIRECTORY.'/'.$data['nombreOriginal'];
        $stream = fopen($file, 'xb');
        try {
            $this->assertSame(strlen($output), fwrite($stream, $output));
            $this->assertTrue(chmod($file, 0600));
            $this->assertTrue(fflush($stream));
            $copied = $public->get($data['url']);
            $this->assertSame(200, $copied->getStatusCode());
            $this->assertSame($output, (string) $copied->getBody());
        } finally {
            fclose($stream);
            unlink($file);
        }
    }
}
