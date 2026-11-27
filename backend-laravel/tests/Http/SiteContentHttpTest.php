<?php

namespace Tests\Http;

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Promise\Utils;
use PDO;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

class SiteContentHttpTest extends TestCase
{
    private const ADMIN = '/api/admin/site-content';

    private const PUBLIC = '/api/site-content';

    private PDO $db;

    private array $original;

    protected function setUp(): void
    {
        $this->db = DisposableDatabase::connect();
        $this->db->exec('DELETE FROM auth_login_windows');
        $this->original = $this->row();
    }

    protected function tearDown(): void
    {
        $this->db->prepare('UPDATE portal_site_content SET contenido_json=?, updated_at=? WHERE id=1')
            ->execute([$this->original['contenido_json'], $this->original['updated_at']]);
    }

    private function row(): array
    {
        return $this->db->query('SELECT contenido_json, updated_at FROM portal_site_content WHERE id=1')->fetch(PDO::FETCH_ASSOC);
    }

    private function actor(string $role = 'super_admin', ?HandlerStack $handler = null): Client
    {
        $client = EditorialFixture::browser($handler);
        $this->assertSame(200, EditorialFixture::login($client, $role.'@example.test')->getStatusCode());

        return $client;
    }

    private function put(Client $client, string $json, array $headers = []): ResponseInterface
    {
        return $client->put(self::ADMIN, ['body' => $json, 'headers' => ['Content-Type' => 'application/json; charset=UTF-8', ...$headers]]);
    }

    private function assertDocument(ResponseInterface $response, string $raw): void
    {
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('application/json', $response->getHeaderLine('Content-Type'));
        $this->assertContains('no-store', array_map('trim', explode(',', $response->getHeaderLine('Cache-Control'))));
        $this->assertSame('nosniff', $response->getHeaderLine('X-Content-Type-Options'));
        $this->assertSame($raw, (string) $response->getBody());
    }

    public function test_reads_and_round_trips_the_exact_document_without_defaults(): void
    {
        $anonymous = LocalHttps::client();
        $admin = $this->actor();
        $this->assertDocument($anonymous->get(self::PUBLIC), $this->original['contenido_json']);
        $this->assertDocument($admin->get(self::ADMIN), $this->original['contenido_json']);
        $full = str_replace('Portada sintética', 'Ñandú 😀 & <texto> \\"prueba\\"', $this->original['contenido_json']);
        foreach ([$full, '{}', '{"hero":{},"areas":{"items":[],"secretarias":[]},"agenda":{"items":[]},"extension":{"a":[],"b":{},"c":null,"d":true}}'] as $raw) {
            $this->assertDocument($this->put($admin, " \n".$raw."\t\r\n"), $raw);
            $this->assertDocument($admin->get(self::ADMIN), $raw);
            $this->assertDocument($anonymous->get(self::PUBLIC), $raw);
            $this->assertSame($raw, $this->row()['contenido_json']);
        }
        $this->assertSame(1, (int) $this->db->query('SELECT COUNT(*) FROM portal_site_content')->fetchColumn());
    }

    public function test_rejects_invalid_shape_and_unsafe_urls_without_writing(): void
    {
        $admin = $this->actor();
        foreach ([
            '', '{incorrecto}', '[]', 'null', '"texto"', '{"footer":null}', '{"hero":[]}', '{"hero":{"intervaloSegundos":"5"}}',
            '{"hero":{"intervaloSegundos":1e400}}', '{"navbar":{"items":{}}}', '{"navbar":{"items":[{"visible":"true"}]}}',
            '{"areas":{"secretarias":[null]}}', '{"agenda":{"items":[{"dia":5}]}}',
            '{"footer":{"telefono":123}}', '{"topbar":{"facebook":" javascript:alert(1)"}}',
            '{"navbar":{"items":[{"url":"java\\u000ascript:alert(1)"}]}}',
            '{"hero":{"ctaPrimarioUrl":"data:text/html,<svg/onload=alert(1)>"}}',
            '{"hero":{"imagenes":["/assets/x.png),url(https://example.test/y"]}}',
            '{"intendencia":{"galeria":["//example.test/foto.jpg"]}}',
            '{"footer":{"mapaUrl":"https://user:pass@example.test/mapa"}}',
            '{"footer":{"mapaUrl":"\\\\example.test/mapa"}}', "{\"extra\":\"\xFF\"}",
        ] as $invalid) {
            $this->assertSame(400, $this->put($admin, $invalid)->getStatusCode());
        }
        $this->assertSame(415, $this->put($admin, '{}', ['Content-Type' => 'text/plain'])->getStatusCode());
        $this->assertSame($this->original, $this->row());
        $allowed = '{"hero":{"imagenes":["/uploads/sitio/historica.jpg","https://images.example.test/a.png"],"ctaPrimarioUrl":"#accesos"},"footer":{"mapaUrl":"https://mapa.example.test/a?q=1&b=2"},"accesos":[{"url":"mailto:portal@example.test"},{"url":"tel:+15550100"}]}';
        $this->assertDocument($this->put($admin, $allowed), $allowed);
    }

    public function test_applies_the_text_column_byte_limit_to_both_engines(): void
    {
        $admin = $this->actor();
        $prefix = '{"extra":"';
        $suffix = '"}';
        $room = 65535 - strlen($prefix.$suffix);
        $atLimit = $prefix.str_repeat('😀', intdiv($room, 4)).str_repeat('x', $room % 4).$suffix;
        $this->assertSame(65535, strlen($atLimit));
        $this->assertDocument($this->put($admin, $atLimit), $atLimit);
        $overLimit = substr($atLimit, 0, -2).'x'.$suffix;
        $this->assertSame(65536, strlen($overLimit));
        $this->assertSame(413, $this->put($admin, $overLimit)->getStatusCode());
        $this->assertSame($atLimit, $this->row()['contenido_json']);
    }

    public function test_requires_super_admin_current_session_and_same_origin(): void
    {
        $anonymous = EditorialFixture::browser();
        $this->assertSame(401, $anonymous->get(self::ADMIN)->getStatusCode());
        $this->assertSame(401, $this->put($anonymous, '{}')->getStatusCode());
        foreach (['prensa', 'hacienda'] as $role) {
            $client = $this->actor($role);
            $this->assertSame(403, $client->get(self::ADMIN)->getStatusCode());
            $this->assertSame(403, $this->put($client, '{}', ['X-Role' => 'SUPER_ADMIN'])->getStatusCode());
            $this->assertSame(200, $client->get(self::PUBLIC)->getStatusCode());
        }
        $admin = $this->actor();
        foreach ([['Sec-Fetch-Site' => ''], ['Origin' => 'https://localhost:19443', 'Sec-Fetch-Site' => 'same-site']] as $headers) {
            $this->assertSame(403, $this->put($admin, '{}', $headers)->getStatusCode());
        }
        $active = $this->db->prepare("UPDATE usuarios SET activo=? WHERE email='super_admin@example.test'");
        $active->execute([0]);
        try {
            $this->assertSame(401, $this->put($admin, '{}')->getStatusCode());
            $this->assertSame(401, $admin->get(self::ADMIN)->getStatusCode());
        } finally {
            $active->execute([1]);
        }
        $this->assertSame(401, $this->put($admin, '{}')->getStatusCode());
        $this->assertSame($this->original, $this->row());
    }

    public function test_never_recreates_the_singleton_and_does_not_publish_invalid_stored_content(): void
    {
        $admin = $this->actor();
        $public = LocalHttps::client();
        $unsafe = '{"hero":{"ctaPrimarioUrl":"javascript:alert(1)"}}';
        $this->db->prepare('UPDATE portal_site_content SET contenido_json=? WHERE id=1')->execute([$unsafe]);
        $response = $public->get(self::PUBLIC);
        $this->assertSame(500, $response->getStatusCode());
        $this->assertStringNotContainsString('javascript:', (string) $response->getBody());
        $this->assertSame($unsafe, $this->row()['contenido_json']);
        $this->db->exec('DELETE FROM portal_site_content WHERE id=1');
        try {
            $this->assertSame(404, $public->get(self::PUBLIC)->getStatusCode());
            $this->assertSame(404, $admin->get(self::ADMIN)->getStatusCode());
            $this->assertSame(404, $this->put($admin, '{}')->getStatusCode());
            $this->assertSame(0, (int) $this->db->query('SELECT COUNT(*) FROM portal_site_content')->fetchColumn());
        } finally {
            $this->db->prepare('INSERT INTO portal_site_content (id,contenido_json,updated_at) VALUES (1,?,?)')
                ->execute([$this->original['contenido_json'], $this->original['updated_at']]);
        }
    }

    public function test_concurrent_writes_and_reads_always_return_complete_documents(): void
    {
        $email = 'stage5a-concurrent-'.bin2hex(random_bytes(6));
        $this->db->prepare("INSERT INTO usuarios (nombre,email,password,rol,activo,token_version)
            SELECT 'Admin sintético concurrente', ?, password, rol, activo, token_version FROM usuarios WHERE email='super_admin@example.test'")
            ->execute([$email.'@example.test']);
        $id = (int) $this->db->lastInsertId();
        try {
            $this->db->prepare("INSERT INTO usuario_roles (usuario_id,rol) VALUES (?,'SUPER_ADMIN')")->execute([$id]);
            $this->assertConcurrentDocuments($email);
        } finally {
            foreach (['auth_sessions', 'usuario_roles'] as $table) {
                $this->db->prepare("DELETE FROM $table WHERE usuario_id=?")->execute([$id]);
            }
            $this->db->prepare('DELETE FROM usuarios WHERE id=?')->execute([$id]);
        }
    }

    private function assertConcurrentDocuments(string $email): void
    {
        $handler = HandlerStack::create();
        $actors = [$this->actor(handler: $handler), $this->actor($email, $handler)];
        $reader = LocalHttps::client(['handler' => $handler]);
        $writers = $readers = $documents = [];
        for ($i = 0; $i < 8; $i++) {
            $documents[$i] = json_encode(['hero' => ['titulo' => "Portada sintética $i"], 'extra' => str_repeat((string) $i, 16000)], JSON_THROW_ON_ERROR);
            $writers[] = $actors[$i % 2]->putAsync(self::ADMIN, ['body' => $documents[$i], 'headers' => ['Content-Type' => 'application/json']]);
            $readers[] = $reader->getAsync(self::PUBLIC);
        }
        foreach (Utils::unwrap($writers) as $i => $response) {
            $this->assertDocument($response, $documents[$i]);
        }
        foreach (Utils::unwrap($readers) as $response) {
            $this->assertSame(200, $response->getStatusCode());
            $this->assertContains((string) $response->getBody(), [$this->original['contenido_json'], ...$documents]);
        }
        $this->assertContains($this->row()['contenido_json'], $documents);
        $this->assertDocument($actors[0]->get(self::ADMIN), $this->row()['contenido_json']);
    }

    public function test_database_failure_preserves_both_fields_of_the_original_row(): void
    {
        $admin = $this->actor();
        $signal = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql'
            ? "FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'synthetic-site-rollback'"
            : "BEGIN SELECT RAISE(ABORT, 'synthetic-site-rollback'); END";
        $this->db->exec('CREATE TRIGGER stage5a_reject_update BEFORE UPDATE ON portal_site_content '.$signal);
        try {
            $response = $this->put($admin, '{}');
            $this->assertSame(500, $response->getStatusCode());
            $this->assertStringNotContainsString('synthetic-site-rollback', (string) $response->getBody());
            $this->assertSame($this->original, $this->row());
        } finally {
            $this->db->exec('DROP TRIGGER stage5a_reject_update');
        }
        $this->assertDocument($this->put($admin, '{}'), '{}');
    }
}
