<?php

namespace Tests\Parity;

use GuzzleHttp\Client;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Tests\Fixtures\HaciendaFixtures as Fixture;
use Tests\Http\DisposableDatabase;
use Tests\Http\EditorialFixture;
use Tests\Http\LocalHttps;

class HaciendaSpringParityTest extends TestCase
{
    public function test_the_backends_share_the_publication_and_document_metadata_contract(): void
    {
        $db = DisposableDatabase::connect();
        $db->exec('DELETE FROM auth_login_windows');
        $laravelPublic = LocalHttps::client();
        $laravel = EditorialFixture::browser();
        $this->assertSame(200, EditorialFixture::login($laravel, 'hacienda@example.test')->getStatusCode());
        $options = ['base_uri' => 'http://spring:8080', 'http_errors' => false, 'timeout' => 20, 'headers' => ['Accept' => 'application/json']];
        $springPublic = new Client($options);
        $spring = $this->springActor($springPublic, $options, 'hacienda');

        $this->assertJsonParity(200, $springPublic->get(Fixture::PUBLIC_API), $laravelPublic->get(Fixture::PUBLIC_API));
        $this->assertJsonParity(200, $spring->get(Fixture::API), $laravel->get(Fixture::API));
        foreach ([301, 302, 303, 304] as $id) {
            $this->assertJsonParity(200, $spring->get(Fixture::API.'/'.$id), $laravel->get(Fixture::API.'/'.$id));
        }
        foreach ([301, '000301', 302, 303, 99999, 'abc', '9223372036854775808'] as $id) {
            $path = Fixture::PUBLIC_API.'/'.$id;
            $expected = match ($id) {
                301, '000301' => 200,
                'abc', '9223372036854775808' => 400,
                default => 404,
            };
            $this->assertJsonParity($expected, $springPublic->get($path), $laravelPublic->get($path));
        }

        $payload = Fixture::payload(['titulo' => '  Crónica sintética Ñandú 😀  ', 'descripcion' => '  ', 'tipo' => 'OTRO']);
        $created = [];
        try {
            $a = $spring->post(Fixture::API, ['json' => $payload]);
            $b = $laravel->post(Fixture::API, ['json' => $payload]);
            $this->assertSame(201, $a->getStatusCode());
            $this->assertSame(201, $b->getStatusCode());
            $first = EditorialFixture::json($a);
            $second = EditorialFixture::json($b);
            $created = [$first['id'], $second['id']];
            $this->assertIsInt($first['id']);
            $this->assertIsInt($second['id']);
            $this->assertSame($first, [...$second, 'id' => $first['id']]);
            $id = $first['id'];
            $adminPath = Fixture::API.'/'.$id;
            $publicPath = Fixture::PUBLIC_API.'/'.$id;
            $published = Fixture::payload(['estado' => 'PUBLICADA', 'titulo' => 'Publicación sintética de paridad', 'fechaPublicacion' => '2026-04-10']);

            $this->assertJsonParity(400, $spring->post(Fixture::API, ['json' => $published]), $laravel->post(Fixture::API, ['json' => $published]));
            $this->assertJsonParity(400, $spring->put($adminPath, ['json' => $published]), $laravel->put($adminPath, ['json' => $published]));
            $invalid = Fixture::payload(['tipo' => 'NO_EXISTE', 'titulo' => '   ']);
            $this->assertJsonParity(400, $spring->put($adminPath, ['json' => $invalid]), $laravel->put($adminPath, ['json' => $invalid]));

            Fixture::attach($db, $id, 760, 'Adjunto sintético de paridad.pdf');
            $this->assertJsonParity(200, $spring->put($adminPath, ['json' => $published]), $laravel->put($adminPath, ['json' => $published]));
            $this->assertJsonParity(200, $springPublic->get($publicPath), $laravelPublic->get($publicPath));
            foreach ([$spring, $laravel] as $backend) {
                $archive = $backend->delete($adminPath);
                $this->assertSame(204, $archive->getStatusCode());
                $this->assertSame('', (string) $archive->getBody());
            }
            $this->assertJsonParity(200, $spring->get($adminPath), $laravel->get($adminPath));
            $this->assertJsonParity(404, $springPublic->get($publicPath), $laravelPublic->get($publicPath));
        } finally {
            foreach ($created as $id) {
                $db->prepare('DELETE FROM archivos_hacienda WHERE publicacion_hacienda_id=?')->execute([$id]);
                $db->prepare('DELETE FROM publicaciones_hacienda WHERE id=?')->execute([$id]);
            }
        }

        foreach ([$springPublic, EditorialFixture::browser()] as $anonymous) {
            $this->assertSame(401, $anonymous->get(Fixture::API)->getStatusCode());
        }
        $press = EditorialFixture::browser();
        $this->assertSame(200, EditorialFixture::login($press, 'prensa@example.test')->getStatusCode());
        $this->assertSame(403, $press->get(Fixture::API)->getStatusCode());
        $this->assertSame(403, $this->springActor($springPublic, $options, 'prensa')->get(Fixture::API)->getStatusCode());
    }

    private function springActor(Client $client, array $options, string $role): Client
    {
        $login = EditorialFixture::login($client, $role.'@example.test');
        $this->assertSame(200, $login->getStatusCode());

        return new Client([...$options, 'headers' => [...$options['headers'], 'Authorization' => 'Bearer '.EditorialFixture::json($login)['token']]]);
    }

    private function assertJsonParity(int $status, ResponseInterface $spring, ResponseInterface $laravel): void
    {
        $this->assertSame($status, $spring->getStatusCode(), (string) $spring->getBody());
        $this->assertSame($status, $laravel->getStatusCode(), (string) $laravel->getBody());
        $expected = EditorialFixture::json($spring);
        $actual = EditorialFixture::json($laravel);
        if ($status >= 400) {
            foreach ([$expected, $actual] as $error) {
                $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d+Z$/D', $error['timestamp']);
            }
            unset($expected['timestamp'], $actual['timestamp']);
        }
        $this->assertSame($expected, $actual);
    }
}
