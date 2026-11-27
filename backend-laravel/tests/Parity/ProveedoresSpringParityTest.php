<?php

namespace Tests\Parity;

use App\Proveedores\ProviderStore;
use GuzzleHttp\Client;
use PHPUnit\Framework\TestCase;
use Tests\Fixtures\HaciendaDocuments;
use Tests\Fixtures\ProviderDocuments as Fixture;
use Tests\Http\DisposableDatabase;
use Tests\Http\EditorialFixture;
use Tests\Http\LocalHttps;

class ProveedoresSpringParityTest extends TestCase
{
    public function test_live_endpoints_preserve_contracts_and_historical_urls(): void
    {
        $db = DisposableDatabase::connect();
        $db->exec('DELETE FROM auth_login_windows');
        $laravel = EditorialFixture::browser();
        $this->assertSame(200, EditorialFixture::login($laravel, 'hacienda@example.test')->getStatusCode());
        $options = ['base_uri' => 'http://spring:8080', 'http_errors' => false, 'headers' => ['Accept' => 'application/json']];
        $springPublic = new Client($options);
        $login = EditorialFixture::login($springPublic, 'hacienda@example.test');
        $this->assertSame(200, $login->getStatusCode());
        $spring = new Client([...$options, 'headers' => [...$options['headers'], 'Authorization' => 'Bearer '.EditorialFixture::json($login)['token']]]);
        $public = LocalHttps::client();
        $created = [];
        try {
            foreach ([[$spring, HaciendaDocuments::pdf(), 'Planilla de prueba.pdf'], [$laravel, Fixture::xlsx(), 'Planilla.xlsx'],
                [$laravel, Fixture::compound(), 'Ejemplo.doc'], [$spring, Fixture::compound('xls'), 'Ejemplo.xls']] as [$actor, $bytes, $name]) {
                $response = $actor->post(Fixture::ADMIN, Fixture::upload($bytes, $name));
                $this->assertSame(201, $response->getStatusCode());
                $created[] = $row = EditorialFixture::json($response);
                $this->assertSame($name, $row['nombreOriginal']);
                $this->assertSame(strlen($bytes), $row['tamanio']);
                $this->assertSame(EditorialFixture::json($spring->get(Fixture::ADMIN)), EditorialFixture::json($laravel->get(Fixture::ADMIN)));
                $this->assertSame(EditorialFixture::json($springPublic->get(Fixture::PUBLIC)), EditorialFixture::json($public->get(Fixture::PUBLIC)));
                if ($actor === $spring) {
                    $expected = $springPublic->get($row['url']);
                    $this->assertSame(200, $expected->getStatusCode());
                    $path = ProviderStore::DIRECTORY.'/'.basename($row['url']);
                    file_put_contents($path, (string) $expected->getBody());
                    chmod($path, 0600);
                    $actual = $public->get($row['url']);
                    $this->assertSame(200, $actual->getStatusCode());
                    $this->assertSame((string) $expected->getBody(), (string) $actual->getBody());
                    foreach (['Content-Disposition', 'Content-Type', 'Content-Length', 'X-Content-Type-Options'] as $header) {
                        $this->assertSame($expected->getHeaderLine($header), $actual->getHeaderLine($header), $header);
                    }
                }
            }
            foreach ([['POST', Fixture::ADMIN, Fixture::upload('texto', 'invalido.pdf'), 400],
                ['PUT', Fixture::ADMIN.'/orden', ['json' => ['ids' => [1234]]], 400],
                ['DELETE', Fixture::ADMIN.'/1234', [], 404]] as [$method, $url, $body, $code]) {
                $expected = $spring->request($method, $url, $body);
                $actual = $laravel->request($method, $url, $body);
                $this->assertSame($code, $expected->getStatusCode());
                $this->assertSame($code, $actual->getStatusCode());
                $springError = EditorialFixture::json($expected);
                $laravelError = EditorialFixture::json($actual);
                unset($springError['timestamp'], $laravelError['timestamp']);
                $this->assertSame($springError, $laravelError);
            }
            $ids = array_reverse(array_column($created, 'id'));
            $order = ['json' => ['ids' => $ids]];
            $expected = $spring->put(Fixture::ADMIN.'/orden', $order);
            $actual = $laravel->put(Fixture::ADMIN.'/orden', $order);
            $this->assertSame(200, $expected->getStatusCode());
            $this->assertSame(200, $actual->getStatusCode());
            $this->assertSame(EditorialFixture::json($expected), EditorialFixture::json($actual));
            $path = Fixture::ADMIN.'/'.$created[0]['id'];
            $payload = ['json' => ['titulo' => ' Editado ', 'descripcion' => ' Resumen ', 'activo' => false]];
            $this->assertSame(EditorialFixture::json($spring->put($path, $payload)), EditorialFixture::json($laravel->put($path, $payload)));
            foreach ([$springPublic, $public, $spring, $laravel] as $client) {
                $this->assertSame(404, $client->get($created[0]['url'])->getStatusCode());
            }
            $this->assertSame(204, $laravel->delete($path)->getStatusCode());
            $this->assertSame(204, $spring->delete(Fixture::ADMIN.'/'.$created[1]['id'])->getStatusCode());
            $this->assertSame(EditorialFixture::json($spring->get(Fixture::ADMIN)), EditorialFixture::json($laravel->get(Fixture::ADMIN)));
        } finally {
            foreach ($created as $document) {
                $db->prepare('DELETE FROM documentos_proveedores WHERE id=?')->execute([$document['id']]);
                @unlink(ProviderStore::DIRECTORY.'/'.basename($document['url']));
            }
        }
    }
}
