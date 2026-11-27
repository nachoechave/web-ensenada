<?php

namespace Tests\Parity;

use GuzzleHttp\Client;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Http\DisposableDatabase;
use Tests\Http\EditorialFixture;
use Tests\Http\LocalHttps;

class SiteContentSpringParityTest extends TestCase
{
    private const ADMIN = '/api/admin/site-content';

    private const PUBLIC = '/api/site-content';

    public function test_existing_spring_and_laravel_exchange_the_same_documents(): void
    {
        $db = DisposableDatabase::connect();
        $original = $db->query('SELECT contenido_json, updated_at FROM portal_site_content WHERE id=1')->fetch(PDO::FETCH_ASSOC);
        $db->exec('DELETE FROM auth_login_windows');
        $spring = new Client(['base_uri' => 'http://spring:8080', 'http_errors' => false, 'timeout' => 20, 'headers' => ['Accept' => 'application/json']]);
        $laravel = EditorialFixture::browser();
        $springLogin = EditorialFixture::login($spring, 'super_admin@example.test');
        $this->assertSame(200, $springLogin->getStatusCode());
        $bearer = ['Authorization' => 'Bearer '.EditorialFixture::json($springLogin)['token']];
        $this->assertSame(200, EditorialFixture::login($laravel, 'super_admin@example.test')->getStatusCode());

        try {
            foreach ([$original['contenido_json'], '{}', '{"areas":{"secretarias":[],"titulo":"Ñandú 😀 & <texto>"},"extra":{"a":[],"b":{},"c":null}}'] as $raw) {
                $options = ['body' => " \t".$raw."\n", 'headers' => ['Content-Type' => 'application/json']];
                foreach ([
                    $spring->put(self::ADMIN, ['headers' => [...$options['headers'], ...$bearer]] + $options),
                    $laravel->get(self::ADMIN),
                    LocalHttps::client()->get(self::PUBLIC),
                    $laravel->put(self::ADMIN, $options),
                    $spring->get(self::ADMIN, ['headers' => $bearer]),
                    $spring->get(self::PUBLIC),
                ] as $response) {
                    $this->assertSame(200, $response->getStatusCode());
                    $this->assertContains('no-store', array_map('trim', explode(',', $response->getHeaderLine('Cache-Control'))));
                    $this->assertSame('application/json', $response->getHeaderLine('Content-Type'));
                    $this->assertSame($raw, (string) $response->getBody());
                }
            }
            foreach ([$spring, $laravel] as $client) {
                $headers = $client === $spring ? $bearer : [];
                $this->assertSame(400, $client->put(self::ADMIN, ['body' => '[]', 'headers' => [...$headers, 'Content-Type' => 'application/json']])->getStatusCode());
                $this->assertSame(415, $client->put(self::ADMIN, ['body' => '{}', 'headers' => [...$headers, 'Content-Type' => 'text/plain']])->getStatusCode());
                $tooLong = '{"x":"'.str_repeat('a', 160001).'"}';
                $this->assertSame(413, $client->put(self::ADMIN, ['body' => $tooLong, 'headers' => [...$headers, 'Content-Type' => 'application/json']])->getStatusCode());
            }

            $tooLongForText = '{"x":"'.str_repeat('é', 32764).'"}';
            $this->assertSame(65536, strlen($tooLongForText));
            $this->assertSame(500, $spring->put(self::ADMIN, ['body' => $tooLongForText, 'headers' => [...$bearer, 'Content-Type' => 'application/json']])->getStatusCode());
            $this->assertSame(413, $laravel->put(self::ADMIN, ['body' => $tooLongForText, 'headers' => ['Content-Type' => 'application/json']])->getStatusCode());

            $this->assertSame(200, $spring->put(self::ADMIN, ['body' => '{malformado}', 'headers' => [...$bearer, 'Content-Type' => 'application/json']])->getStatusCode());
            $this->assertSame(400, $laravel->put(self::ADMIN, ['body' => '{malformado}', 'headers' => ['Content-Type' => 'application/json']])->getStatusCode());
            $this->assertSame(500, $laravel->get(self::ADMIN)->getStatusCode());
        } finally {
            $db->prepare('UPDATE portal_site_content SET contenido_json=?,updated_at=? WHERE id=1')
                ->execute([$original['contenido_json'], $original['updated_at']]);
        }
    }
}
