<?php

namespace Tests\Http;

use App\Proveedores\ProviderStore;
use GuzzleHttp\Client;
use GuzzleHttp\Promise\Utils;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Fixtures\HaciendaDocuments;
use Tests\Fixtures\ProviderDocuments as Fixture;

class ProveedoresHttpTest extends TestCase
{
    private PDO $db;

    private array $created = [];

    protected function setUp(): void
    {
        $this->db = DisposableDatabase::connect();
        $this->db->exec('DELETE FROM auth_login_windows');
    }

    protected function tearDown(): void
    {
        foreach ($this->created as $doc) {
            $this->db->prepare('DELETE FROM documentos_proveedores WHERE id=?')->execute([$doc['id']]);
            @unlink(ProviderStore::DIRECTORY.'/'.basename($doc['url']));
        }
    }

    private function actor(string $role = 'hacienda'): Client
    {
        $client = EditorialFixture::browser();
        $this->assertSame(200, EditorialFixture::login($client, $role.'@example.test')->getStatusCode());

        return $client;
    }

    private function record($response): array
    {
        $this->assertSame(201, $response->getStatusCode(), (string) $response->getBody());

        return $this->created[] = EditorialFixture::json($response);
    }

    public function test_contract_lifecycle_order_and_private_access(): void
    {
        $actor = $this->actor();
        $public = LocalHttps::client();
        $first = $this->record($actor->post(Fixture::ADMIN, Fixture::upload(HaciendaDocuments::pdf(), 'Planilla ñ.pdf')));
        $second = $this->record($actor->post(Fixture::ADMIN, Fixture::upload(Fixture::xlsx(), 'Planilla.xlsx')));
        $this->assertSame(['id', 'titulo', 'descripcion', 'nombreOriginal', 'url', 'tipoMime', 'tamanio', 'orden', 'activo'], array_keys($first));
        $this->assertSame('Descripción sintética', $first['descripcion']);
        $this->assertTrue($first['activo']);
        $this->assertSame([$first, $second], EditorialFixture::json($public->get(Fixture::PUBLIC)));
        $response = $public->get($first['url']);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(HaciendaDocuments::pdf(), (string) $response->getBody());
        $this->assertStringContainsString('Planilla%20%C3%B1.pdf', $response->getHeaderLine('Content-Disposition'));
        $this->assertSame('nosniff', $response->getHeaderLine('X-Content-Type-Options'));
        $this->assertStringContainsString('no-store', $response->getHeaderLine('Cache-Control'));
        $this->assertSame(206, $public->get($first['url'], ['headers' => ['Range' => 'bytes=0-4']])->getStatusCode());
        $this->assertSame('', (string) $public->head($first['url'])->getBody());
        $path = ProviderStore::DIRECTORY.'/'.basename($first['url']);
        $this->assertSame(0600, fileperms($path) & 0777);
        rename($path, $path.'.synthetic');
        try {
            $this->assertTrue(symlink($path.'.synthetic', $path));
            $this->assertSame(404, $public->get($first['url'])->getStatusCode());
        } finally {
            @unlink($path);
            rename($path.'.synthetic', $path);
        }
        $id = Fixture::ADMIN.'/'.$first['id'];
        $updated = $actor->put($id, ['json' => ['titulo' => ' Editado ', 'descripcion' => '', 'activo' => false]]);
        $this->assertSame(200, $updated->getStatusCode());
        $hidden = EditorialFixture::json($updated);
        $this->assertSame([$hidden, $second], EditorialFixture::json($actor->get(Fixture::ADMIN)));
        $this->assertSame([$second], EditorialFixture::json($public->get(Fixture::PUBLIC)));
        $this->assertSame(404, $public->get($first['url'])->getStatusCode());
        $this->assertSame(404, $actor->get($first['url'])->getStatusCode());
        $this->assertSame(404, $public->get('/uploads/proveedores/%2e%2e%2f'.basename($first['url']))->getStatusCode());
        foreach ([[$first['id']], [$first['id'], $first['id']], [$first['id'], -1]] as $ids) {
            $this->assertSame(400, $actor->put(Fixture::ADMIN.'/orden', ['json' => ['ids' => $ids]])->getStatusCode());
        }
        $result = $actor->put(Fixture::ADMIN.'/orden', ['json' => ['ids' => [$second['id'], $first['id']]]]);
        $this->assertSame(200, $result->getStatusCode());
        $this->assertSame([$second['id'], $first['id']], array_column(EditorialFixture::json($result), 'id'));
        $this->assertSame(204, $actor->delete(Fixture::ADMIN.'/'.$second['id'])->getStatusCode());
        $this->assertSame(404, $public->get($second['url'])->getStatusCode());
        $this->assertSame(0, EditorialFixture::json($actor->get(Fixture::ADMIN))[0]['orden']);
        $this->assertSame(404, $actor->delete(Fixture::ADMIN.'/'.$second['id'])->getStatusCode());
    }

    public function test_formats_bounds_and_failure_rollback(): void
    {
        $actor = $this->actor('super_admin');
        foreach (['doc', 'xls'] as $extension) {
            $bytes = Fixture::compound($extension);
            $file = $this->record($actor->post(Fixture::ADMIN, Fixture::upload($bytes, 'Prueba.'.$extension)));
            $this->assertSame($bytes, (string) $actor->get($file['url'])->getBody());
        }
        $before = scandir(ProviderStore::DIRECTORY);
        foreach ([['', 'x.pdf'], [HaciendaDocuments::pdf(), 'x.php'], ['%PDF-1.4', 'x.pdf'], ['PK', 'x.xlsx'],
            [Fixture::xlsx(false), 'x.xlsx'], [substr(Fixture::compound(), 0, 8), 'x.doc'], [Fixture::compound(), 'x.xls'],
            [HaciendaDocuments::pdf(ProviderStore::MAX_BYTES + 1), 'x.pdf']] as [$bytes, $name]) {
            $this->assertSame(400, $actor->post(Fixture::ADMIN, Fixture::upload($bytes, $name))->getStatusCode());
        }
        $this->assertSame(400, $actor->post(Fixture::ADMIN, Fixture::upload(HaciendaDocuments::pdf(), title: str_repeat('😀', 91)))->getStatusCode());
        $this->assertSame(415, $actor->post(Fixture::ADMIN, ['json' => []])->getStatusCode());
        $this->assertSame($before, scandir(ProviderStore::DIRECTORY));
        $trigger = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql'
            ? "FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'synthetic-providers-rollback'"
            : "BEGIN SELECT RAISE(ABORT, 'synthetic-providers-rollback'); END";
        $this->db->exec('CREATE TRIGGER stage7_fail_insert BEFORE INSERT ON documentos_proveedores '.$trigger);
        try {
            $this->assertSame(500, $actor->post(Fixture::ADMIN, Fixture::upload(HaciendaDocuments::pdf()))->getStatusCode());
            $this->assertSame($before, scandir(ProviderStore::DIRECTORY));
        } finally {
            $this->db->exec('DROP TRIGGER stage7_fail_insert');
        }
        $boundary = $this->record($actor->post(Fixture::ADMIN, Fixture::upload(HaciendaDocuments::pdf(ProviderStore::MAX_BYTES))));
        $this->assertSame(ProviderStore::MAX_BYTES, $boundary['tamanio']);
    }

    public function test_permissions_csrf_and_parallel_creation(): void
    {
        $anonymous = EditorialFixture::browser();
        $press = $this->actor('prensa');
        $this->assertSame(401, $anonymous->get(Fixture::ADMIN)->getStatusCode());
        $this->assertSame(403, $press->get(Fixture::ADMIN)->getStatusCode());
        $this->assertSame(403, $press->post(Fixture::ADMIN, Fixture::upload(HaciendaDocuments::pdf()))->getStatusCode());
        $this->assertSame(403, $press->delete(Fixture::ADMIN.'/1')->getStatusCode());
        $actor = $this->actor();
        $super = $this->actor('super_admin');
        $options = Fixture::upload(HaciendaDocuments::pdf());
        $this->assertSame(403, $actor->post(Fixture::ADMIN, [...$options, 'headers' => ['Sec-Fetch-Site' => 'cross-site']])->getStatusCode());
        $results = Utils::unwrap([$actor->postAsync(Fixture::ADMIN, $options), $super->postAsync(Fixture::ADMIN, $options)]);
        $orders = array_map(fn ($response) => $this->record($response)['orden'], $results);
        sort($orders);
        $this->assertSame([0, 1], $orders);
        $actor->post('/api/auth/logout');
        $this->assertSame(401, $actor->get(Fixture::ADMIN)->getStatusCode());
    }
}
