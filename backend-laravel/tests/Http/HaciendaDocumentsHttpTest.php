<?php

namespace Tests\Http;

use App\Hacienda\DocumentStore;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Promise\Utils;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Fixtures\HaciendaDocuments as Documents;
use Tests\Fixtures\HaciendaFixtures as Fixture;
use Tests\Fixtures\NewsImageFixtures;

class HaciendaDocumentsHttpTest extends TestCase
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

    private function actor(string $role = 'hacienda', ?HandlerStack $stack = null): Client
    {
        $actor = EditorialFixture::browser($stack);
        $this->assertSame(200, EditorialFixture::login($actor, $role.'@example.test')->getStatusCode());

        return $actor;
    }

    private function draft(Client $actor): int
    {
        $response = $actor->post(Fixture::API, ['json' => Fixture::payload()]);
        $this->assertSame(201, $response->getStatusCode());
        $this->created[] = $id = EditorialFixture::json($response)['id'];

        return $id;
    }

    private function add(Client $actor, int $id, string $bytes, string $filename = 'Sintético.pdf'): array
    {
        $response = Documents::upload($actor, $id, $bytes, $filename);
        $this->assertSame(201, $response->getStatusCode(), (string) $response->getBody());
        $documents = EditorialFixture::json($response)['archivos'];

        return end($documents);
    }

    public function test_upload_private_public_download_headers_and_revocation_by_state(): void
    {
        $operator = $this->actor();
        $super = $this->actor('super_admin');
        $public = LocalHttps::client();
        $id = $this->draft($operator);
        $pdf = Documents::pdf();
        $last = [];
        foreach ([
            [$pdf, 'application/pdf', 'pdf'],
            [NewsImageFixtures::bytes('jpg').'synthetic-marker', 'image/jpeg', 'jpg'],
            [NewsImageFixtures::bytes('png').'synthetic-marker', 'image/png', 'png'],
        ] as [$bytes, $mime, $extension]) {
            $last[] = $doc = $this->add($operator, $id, $bytes, 'Directorio\Ñandú.exe');
            $this->assertSame(['id', 'nombreOriginal', 'url', 'tipoMime', 'orden', 'tamanio'], array_keys($doc));
            $this->assertSame('Ñandú.exe', $doc['nombreOriginal']);
            $this->assertSame($mime, $doc['tipoMime']);
            $this->assertMatchesRegularExpression('~^/uploads/hacienda/[a-f0-9-]{36}\\.'.$extension.'$~D', $doc['url']);
            $this->assertSame(0600, fileperms(DocumentStore::DIRECTORY.'/'.basename($doc['url'])) & 0777);
            $this->assertSame(404, $public->get($doc['url'])->getStatusCode());
            $adminPath = Fixture::API.'/'.$id.'/archivos/'.$doc['id'].'/contenido';
            $private = $super->get($adminPath);
            $this->assertSame(200, $private->getStatusCode());
            $this->assertSame($mime, $private->getHeaderLine('Content-Type'));
            $this->assertStringContainsString('no-store', $private->getHeaderLine('Cache-Control'));
            $this->assertSame('nosniff', $private->getHeaderLine('X-Content-Type-Options'));
            $this->assertSame("sandbox; default-src 'none'", $private->getHeaderLine('Content-Security-Policy'));
            $this->assertStringStartsWith('attachment;', $private->getHeaderLine('Content-Disposition'));
            $result = (string) $private->getBody();
            $this->assertSame((string) $doc['tamanio'], $private->getHeaderLine('Content-Length'));
            $this->assertSame($doc['tamanio'], strlen($result));
            if ($extension === 'pdf') {
                $this->assertSame($pdf, $result);
            } else {
                $this->assertSame($mime, getimagesizefromstring($result)['mime']);
                $this->assertStringNotContainsString('synthetic-marker', $result);
            }
        }
        $this->assertSame(200, $operator->put(Fixture::API.'/'.$id, ['json' => Fixture::payload(['estado' => 'PUBLICADA'])])->getStatusCode());
        $url = $last[0]['url'];
        $this->assertStringStartsWith('inline;', $public->get($url)->getHeaderLine('Content-Disposition'));
        $this->assertStringStartsWith('attachment;', $public->get($url.'?descargar=1')->getHeaderLine('Content-Disposition'));
        $this->assertSame(400, $public->get($url.'?descargar=invalid')->getStatusCode());
        $head = $public->head($url);
        $this->assertSame(200, $head->getStatusCode());
        $this->assertSame('', (string) $head->getBody());
        $this->assertSame((string) strlen($pdf), $head->getHeaderLine('Content-Length'));
        foreach (['bytes=1-4' => substr($pdf, 1, 4), 'bytes=-5' => substr($pdf, -5)] as $range => $content) {
            $response = $public->get($url, ['headers' => ['Range' => $range]]);
            $this->assertSame(206, $response->getStatusCode());
            $this->assertSame($content, (string) $response->getBody());
        }
        $this->assertSame(416, $public->get($url, ['headers' => ['Range' => 'bytes=99999-']])->getStatusCode());
        $this->assertSame(204, $operator->delete(Fixture::API.'/'.$id)->getStatusCode());
        $this->assertSame(404, $public->get($url, ['headers' => ['Range' => 'bytes=0-3']])->getStatusCode());
        $this->assertSame(200, $super->get(Fixture::API.'/'.$id.'/archivos/'.$last[0]['id'].'/contenido')->getStatusCode());
    }

    public function test_invalid_files_and_boundaries(): void
    {
        $actor = $this->actor();
        $id = $this->draft($actor);
        $before = scandir(DocumentStore::DIRECTORY);
        $jpg = NewsImageFixtures::bytes('jpg');
        foreach (['', '<?php echo 1;', '<svg/>', '<html/>', "%PDF-9.9\n%%EOF", "%PDF-1.4\nsin final",
            "%PDF-1.4\n%%EOF\n<script>", substr($jpg, 0, -25), substr(NewsImageFixtures::bytes('png'), 0, 50),
            str_pad($jpg, 5 * 1024 * 1024 + 1, 'x'), NewsImageFixtures::bytes('png', 5001, 4000),
            Documents::pdf(DocumentStore::MAX_BYTES + 1)] as $invalid) {
            $this->assertSame(400, Documents::upload($actor, $id, $invalid)->getStatusCode());
        }
        $this->assertSame($before, scandir(DocumentStore::DIRECTORY));
        $pdf = Documents::pdf(DocumentStore::MAX_BYTES);
        $this->assertSame(DocumentStore::MAX_BYTES, $this->add($actor, $id, $pdf)['tamanio']);
        $this->assertSame(201, Documents::upload($actor, $id, str_pad($jpg, 5 * 1024 * 1024, 'x'))->getStatusCode());
        $this->assertSame(201, Documents::upload($actor, $id, NewsImageFixtures::bytes('png', 5000, 4000))->getStatusCode());
    }

    public function test_roles_csrf_ownership_revoked_sessions_and_legacy_files(): void
    {
        $hacienda = $this->actor();
        $press = $this->actor('prensa');
        $id = $this->draft($hacienda);
        $other = $this->draft($hacienda);
        $name = '00000000-0000-4000-8000-000000009001.pdf';
        $url = '/uploads/hacienda/'.$name;
        $pdf = Documents::pdf();
        $this->db->prepare('INSERT INTO archivos_hacienda (id,publicacion_hacienda_id,nombre_original,nombre_almacenado,tipo_mime,orden,tamanio) VALUES (?,?,?,?,?,?,?)')
            ->execute([9001, $id, 'Histórico sintético.pdf', $name, 'application/pdf', 0, strlen($pdf)]);
        $stored = DocumentStore::DIRECTORY.'/'.$name;
        file_put_contents($stored, $pdf);
        chmod($stored, 0600);
        $private = Fixture::API.'/'.$id.'/archivos/9001/contenido';
        $public = LocalHttps::client();
        try {
            $this->assertSame(401, EditorialFixture::browser()->get($private)->getStatusCode());
            $this->assertSame(403, $press->get($private)->getStatusCode());
            $this->assertSame(403, Documents::upload($press, $id, $pdf)->getStatusCode());
            $this->assertSame(403, $press->put(Fixture::API.'/'.$id.'/archivos/orden', ['json' => ['ids' => [9001]]])->getStatusCode());
            $this->assertSame(403, $press->delete(Fixture::API.'/'.$id.'/archivos/9001')->getStatusCode());
            $this->assertSame(404, $hacienda->get(Fixture::API.'/'.$other.'/archivos/9001/contenido')->getStatusCode());
            $this->assertSame(404, $hacienda->delete(Fixture::API.'/'.$other.'/archivos/9001')->getStatusCode());
            $this->assertSame(403, Documents::upload($hacienda, $id, $pdf, headers: ['Sec-Fetch-Site' => ''])->getStatusCode());
            $this->assertSame(404, $public->get($url)->getStatusCode());
            $this->assertSame($pdf, (string) $hacienda->get($private)->getBody());
            $this->assertSame(200, $hacienda->put(Fixture::API.'/'.$id, ['json' => Fixture::payload(['estado' => 'PUBLICADA'])])->getStatusCode());
            $this->assertSame($pdf, (string) $public->get($url)->getBody());
            $outside = tempnam('/tmp', 'hacienda-synthetic-');
            file_put_contents($outside, $pdf);
            try {
                unlink($stored);
                $this->assertTrue(symlink($outside, $stored));
                $this->assertSame(404, $public->get($url)->getStatusCode());
                $this->assertSame(404, $hacienda->get($private)->getStatusCode());
                $this->assertSame(404, $public->get('/uploads/hacienda/%2e%2e%2f'.basename($outside))->getStatusCode());
            } finally {
                unlink($outside);
            }
            $token = EditorialFixture::json(EditorialFixture::login($hacienda, 'hacienda@example.test'))['token'];
            $bearer = ['Authorization' => 'Bearer '.$token];
            $this->db->exec("UPDATE usuarios SET activo=0 WHERE email='hacienda@example.test'");
            try {
                $this->assertSame(401, $hacienda->get($private, ['headers' => $bearer])->getStatusCode());
            } finally {
                $this->db->exec("UPDATE usuarios SET activo=1 WHERE email='hacienda@example.test'");
            }
            $this->assertSame(401, Documents::upload($hacienda, $id, $pdf, headers: $bearer)->getStatusCode());
        } finally {
            @unlink($stored);
        }
    }

    public function test_order_detach_and_last_public_document(): void
    {
        $actor = $this->actor();
        $id = $this->draft($actor);
        $a = $this->add($actor, $id, Documents::pdf());
        $b = $this->add($actor, $id, Documents::pdf());
        $other = $this->add($actor, $this->draft($actor), Documents::pdf());
        $path = Fixture::API.'/'.$id.'/archivos';
        foreach ([[$a['id']], [$a['id'], $a['id']], [$a['id'], $other['id']], [(string) $a['id'], $b['id']], []] as $invalid) {
            $this->assertSame(400, $actor->put($path.'/orden', ['json' => ['ids' => $invalid]])->getStatusCode());
        }
        $ordered = $actor->put($path.'/orden', ['json' => ['ids' => [$b['id'], $a['id']]]]);
        $this->assertSame(200, $ordered->getStatusCode());
        $this->assertSame([$b['id'], $a['id']], array_column(EditorialFixture::json($ordered)['archivos'], 'id'));
        $this->assertSame(200, $actor->put(Fixture::API.'/'.$id, ['json' => Fixture::payload(['estado' => 'PUBLICADA'])])->getStatusCode());
        $detached = $actor->delete($path.'/'.$b['id']);
        $this->assertSame(200, $detached->getStatusCode());
        $this->assertSame([0], array_column(EditorialFixture::json($detached)['archivos'], 'orden'));
        $this->assertSame(404, LocalHttps::client()->get($b['url'])->getStatusCode());
        $this->assertSame(404, $actor->get($path.'/'.$b['id'].'/contenido')->getStatusCode());
        $this->assertFileExists(DocumentStore::DIRECTORY.'/'.basename($b['url']));
        $this->assertSame(400, $actor->delete($path.'/'.$a['id'])->getStatusCode());
    }

    public function test_concurrent_publish_detach_and_reorder_leave_a_consistent_result(): void
    {
        $stack = HandlerStack::create();
        $actor = $this->actor(stack: $stack);
        $admin = $this->actor('super_admin', $stack);
        $id = $this->draft($actor);
        $doc = $this->add($actor, $id, Documents::pdf());
        $path = Fixture::API.'/'.$id;
        [$publish, $remove, $order] = Utils::unwrap([
            $actor->putAsync($path, ['json' => Fixture::payload(['estado' => 'PUBLICADA'])]),
            $admin->deleteAsync($path.'/archivos/'.$doc['id']),
            $admin->putAsync($path.'/archivos/orden', ['json' => ['ids' => [$doc['id']]]]),
        ]);
        $final = EditorialFixture::json($admin->get($path));
        $public = LocalHttps::client()->get($doc['url']);
        if ($publish->getStatusCode() === 200) {
            $this->assertSame(400, $remove->getStatusCode());
            $this->assertSame(200, $order->getStatusCode());
            $this->assertSame('PUBLICADA', $final['estado']);
            $this->assertSame([$doc], $final['archivos']);
            $this->assertSame(200, $public->getStatusCode());
        } else {
            $this->assertSame(400, $publish->getStatusCode());
            $this->assertSame(200, $remove->getStatusCode());
            $this->assertContains($order->getStatusCode(), [200, 400]);
            $this->assertSame('BORRADOR', $final['estado']);
            $this->assertSame([], $final['archivos']);
            $this->assertSame(404, $public->getStatusCode());
        }
    }

    public function test_two_uploads_cannot_exceed_fifty_documents(): void
    {
        $stack = HandlerStack::create();
        $actor = $this->actor(stack: $stack);
        $admin = $this->actor('super_admin', $stack);
        $id = $this->draft($actor);
        foreach (range(0, 48) as $position) {
            Fixture::attach($this->db, $id, 10000 + $position, order: $position);
        }
        $path = Fixture::API.'/'.$id;
        $options = Documents::options(Documents::pdf());
        $statuses = array_map(fn ($response) => $response->getStatusCode(), Utils::unwrap([
            $actor->postAsync($path.'/archivos', $options), $admin->postAsync($path.'/archivos', $options),
        ]));
        sort($statuses);
        $this->assertSame([201, 400], $statuses);
        $this->assertCount(50, EditorialFixture::json($actor->get($path))['archivos']);
    }

    public function test_disk_failure_short_write_and_sql_rollback_leave_no_partial_file(): void
    {
        $actor = $this->actor();
        $id = $this->draft($actor);
        $directory = DocumentStore::DIRECTORY;
        $before = scandir($directory);
        $moved = $directory.'-test-'.bin2hex(random_bytes(5));
        $this->assertTrue(rename($directory, $moved));
        try {
            file_put_contents($directory, 'synthetic non-directory');
            $failure = Documents::upload($actor, $id, Documents::pdf());
            $this->assertSame(500, $failure->getStatusCode());
            $this->assertStringNotContainsString($directory, (string) $failure->getBody());
        } finally {
            unlink($directory);
            rename($moved, $directory);
        }
        $signal = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql'
            ? "FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'synthetic-hacienda-rollback'"
            : "BEGIN SELECT RAISE(ABORT, 'synthetic-hacienda-rollback'); END";
        $this->db->exec('CREATE TRIGGER stage6b_fail_insert BEFORE INSERT ON archivos_hacienda '.$signal);
        try {
            $this->assertSame(500, Documents::upload($actor, $id, Documents::pdf())->getStatusCode());
            $this->assertSame($before, scandir($directory));
            $this->assertSame([], EditorialFixture::json($actor->get(Fixture::API.'/'.$id))['archivos']);
        } finally {
            $this->db->exec('DROP TRIGGER stage6b_fail_insert');
        }
        $source = fopen('php://temp', 'w+b');
        try {
            fwrite($source, '%PDF-');
            rewind($source);
            (new DocumentStore)->put($source, 'pdf', 100);
            $this->fail('No se debe publicar una copia corta.');
        } catch (RuntimeException) {
            $this->assertSame($before, scandir($directory));
        } finally {
            fclose($source);
        }
        $this->assertSame(201, Documents::upload($actor, $id, Documents::pdf())->getStatusCode());
    }
}
