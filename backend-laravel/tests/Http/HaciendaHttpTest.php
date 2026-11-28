<?php

namespace Tests\Http;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\CurlMultiHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Promise\Utils;
use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;
use Tests\Fixtures\HaciendaFixtures as Fixture;

class HaciendaHttpTest extends TestCase
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
        foreach ($this->created as $id) {
            $this->db->prepare('DELETE FROM archivos_hacienda WHERE publicacion_hacienda_id=?')->execute([$id]);
            $this->db->prepare('DELETE FROM publicaciones_hacienda WHERE id=?')->execute([$id]);
        }
    }

    private function actor(string $role = 'hacienda', ?HandlerStack $handler = null): Client
    {
        $client = EditorialFixture::browser($handler);
        $this->assertSame(200, EditorialFixture::login($client, $role.'@example.test')->getStatusCode());

        return $client;
    }

    private function create(Client $actor, array $overrides = []): array
    {
        $response = $actor->post(Fixture::API, ['json' => Fixture::payload($overrides)]);
        $this->assertSame(201, $response->getStatusCode(), (string) $response->getBody());
        $row = EditorialFixture::json($response);
        $this->created[] = $row['id'];

        return $row;
    }

    public function test_public_snapshot_excludes_private_metadata_and_preserves_historical_order(): void
    {
        $public = LocalHttps::client();
        $admin = $this->actor();
        $list = $public->get(Fixture::PUBLIC_API);
        $this->assertSame(200, $list->getStatusCode());
        $this->assertStringContainsString('no-store', $list->getHeaderLine('Cache-Control'));
        $raw = (string) $list->getBody();
        foreach (['Borrador sintético reservado', 'Archivo sintético reservado', '000000000454.pdf', '000000000455.jpg'] as $private) {
            $this->assertStringNotContainsString($private, $raw);
        }
        $rows = EditorialFixture::json($list);
        $this->assertSame([304, 301], array_column($rows, 'id'));
        $this->assertSame([
            'id' => 301, 'titulo' => 'Informe público sintético', 'descripcion' => 'Descripción pública sintética.',
            'tipo' => 'SITUACION_ECONOMICO_FINANCIERA', 'fechaPublicacion' => '2026-04-03', 'estado' => 'PUBLICADA',
            'archivos' => [
                ['id' => 453, 'nombreOriginal' => 'Público primero.pdf', 'url' => '/uploads/hacienda/00000000-0000-4000-8000-000000000453.pdf', 'tipoMime' => 'application/pdf', 'orden' => 0, 'tamanio' => 4549],
                ['id' => 451, 'nombreOriginal' => 'Público segundo.png', 'url' => '/uploads/hacienda/00000000-0000-4000-8000-000000000451.png', 'tipoMime' => 'image/png', 'orden' => 1, 'tamanio' => 4547],
            ],
        ], $rows[1]);
        $this->assertSame($rows[1], EditorialFixture::json($public->get(Fixture::PUBLIC_API.'/000301')));
        foreach ([302, 303, 99999, -1] as $id) {
            $response = $public->get(Fixture::PUBLIC_API.'/'.$id);
            $this->assertSame(404, $response->getStatusCode());
            $this->assertArrayNotHasKey('archivos', EditorialFixture::json($response));
        }
        $all = EditorialFixture::json($admin->get(Fixture::API));
        $this->assertSame([302, 304, 301, 303], array_column($all, 'id'));
        $this->assertSame([452, 454], array_column($all[0]['archivos'], 'id'));
        $this->assertSame($all[0], EditorialFixture::json($admin->get(Fixture::API.'/302')));
        $this->assertSame(404, $public->get($rows[1]['archivos'][0]['url'])->getStatusCode());
        $this->assertSame(415, $admin->post(Fixture::API.'/302/archivos')->getStatusCode());
        $this->assertSame(404, $admin->get(Fixture::API.'/302/archivos/452/contenido')->getStatusCode());
        $this->assertSame(415, $admin->put(Fixture::API.'/302/archivos/orden')->getStatusCode());
        $this->assertSame(404, $admin->delete(Fixture::API.'/303/archivos/452')->getStatusCode());
    }

    public function test_draft_edit_publish_archive_and_republish_preserve_the_document_rows(): void
    {
        $operator = $this->actor();
        $super = $this->actor('super_admin');
        $public = LocalHttps::client();
        $row = $this->create($operator, ['titulo' => " \tPresupuesto Ñandú 😀  ", 'descripcion' => ' ', 'id' => 999, 'archivos' => [['id' => 453]]]);
        $id = $row['id'];
        $this->assertNotSame(999, $id);
        $this->assertSame('Presupuesto Ñandú 😀', $row['titulo']);
        $this->assertSame('', $row['descripcion']);
        $this->assertSame([], $row['archivos']);
        $path = Fixture::API.'/'.$id;
        $publicPath = Fixture::PUBLIC_API.'/'.$id;
        $payload = Fixture::payload(['estado' => 'PUBLICADA', 'titulo' => 'Publicación sintética editada', 'tipo' => 'CIERRE_EJERCICIO', 'fechaPublicacion' => '2099-03-02']);
        $this->assertSame(400, $super->post(Fixture::API, ['json' => $payload])->getStatusCode());
        $this->assertSame(400, $operator->put($path, ['json' => $payload])->getStatusCode());
        $this->assertSame($row, EditorialFixture::json($operator->get($path)));
        Fixture::attach($this->db, $id, 750);
        $published = $operator->put($path, ['json' => [...$payload, 'archivos' => []]]);
        $this->assertSame(200, $published->getStatusCode());
        $published = EditorialFixture::json($published);
        $this->assertSame([750], array_column($published['archivos'], 'id'));
        $this->assertSame($published, EditorialFixture::json($public->get($publicPath)));

        $archive = $super->delete($path);
        $this->assertSame(204, $archive->getStatusCode());
        $this->assertSame('', (string) $archive->getBody());
        $archived = EditorialFixture::json($super->get($path));
        $this->assertSame([...$published, 'estado' => 'ARCHIVADA'], $archived);
        $this->assertSame(404, $public->get($publicPath)->getStatusCode());
        $this->assertSame(204, $operator->delete($path)->getStatusCode());
        $this->assertSame($published, EditorialFixture::json($super->put($path, ['json' => $payload])));
        $this->assertSame(200, $public->get($publicPath)->getStatusCode());
        $this->assertSame('ARCHIVADA', $this->create($super, ['estado' => 'ARCHIVADA'])['estado']);
    }

    public function test_invalid_requests_do_not_modify_storage(): void
    {
        $actor = $this->actor();
        $before = EditorialFixture::json($actor->get(Fixture::API.'/302'));
        foreach ([
            ['titulo' => null], ['titulo' => '  '], ['titulo' => str_repeat('😀', 128)], ['descripcion' => 123],
            ['descripcion' => str_repeat('á', 10001)], ['tipo' => 'presupuesto'], ['estado' => 'INEXISTENTE'],
            ['fechaPublicacion' => '2026-02-29'], ['fechaPublicacion' => [2026, 4, 1]], ['fechaPublicacion' => '0999-04-01'],
        ] as $invalid) {
            $this->assertSame(400, $actor->put(Fixture::API.'/302', ['json' => Fixture::payload($invalid)])->getStatusCode());
        }
        $this->assertSame(400, $actor->post(Fixture::API, ['body' => '{mal', 'headers' => ['Content-Type' => 'application/json']])->getStatusCode());
        $this->assertSame(415, $actor->post(Fixture::API, ['body' => 'texto'])->getStatusCode());
        foreach (['invalido', '9223372036854775808'] as $id) {
            $this->assertSame(400, $actor->get(Fixture::API.'/'.$id)->getStatusCode());
            $this->assertSame(400, $actor->delete(Fixture::API.'/'.$id)->getStatusCode());
        }
        $this->assertSame(404, $actor->put(Fixture::API.'/99999', ['json' => Fixture::payload()])->getStatusCode());
        $this->assertSame(404, $actor->delete(Fixture::API.'/99999')->getStatusCode());
        $this->assertSame($before, EditorialFixture::json($actor->get(Fixture::API.'/302')));
    }

    public function test_roles_revocation_and_csrf_are_enforced(): void
    {
        $anonymous = EditorialFixture::browser();
        $press = $this->actor('prensa');
        $hacienda = $this->actor();
        $admin = $this->actor('super_admin');
        foreach ([
            ['GET', Fixture::API], ['GET', Fixture::API.'/302'], ['POST', Fixture::API],
            ['PUT', Fixture::API.'/302'], ['DELETE', Fixture::API.'/302'],
        ] as [$method, $path]) {
            $this->assertSame(401, $anonymous->request($method, $path)->getStatusCode());
            $this->assertSame(403, $press->request($method, $path, ['headers' => ['X-Role' => 'SUPER_ADMIN']])->getStatusCode());
        }
        $this->assertSame(200, $admin->get(Fixture::API)->getStatusCode());
        $this->assertSame(200, $press->get(Fixture::PUBLIC_API)->getStatusCode());
        foreach ([
            ['Sec-Fetch-Site' => ''],
            ['Origin' => 'https://localhost:19443', 'Sec-Fetch-Site' => 'same-site'],
        ] as $headers) {
            $this->assertSame(403, $hacienda->put(Fixture::API.'/302', ['headers' => $headers, 'json' => Fixture::payload()])->getStatusCode());
        }
        $token = EditorialFixture::json(EditorialFixture::login($hacienda, 'hacienda@example.test'))['token'];
        $bearer = ['Authorization' => 'Bearer '.$token];
        $this->db->exec("UPDATE usuarios SET activo=0 WHERE email='hacienda@example.test'");
        try {
            $this->assertSame(401, $hacienda->get(Fixture::API, ['headers' => $bearer])->getStatusCode());
        } finally {
            $this->db->exec("UPDATE usuarios SET activo=1 WHERE email='hacienda@example.test'");
        }
        $this->assertSame(401, $hacienda->post(Fixture::API, ['headers' => $bearer, 'json' => Fixture::payload()])->getStatusCode());
    }

    public function test_invalid_legacy_storage_names_are_never_exposed_or_counted(): void
    {
        $actor = $this->actor();
        $id = $this->create($actor)['id'];
        $this->db->prepare('INSERT INTO archivos_hacienda (publicacion_hacienda_id,nombre_original,nombre_almacenado,tipo_mime,orden,tamanio) VALUES (?,?,?,?,?,?)')
            ->execute([$id, 'Privado sintético', '../private/synthetic.pdf', 'application/pdf', 0, 10]);
        $published = Fixture::payload(['estado' => 'PUBLICADA']);
        $this->assertSame(400, $actor->put(Fixture::API.'/'.$id, ['json' => $published])->getStatusCode());
        $this->db->prepare("UPDATE publicaciones_hacienda SET estado='PUBLICADA' WHERE id=?")->execute([$id]);
        $public = LocalHttps::client();
        $this->assertSame(404, $public->get(Fixture::PUBLIC_API.'/'.$id)->getStatusCode());
        $this->assertNotContains($id, array_column(EditorialFixture::json($public->get(Fixture::PUBLIC_API)), 'id'));
        $admin = $actor->get(Fixture::API.'/'.$id);
        $this->assertSame([], EditorialFixture::json($admin)['archivos']);
        $this->assertStringNotContainsString('../private', (string) $admin->getBody());
        $this->assertSame(400, $actor->put(Fixture::API.'/'.$id, ['json' => $published])->getStatusCode());
        $this->assertSame(204, $actor->delete(Fixture::API.'/'.$id)->getStatusCode());
    }

    public function test_original_names_do_not_expose_directories_and_public_state_is_exact(): void
    {
        $actor = $this->actor();
        $id = $this->create($actor)['id'];
        Fixture::attach($this->db, $id, 755, 'C:\\private\\Histórico.pdf');
        $path = Fixture::API.'/'.$id;
        $publication = Fixture::payload(['estado' => 'PUBLICADA']);
        $response = $actor->put($path, ['json' => $publication]);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('Histórico.pdf', EditorialFixture::json($response)['archivos'][0]['nombreOriginal']);
        $this->assertStringNotContainsString('private', (string) $response->getBody());
        if ($this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
            $this->db->prepare("UPDATE publicaciones_hacienda SET estado='publicada' WHERE id=?")->execute([$id]);
            $this->assertSame(404, LocalHttps::client()->get(Fixture::PUBLIC_API.'/'.$id)->getStatusCode());
        }
    }

    public function test_publish_waits_for_a_transaction_that_removes_the_last_document(): void
    {
        $multi = new CurlMultiHandler(['select_timeout' => 0.03]);
        $actor = $this->actor(handler: HandlerStack::create($multi));
        $id = $this->create($actor)['id'];
        Fixture::attach($this->db, $id, 751);
        $this->db->beginTransaction();
        try {
            // Model the first half of a competing writer on an isolated synthetic row.
            $this->db->prepare('UPDATE publicaciones_hacienda SET estado=estado WHERE id=?')->execute([$id]);
            $this->db->prepare('DELETE FROM archivos_hacienda WHERE publicacion_hacienda_id=?')->execute([$id]);
            $pending = $actor->putAsync(Fixture::API.'/'.$id, ['json' => Fixture::payload(['estado' => 'PUBLICADA'])]);
            $until = hrtime(true) + 500_000_000;
            do {
                $multi->tick();
                usleep(10_000);
            } while ($pending->getState() === 'pending' && hrtime(true) < $until);
            $this->assertSame('pending', $pending->getState());
            $this->db->commit();
        } finally {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
        }
        $this->assertSame(400, $pending->wait()->getStatusCode());
        $row = EditorialFixture::json($actor->get(Fixture::API.'/'.$id));
        $this->assertSame('BORRADOR', $row['estado']);
        $this->assertSame([], $row['archivos']);
        $this->assertSame(404, LocalHttps::client()->get(Fixture::PUBLIC_API.'/'.$id)->getStatusCode());
    }

    public function test_concurrent_state_changes_preserve_the_document_and_public_snapshot(): void
    {
        $handler = HandlerStack::create();
        $actor = $this->actor(handler: $handler);
        $admin = $this->actor('super_admin', $handler);
        $id = $this->create($actor)['id'];
        Fixture::attach($this->db, $id, 752);
        $path = Fixture::API.'/'.$id;
        $public = LocalHttps::client(['handler' => $handler]);
        $publish = $actor->putAsync($path, ['json' => Fixture::payload(['estado' => 'PUBLICADA'])]);
        $archive = $admin->deleteAsync($path);
        $reads = array_map(fn () => $public->getAsync(Fixture::PUBLIC_API.'/'.$id), range(1, 6));
        [$published, $archived] = Utils::unwrap([$publish, $archive]);
        $this->assertSame(200, $published->getStatusCode());
        $this->assertSame(204, $archived->getStatusCode());
        foreach (Utils::unwrap($reads) as $read) {
            $this->assertContains($read->getStatusCode(), [200, 404]);
            if ($read->getStatusCode() === 200) {
                $this->assertSame(EditorialFixture::json($published), EditorialFixture::json($read));
            }
        }
        $final = EditorialFixture::json($admin->get($path));
        $this->assertContains($final['estado'], ['PUBLICADA', 'ARCHIVADA']);
        $this->assertSame([752], array_column($final['archivos'], 'id'));
        $this->assertSame($final['estado'] === 'PUBLICADA' ? 200 : 404, $public->get(Fixture::PUBLIC_API.'/'.$id)->getStatusCode());
    }

    public function test_foreign_key_and_rollback_keep_original_metadata_intact(): void
    {
        $actor = $this->actor();
        try {
            Fixture::attach($this->db, 99999999, 753);
            $this->fail('La FK debe rechazar documentos huérfanos.');
        } catch (PDOException $exception) {
            $this->assertSame('23000', $exception->getCode());
        }

        $id = $this->create($actor)['id'];
        Fixture::attach($this->db, $id, 754);
        $path = Fixture::API.'/'.$id;
        $before = EditorialFixture::json($actor->get($path));
        $signal = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql'
            ? "FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'synthetic-hacienda-rollback'"
            : "BEGIN SELECT RAISE(ABORT, 'synthetic-hacienda-rollback'); END";
        $this->db->exec('CREATE TRIGGER stage6a_reject_update BEFORE UPDATE ON publicaciones_hacienda '.$signal);
        try {
            $failed = $actor->put($path, ['json' => Fixture::payload(['estado' => 'PUBLICADA', 'titulo' => 'Cambio sintético fallido'])]);
            $this->assertSame(500, $failed->getStatusCode());
            $this->assertStringNotContainsString('synthetic-hacienda-rollback', (string) $failed->getBody());
        } finally {
            $this->db->exec('DROP TRIGGER stage6a_reject_update');
        }
        $this->assertSame($before, EditorialFixture::json($actor->get($path)));
        $this->assertSame(404, LocalHttps::client()->get(Fixture::PUBLIC_API.'/'.$id)->getStatusCode());
    }
}
