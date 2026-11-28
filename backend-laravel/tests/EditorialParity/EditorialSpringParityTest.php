<?php

namespace Tests\EditorialParity;

use GuzzleHttp\Client;
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Tests\Http\DisposableDatabase;
use Tests\Http\EditorialFixture;

class EditorialSpringParityTest extends TestCase
{
    private PDO $db;

    private Client $laravel;

    private Client $spring;

    private static ?Client $springPress = null;

    protected function setUp(): void
    {
        $this->db = DisposableDatabase::connect();
        $this->db->exec('DELETE FROM auth_login_windows');
        $this->laravel = EditorialFixture::browser();
        $this->assertSame(200, EditorialFixture::login($this->laravel, 'prensa@example.test')->getStatusCode());
        $this->spring = self::$springPress ??= $this->springActor('prensa@example.test');
    }

    private function springActor(string $email): Client
    {
        $options = ['base_uri' => 'http://spring:8080', 'http_errors' => false, 'timeout' => 15];
        $login = EditorialFixture::login(new Client($options), $email);
        $this->assertSame(200, $login->getStatusCode());

        return new Client([...$options, 'headers' => ['Accept' => 'application/json', 'Authorization' => 'Bearer '.EditorialFixture::json($login)['token']]]);
    }

    public function test_six_routes_and_editorial_transitions_return_the_same_contract(): void
    {
        foreach (['', '/61', '/62', '/auditoria'] as $suffix) {
            $path = EditorialFixture::API.$suffix;
            $this->assertJsonSame($this->spring->get($path), $this->laravel->get($path), 200);
        }

        $payload = EditorialFixture::payload(['titulo' => 'Crónica de comparación ñ '.bin2hex(random_bytes(4))]);
        $spring = $this->workflow($this->spring, $payload);
        $laravel = $this->workflow($this->laravel, $payload);
        $this->assertSame($spring, $laravel);
    }

    private function workflow(Client $editor, array $payload): array
    {
        $responses = [];
        $ids = [];
        $root = EditorialFixture::API;
        try {
            for ($i = 0; $i < 2; $i++) {
                $response = $editor->post($root, ['json' => $payload]);
                $this->assertSame(201, $response->getStatusCode(), (string) $response->getBody());
                $row = EditorialFixture::json($response);
                $this->assertIsInt($row['id']);
                $ids[] = $row['id'];
                $this->assertJsonSame($this->spring->get($root.'/'.$row['id']), $this->laravel->get($root.'/'.$row['id']), 200);
                unset($row['id']);
                $responses[] = $this->normalize($row);
            }
            $this->assertSame($responses[0]['slug'].'-2', $responses[1]['slug']);
            $item = $root.'/'.$ids[0];
            foreach (['BORRADOR', 'PUBLICADA', 'BORRADOR', 'ARCHIVADA', 'PUBLICADA'] as $state) {
                $update = [...$payload, 'titulo' => $payload['titulo'].' editada', 'estado' => $state];
                if ($state === 'PUBLICADA') {
                    unset($update['imagenes']);
                }
                $response = $editor->put($item, ['json' => $update]);
                $this->assertSame(200, $response->getStatusCode());
                $row = EditorialFixture::json($response);
                $this->assertSame($ids[0], $row['id']);
                $this->assertSame($responses[0]['slug'], $row['slug']);
                unset($row['id']);
                $responses[] = $this->normalize($row);
            }
            for ($i = 0; $i < 2; $i++) {
                $deleted = $editor->delete($item);
                $this->assertSame(204, $deleted->getStatusCode());
                $this->assertSame('', (string) $deleted->getBody());
            }
            $this->assertJsonSame($this->spring->get($item), $this->laravel->get($item), 200);
            $springAudit = $this->spring->get($root.'/auditoria');
            $this->assertJsonSame($springAudit, $this->laravel->get($root.'/auditoria'), 200);
            $events = array_values(array_filter(EditorialFixture::json($springAudit), fn (array $row): bool => $row['noticiaId'] === $ids[0]));
            $this->assertSame(['ARCHIVADO', 'ARCHIVADO', 'PUBLICACION', 'DESPUBLICACION', 'DESPUBLICACION', 'PUBLICACION', 'EDICION', 'CREACION'], array_column($events, 'accion'));
            foreach ($events as $event) {
                $this->assertIsInt($event['id']);
                $this->assertMatchesRegularExpression('/^\d{4}-\d\d-\d\dT\d\d:\d\d:\d\d(?:\.\d{3}(?:\d{3})?)?Z$/', $event['fecha']);
                unset($event['id'], $event['noticiaId'], $event['fecha']);
                $responses[] = $this->normalize($event);
            }
        } finally {
            foreach ($ids as $id) {
                EditorialFixture::remove($this->db, $this->db, $id);
            }
        }

        return $responses;
    }

    public static function errorRequests(): array
    {
        return [
            ['GET', '/no-es-id', null, 400], ['GET', '/9223372036854775808', null, 400],
            ['GET', '/999999', null, 404], ['PUT', '/999999', [], 404], ['DELETE', '/999999', null, 404],
            ['POST', '', ['titulo' => ' '], 400], ['POST', '', ['titulo' => str_repeat('a', 256)], 400],
            ['POST', '', ['estado' => 'publicada'], 400], ['POST', '', ['imagen' => 'http://example.test/a.png'], 400],
            ['POST', '', ['imagenes' => ['/assets/otra.jpg']], 400],
            ['POST', '', ['imagenes' => array_fill(0, 7, '/assets/ensenada-hero.jpg')], 400],
        ];
    }

    #[DataProvider('errorRequests')]
    public function test_spring_error_codes_and_json(string $method, string $suffix, ?array $payload, int $status): void
    {
        $path = EditorialFixture::API.$suffix;
        $options = $payload === null ? [] : ['json' => EditorialFixture::payload($payload)];
        $spring = $this->spring->request($method, $path, $options);
        $laravel = $this->laravel->request($method, $path, $options);
        $this->assertSame($status, $spring->getStatusCode());
        $this->assertSame($status, $laravel->getStatusCode());
        $expected = EditorialFixture::json($spring);
        $actual = EditorialFixture::json($laravel);
        foreach ([$expected, $actual] as $error) {
            $this->assertMatchesRegularExpression('/^\d{4}-\d\d-\d\dT\d\d:\d\d:\d\d\.\d{3}Z$/', $error['timestamp']);
        }
        unset($expected['timestamp'], $actual['timestamp']);
        $this->assertSame($this->normalize($expected), $this->normalize($actual));
    }

    public function test_authorization_codes_match_for_a_missing_session_and_hacienda(): void
    {
        $spring = new Client(['base_uri' => 'http://spring:8080', 'http_errors' => false]);
        $this->assertSame(401, $spring->get(EditorialFixture::API)->getStatusCode());
        $this->assertSame(401, EditorialFixture::browser()->get(EditorialFixture::API)->getStatusCode());
        $hacienda = EditorialFixture::browser();
        $this->assertSame(200, EditorialFixture::login($hacienda, 'hacienda@example.test')->getStatusCode());
        $this->assertSame(403, $this->springActor('hacienda@example.test')->get(EditorialFixture::API)->getStatusCode());
        $this->assertSame(403, $hacienda->get(EditorialFixture::API)->getStatusCode());
    }

    private function assertJsonSame(ResponseInterface $spring, ResponseInterface $laravel, int $status): void
    {
        $this->assertSame($status, $spring->getStatusCode());
        $this->assertSame($status, $laravel->getStatusCode());
        $this->assertSame($this->normalize(EditorialFixture::json($spring)), $this->normalize(EditorialFixture::json($laravel)));
    }

    private function normalize(array $value): array
    {
        if (! array_is_list($value)) {
            ksort($value);
        }

        return array_map(fn (mixed $item): mixed => is_array($item) ? $this->normalize($item) : $item, $value);
    }
}
