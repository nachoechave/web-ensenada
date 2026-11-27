<?php

namespace Tests\Http;

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Promise\Utils;
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

class EditorialHttpTest extends TestCase
{
    private PDO $auth;

    private PDO $news;

    private string $tag;

    /** @var list<int> */
    private array $created = [];

    protected function setUp(): void
    {
        $this->auth = DisposableDatabase::connect();
        $this->news = EditorialFixture::newsDatabase($this->auth);
        $this->auth->exec('DELETE FROM auth_login_windows');
        $this->tag = 'editorial-http-'.bin2hex(random_bytes(5));
    }

    protected function tearDown(): void
    {
        foreach ($this->created as $id) {
            EditorialFixture::remove($this->auth, $this->news, $id);
        }
    }

    private function actor(string $email = 'prensa@example.test', ?HandlerStack $stack = null): Client
    {
        $client = EditorialFixture::browser($stack);
        $this->assertSame(200, EditorialFixture::login($client, $email)->getStatusCode());

        return $client;
    }

    private function save(Client $client, array $changes = []): array
    {
        $response = $client->post(EditorialFixture::API, ['json' => EditorialFixture::payload(['titulo' => $this->tag, ...$changes])]);
        $this->assertSame(201, $response->getStatusCode());
        $row = EditorialFixture::json($response);
        $this->created[] = $row['id'];

        return $row;
    }

    private function audit(Client $client, int $id): array
    {
        $response = $client->get(EditorialFixture::API.'/auditoria');
        $this->assertSame(200, $response->getStatusCode());

        return array_values(array_filter(EditorialFixture::json($response), fn (array $row): bool => $row['noticiaId'] === $id));
    }

    public function test_crud_transitions_and_audit_preserve_news_and_identity(): void
    {
        $press = $this->actor();
        $admin = $this->actor('super_admin@example.test');
        $created = $this->save($press, [
            'titulo' => 'Crónica Ñandú '.$this->tag, 'slug' => 'injected-slug', 'usuarioEmail' => 'forged@example.test',
            'imagenes' => ['/assets/escudo-ensenada.png', '/assets/ensenada-hero.jpg', '/assets/escudo-ensenada.png'],
        ]);
        $id = $created['id'];
        $path = EditorialFixture::API.'/'.$id;
        $public = '/api/noticias/'.$created['slug'];
        $this->assertSame('cronica-nandu-'.$this->tag, $created['slug']);
        $this->assertSame(EditorialFixture::payload()['imagenes'], $created['imagenes']);
        $this->assertSame('2026-04-07', $created['fechaPublicacion']);
        $this->assertTrue($created['destacada']);
        $this->assertSame(['id', 'titulo', 'slug', 'bajada', 'contenido', 'imagen', 'imagenes', 'categoria', 'fechaPublicacion', 'estado', 'destacada'], array_keys($created));
        $this->assertSame($created, EditorialFixture::json($admin->get($path)));
        $this->assertSame(404, $press->get($public)->getStatusCode());
        $listing = EditorialFixture::json($press->get(EditorialFixture::API));
        $this->assertSame($id, $listing[0]['id']);
        $this->assertContains('ARCHIVADA', array_column($listing, 'estado'));
        $this->assertContains('BORRADOR', array_column($listing, 'estado'));

        $edited = EditorialFixture::payload(['titulo' => $this->tag.' editada', 'fechaPublicacion' => '2026-03-06']);
        $this->assertSame(200, $press->put($path, ['json' => $edited])->getStatusCode());
        $edited['estado'] = 'PUBLICADA';
        unset($edited['imagenes']); // Angular's state toggle sends only the cover.
        $published = EditorialFixture::json($press->put($path, ['json' => $edited]));
        $this->assertSame($created['slug'], $published['slug']);
        $this->assertSame([$edited['imagen']], $published['imagenes']);
        $this->assertSame($published, EditorialFixture::json($admin->get($public)));
        $this->assertSame('2026-03-06', $published['fechaPublicacion']);
        $edited['estado'] = 'BORRADOR';
        $this->assertSame(200, $press->put($path, ['json' => $edited])->getStatusCode());
        $this->assertSame(404, $admin->get($public)->getStatusCode());
        $deleted = $admin->delete($path);
        $this->assertSame(204, $deleted->getStatusCode());
        $this->assertSame('', (string) $deleted->getBody());
        $this->assertSame('ARCHIVADA', EditorialFixture::json($press->get($path))['estado']);
        $this->assertSame(404, $admin->get($public)->getStatusCode());
        $edited['estado'] = 'PUBLICADA';
        $this->assertSame(200, $admin->put($path, ['json' => $edited])->getStatusCode());
        $this->assertSame(200, $press->get($public)->getStatusCode());
        $audit = $this->audit($press, $id);
        $this->assertSame(['PUBLICACION', 'ARCHIVADO', 'DESPUBLICACION', 'PUBLICACION', 'EDICION', 'CREACION'], array_column($audit, 'accion'));
        $this->assertSame(['PUBLICADA', 'ARCHIVADA', 'BORRADOR', 'PUBLICADA', 'BORRADOR', 'BORRADOR'], array_column($audit, 'estadoResultante'));
        $this->assertSame('super_admin@example.test', $audit[0]['usuarioEmail']);
        $this->assertSame('prensa@example.test', $audit[5]['usuarioEmail']);
        $this->assertSame(['id', 'noticiaId', 'noticiaTitulo', 'accion', 'estadoResultante', 'usuarioNombre', 'usuarioEmail', 'fecha'], array_keys($audit[0]));
        foreach ($audit as $event) {
            $this->assertMatchesRegularExpression('/^\d{4}-\d\d-\d\dT\d\d:\d\d:\d\d(?:\.\d{3}(?:\d{3})?)?Z$/', $event['fecha']);
        }
    }

    public static function deniedActors(): array
    {
        return [[null, 401], ['hacienda@example.test', 403]];
    }

    #[DataProvider('deniedActors')]
    public function test_every_editorial_route_checks_authorization(?string $email, int $status): void
    {
        $row = $this->save($this->actor());
        $api = EditorialFixture::API;
        $path = $api.'/'.$row['id'];
        $client = $email === null ? EditorialFixture::browser() : $this->actor($email);
        foreach ([['GET', $api], ['GET', $path], ['GET', $api.'/auditoria'], ['POST', $api], ['PUT', $path], ['DELETE', $path]] as [$method, $url]) {
            $options = ['headers' => ['X-Role' => 'SUPER_ADMIN']];
            if (in_array($method, ['POST', 'PUT'], true)) {
                $options['json'] = EditorialFixture::payload(['roles' => ['SUPER_ADMIN']]);
            }
            $this->assertSame($status, $client->request($method, $url, $options)->getStatusCode());
        }
        $this->assertSame($row, EditorialFixture::json($this->actor()->get($path)));
    }

    public function test_writes_require_csrf_and_deactivation_permanently_revokes_an_editor_session(): void
    {
        $press = $this->actor();
        $admin = $this->actor('super_admin@example.test');
        $id = (int) $this->auth->query("SELECT id FROM usuarios WHERE email = 'prensa@example.test'")->fetchColumn();
        $path = '/api/admin/usuarios/'.$id.'/activo';
        $row = $this->save($press);
        $item = EditorialFixture::API.'/'.$row['id'];
        foreach (['POST' => EditorialFixture::API, 'PUT' => $item, 'DELETE' => $item] as $method => $url) {
            $this->assertSame(403, $press->request($method, $url, [
                'headers' => ['Origin' => 'https://localhost:19443', 'Sec-Fetch-Site' => 'same-site'], 'json' => EditorialFixture::payload(),
            ])->getStatusCode());
        }
        $this->assertSame(200, $admin->put($path, ['json' => ['activo' => false]])->getStatusCode());
        try {
            $blocked = EditorialFixture::browser();
            $this->assertSame(403, EditorialFixture::login($blocked, 'prensa@example.test')->getStatusCode());
        } finally {
            $this->assertSame(200, $admin->put($path, ['json' => ['activo' => true]])->getStatusCode());
        }
        $this->assertSame(401, $press->put($item, ['json' => EditorialFixture::payload()])->getStatusCode());
        $this->assertSame($row, EditorialFixture::json($admin->get($item)));
        $this->assertSame(200, $this->actor()->get($item)->getStatusCode());
    }

    public function test_concurrent_creations_have_unique_stable_slugs_and_one_audit_each(): void
    {
        $stack = HandlerStack::create();
        $actors = [$this->actor(stack: $stack), $this->actor('super_admin@example.test', $stack)];
        $pending = [];
        for ($i = 0; $i < 8; $i++) {
            $pending[] = $actors[$i % 2]->postAsync(EditorialFixture::API, ['json' => EditorialFixture::payload(['titulo' => $this->tag])]);
        }
        $responses = Utils::unwrap($pending);
        $rows = [];
        foreach ($responses as $response) {
            if ($response->getStatusCode() === 201) {
                $rows[] = $row = EditorialFixture::json($response);
                $this->created[] = $row['id'];
            }
        }
        $this->assertSame(array_fill(0, 8, 201), array_map(fn (ResponseInterface $response): int => $response->getStatusCode(), $responses));
        $this->assertEqualsCanonicalizing([$this->tag, ...array_map(fn (int $i): string => $this->tag.'-'.$i, range(2, 8))], array_column($rows, 'slug'));
        foreach ($rows as $row) {
            $this->assertSame(['CREACION'], array_column($this->audit($actors[0], $row['id']), 'accion'));
        }
    }

    public function test_audit_failure_rolls_back_creation_edition_gallery_and_archive(): void
    {
        $actor = $this->actor();
        $row = $this->save($actor);
        $item = EditorialFixture::API.'/'.$row['id'];
        $signal = $this->auth->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql'
            ? "FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'synthetic-editorial-rollback'"
            : "BEGIN SELECT RAISE(ABORT, 'synthetic-editorial-rollback'); END";
        $this->auth->exec('CREATE TRIGGER stage4b_reject_audit BEFORE INSERT ON auditoria_noticias '.$signal);
        try {
            $post = $actor->post(EditorialFixture::API, ['json' => EditorialFixture::payload(['titulo' => $this->tag.' rollback'])]);
            $put = $actor->put($item, ['json' => EditorialFixture::payload(['titulo' => $this->tag.' rollback', 'imagenes' => []])]);
            foreach ([$post, $put, $actor->delete($item)] as $response) {
                $this->assertSame(500, $response->getStatusCode());
                $this->assertStringNotContainsString('synthetic-editorial-rollback', (string) $response->getBody());
            }
        } finally {
            $this->auth->exec('DROP TRIGGER stage4b_reject_audit');
        }
        $query = $this->news->prepare('SELECT COUNT(*) FROM noticias WHERE titulo = ?');
        $query->execute([$this->tag.' rollback']);
        $this->assertSame(0, (int) $query->fetchColumn());
        $this->assertSame($row, EditorialFixture::json($actor->get($item)));
        $this->assertSame(['CREACION'], array_column($this->audit($actor, $row['id']), 'accion'));
    }

    public function test_input_limits_unknown_routes_and_audit_window(): void
    {
        $actor = $this->actor();
        foreach ([['titulo' => ' '], ['titulo' => str_repeat('😀', 128)], ['fechaPublicacion' => '2026-02-30'],
            ['estado' => 'publicada'], ['destacada' => 'true'], ['imagen' => '/assets/../privado.jpg'],
            ['imagen' => 'https://editor:password@example.test/a.jpg'], ['imagenes' => ['/assets/otra.jpg']],
            ['imagenes' => array_fill(0, 7, '/assets/ensenada-hero.jpg')], ['contenido' => str_repeat('é', 35000)]] as $invalid) {
            $this->assertSame(400, $actor->post(EditorialFixture::API, ['json' => EditorialFixture::payload($invalid)])->getStatusCode());
        }
        $this->assertSame(400, $actor->get(EditorialFixture::API.'/9223372036854775808')->getStatusCode());
        $this->assertSame(404, $actor->get(EditorialFixture::API.'/999999')->getStatusCode());
        $this->assertSame(404, $actor->put(EditorialFixture::API.'/999999', ['json' => EditorialFixture::payload()])->getStatusCode());
        $this->assertSame(404, $actor->delete(EditorialFixture::API.'/999999')->getStatusCode());
        $this->assertSame(404, $actor->post('/api/admin/archivos/hacienda')->getStatusCode());
        $this->assertSame(415, $actor->post(EditorialFixture::API, ['body' => 'not JSON'])->getStatusCode());

        $email = $this->tag.'@example.test';
        $insert = $this->auth->prepare('INSERT INTO auditoria_noticias (noticia_id, noticia_titulo, accion, estado_resultante, usuario_nombre, usuario_email, fecha) VALUES (?, ?, ?, ?, ?, ?, ?)');
        try {
            for ($i = 0; $i < 102; $i++) {
                $insert->execute([990000 + $i, $this->tag, 'EDICION', 'BORRADOR', 'Actor sintético', $email, sprintf('2037-01-01 00:00:00.%06d', $i)]);
            }
            $rows = EditorialFixture::json($actor->get(EditorialFixture::API.'/auditoria'));
            $this->assertCount(100, $rows);
            $this->assertSame(range(990101, 990002), array_column($rows, 'noticiaId'));
            $this->assertSame('2037-01-01T00:00:00.000101Z', $rows[0]['fecha']);
        } finally {
            $this->auth->prepare('DELETE FROM auditoria_noticias WHERE usuario_email = ?')->execute([$email]);
        }
    }
}
