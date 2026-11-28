<?php

namespace Tests\Parity;

use App\Hacienda\DocumentStore;
use GuzzleHttp\Client;
use PHPUnit\Framework\TestCase;
use Tests\Fixtures\HaciendaDocuments as Documents;
use Tests\Fixtures\HaciendaFixtures as Fixture;
use Tests\Fixtures\NewsImageFixtures;
use Tests\Http\DisposableDatabase;
use Tests\Http\EditorialFixture;
use Tests\Http\LocalHttps;

class HaciendaDocumentsSpringParityTest extends TestCase
{
    public function test_document_contracts_and_historical_urls_match_spring(): void
    {
        $db = DisposableDatabase::connect();
        $db->exec('DELETE FROM auth_login_windows');
        $laravel = EditorialFixture::browser();
        $this->assertSame(200, EditorialFixture::login($laravel, 'hacienda@example.test')->getStatusCode());
        $options = ['base_uri' => 'http://spring:8080', 'http_errors' => false, 'timeout' => 20, 'headers' => ['Accept' => 'application/json']];
        $springPublic = new Client($options);
        $login = EditorialFixture::login($springPublic, 'hacienda@example.test');
        $this->assertSame(200, $login->getStatusCode());
        $spring = new Client([...$options, 'headers' => [...$options['headers'], 'Authorization' => 'Bearer '.EditorialFixture::json($login)['token']]]);
        $public = LocalHttps::client();

        $created = $laravel->post(Fixture::API, ['json' => Fixture::payload()]);
        $this->assertSame(201, $created->getStatusCode());
        $id = EditorialFixture::json($created)['id'];
        $path = Fixture::API.'/'.$id;
        $imported = [];
        try {
            $docs = [];
            foreach ([
                [Documents::pdf(), 'application/pdf'],
                [NewsImageFixtures::bytes('jpg'), 'image/jpeg'],
                [NewsImageFixtures::bytes('png'), 'image/png'],
            ] as [$input, $mime]) {
                $response = Documents::upload($spring, $id, $input, 'Nombre sintético.txt');
                $this->assertSame(201, $response->getStatusCode());
                $row = EditorialFixture::json($response);
                $files = $row['archivos'];
                $docs[] = $file = end($files);
                $this->assertSame(['id', 'nombreOriginal', 'url', 'tipoMime', 'orden', 'tamanio'], array_keys($file));
                $this->assertSame('Nombre sintético.txt', $file['nombreOriginal']);
                $this->assertSame($mime, $file['tipoMime']);
                $this->assertSame($row, EditorialFixture::json($laravel->get($path)));
                $adminPath = $path.'/archivos/'.$file['id'].'/contenido';
                $download = $spring->get($adminPath);
                $this->assertSame(200, $download->getStatusCode());
                $bytes = (string) $download->getBody();
                $name = basename($file['url']);
                $imported[] = $target = DocumentStore::DIRECTORY.'/'.$name;
                $this->assertSame(strlen($bytes), file_put_contents($target, $bytes));
                chmod($target, 0600);
                $this->assertDownloadParity($spring, $laravel, $adminPath);
                $this->assertSame(404, $springPublic->get($file['url'])->getStatusCode());
                $this->assertSame(404, $public->get($file['url'])->getStatusCode());
            }
            foreach ([$spring, $laravel] as $client) {
                $this->assertSame(400, Documents::upload($client, $id, '%PDF-1.4')->getStatusCode());
                $this->assertSame(415, $client->post($path.'/archivos', ['json' => []])->getStatusCode());
                $this->assertSame(400, $client->put($path.'/archivos/orden', ['json' => ['ids' => [$docs[0]['id']]]])->getStatusCode());
                $this->assertSame(200, $client->put($path, ['json' => Fixture::payload(['estado' => 'PUBLICADA'])])->getStatusCode());
            }
            $this->assertDownloadParity($springPublic, $public, $docs[0]['url']);
            $this->assertDownloadParity($springPublic, $public, $docs[0]['url'].'?descargar=true');
            $this->assertDownloadParity($springPublic, $public, $docs[0]['url'], 'bytes=1-4');

            $order = ['json' => ['ids' => array_reverse(array_column($docs, 'id'))]];
            $expected = $spring->put($path.'/archivos/orden', $order);
            $actual = $laravel->put($path.'/archivos/orden', $order);
            $this->assertSame(200, $expected->getStatusCode());
            $this->assertSame(200, $actual->getStatusCode());
            $this->assertSame(EditorialFixture::json($expected), EditorialFixture::json($actual));

            $extra = Documents::upload($laravel, $id, Documents::pdf(), 'Adicional sintético.pdf');
            $this->assertSame(201, $extra->getStatusCode());
            $row = EditorialFixture::json($extra);
            $last = $row['archivos'][3];
            $this->assertSame($row, EditorialFixture::json($spring->get($path)));
            $this->assertSame(strlen(Documents::pdf()), $last['tamanio']);
            $this->assertSame(200, $laravel->delete($path.'/archivos/'.$last['id'])->getStatusCode());
            $this->assertSame(404, $public->get($last['url'])->getStatusCode());
            $this->assertSame(200, $spring->delete($path.'/archivos/'.$docs[2]['id'])->getStatusCode());
            $this->assertSame(404, $public->get($docs[2]['url'])->getStatusCode());
            $this->assertSame(200, $laravel->delete($path.'/archivos/'.$docs[1]['id'])->getStatusCode());
            foreach ([$spring, $laravel] as $backend) {
                $this->assertSame(400, $backend->delete($path.'/archivos/'.$docs[0]['id'])->getStatusCode());
            }
            $this->assertSame(204, $laravel->delete($path)->getStatusCode());
            $this->assertSame(404, $springPublic->get($docs[0]['url'])->getStatusCode());
            $this->assertSame(404, $public->get($docs[0]['url'])->getStatusCode());
        } finally {
            foreach ($imported as $file) {
                @unlink($file);
            }
            $db->prepare('DELETE FROM archivos_hacienda WHERE publicacion_hacienda_id=?')->execute([$id]);
            $db->prepare('DELETE FROM publicaciones_hacienda WHERE id=?')->execute([$id]);
        }
    }

    private function assertDownloadParity(Client $spring, Client $laravel, string $path, ?string $range = null): void
    {
        $options = $range === null ? [] : ['headers' => ['Range' => $range]];
        $expected = $spring->get($path, $options);
        $actual = $laravel->get($path, $options);
        $this->assertSame($range === null ? 200 : 206, $expected->getStatusCode());
        $this->assertSame($expected->getStatusCode(), $actual->getStatusCode());
        $this->assertSame((string) $expected->getBody(), (string) $actual->getBody());
        foreach (['Content-Type', 'Content-Length', 'Content-Disposition', 'Content-Security-Policy', 'X-Content-Type-Options', 'Accept-Ranges', 'Content-Range'] as $header) {
            $this->assertSame($expected->getHeaderLine($header), $actual->getHeaderLine($header), $header);
        }
        foreach ([$expected, $actual] as $response) {
            $this->assertContains('no-store', array_map('trim', explode(',', $response->getHeaderLine('Cache-Control'))));
        }
    }
}
