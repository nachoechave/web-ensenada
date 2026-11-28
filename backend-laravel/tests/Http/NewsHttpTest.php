<?php

namespace Tests\Http;

use Illuminate\Database\Connectors\MySqlConnector;
use Illuminate\Database\Connectors\SQLiteConnector;
use PDOException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class NewsHttpTest extends TestCase
{
    public function test_list_defaults_and_maximum_preserve_public_order_and_pagination(): void
    {
        $client = LocalHttps::client();
        $response = $client->get('/api/noticias');
        $this->assertSame(200, $response->getStatusCode());
        $page = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame([63, ...range(52, 42)], array_column($page['content'], 'id'));
        $this->assertSame(['PUBLICADA'], array_unique(array_column($page['content'], 'estado')));
        unset($page['content']);
        $this->assertSame(['page' => 0, 'size' => 12, 'totalElements' => 53, 'totalPages' => 5, 'first' => true, 'last' => false], $page);

        $response = $client->get('/api/noticias?page=-3&size=999');
        $this->assertSame(200, $response->getStatusCode());
        $page = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertCount(48, $page['content']);
        $this->assertSame(48, $page['size']);
        $this->assertSame(2, $page['totalPages']);
        $this->assertSame(0, $page['page']);

        $response = $client->get('/api/noticias?page=1&size=48');
        $this->assertSame(200, $response->getStatusCode());
        $last = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame([5, 4, 3, 2, 1], array_column($last['content'], 'id'));
        $this->assertFalse($last['first']);
        $this->assertTrue($last['last']);

        $response = $client->get('/api/noticias?page=20&size=12');
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['content' => [], 'page' => 20, 'size' => 12, 'totalElements' => 53, 'totalPages' => 5, 'first' => false, 'last' => true],
            json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR));
    }

    public function test_featured_filters_state_and_preserves_id_order(): void
    {
        $response = LocalHttps::client()->get('/api/noticias/destacadas');
        $this->assertSame(200, $response->getStatusCode());
        $rows = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame([63, 50, 2], array_column($rows, 'id'));
        $this->assertSame([true, true, true], array_column($rows, 'destacada'));
        $this->assertSame(['PUBLICADA', 'PUBLICADA', 'PUBLICADA'], array_column($rows, 'estado'));
    }

    public function test_detail_preserves_slugs_historical_ids_and_the_full_public_dto(): void
    {
        $client = LocalHttps::client();
        $expected = [
            'id' => 63, 'titulo' => 'Crónica sintética de Ensenada', 'slug' => 'cronica-ensenada-2',
            'bajada' => 'Una bajada para comprobar el HTML inicial.', 'contenido' => 'Texto de la noticia visible antes de ejecutar JavaScript.',
            'imagen' => '/assets/ensenada-hero.jpg', 'imagenes' => ['/assets/ensenada-hero.jpg', '/assets/escudo-ensenada.png', '/assets/mario-secco.jpg'],
            'categoria' => 'Cultura', 'fechaPublicacion' => '2099-04-05', 'estado' => 'PUBLICADA', 'destacada' => true,
        ];
        foreach (['cronica-ensenada-2', 'CRONICA-ENSENADA-2', '63', '00063'] as $identifier) {
            $response = $client->get('/api/noticias/'.$identifier);
            $this->assertSame(200, $response->getStatusCode());
            $this->assertSame($expected, json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR));
        }
        $legacy = json_decode((string) $client->get('/api/noticias/noticia-1')->getBody(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('noticia-1', $legacy['slug']);
        $this->assertSame([], $legacy['imagenes']);
        $this->assertFalse($legacy['destacada']);
    }

    public static function hiddenOrMissing(): array
    {
        return array_map(fn (string $slug): array => [$slug], ['61', '62', 'borrador-privado', 'archivo-privado', 'sin-noticia', '9223372036854775808', '%27%20OR%201%3D1']);
    }

    #[DataProvider('hiddenOrMissing')]
    public function test_hidden_or_missing_details_share_the_same_404_envelope(string $identifier): void
    {
        $path = '/api/noticias/'.$identifier;
        $response = LocalHttps::client()->get($path);
        $this->assertSame(404, $response->getStatusCode());
        $error = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertMatchesRegularExpression('/^\d{4}-\d\d-\d\dT\d\d:\d\d:\d\d\.\d{3}Z$/', $error['timestamp']);
        unset($error['timestamp']);
        $this->assertSame(['status' => 404, 'error' => 'Not Found', 'path' => $path], $error);
    }

    public function test_invalid_pagination_is_rejected_and_news_has_no_write_routes(): void
    {
        $client = LocalHttps::client();
        foreach (['?page=nan', '?size=1.2', '?page=2147483648', '?page=2147483647&size=48'] as $query) {
            $response = $client->get('/api/noticias'.$query);
            $this->assertSame(400, $response->getStatusCode());
            $error = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
            unset($error['timestamp']);
            $this->assertSame(['status' => 400, 'error' => 'Bad Request', 'path' => '/api/noticias'], $error);
        }
        $this->assertSame(405, $client->post('/api/noticias')->getStatusCode());
        $this->assertSame(405, $client->put('/api/noticias/63')->getStatusCode());
        $this->assertSame(401, $client->get('/api/admin/noticias')->getStatusCode());
        $this->assertSame(401, EditorialFixture::browser()->post('/api/admin/noticias', ['json' => EditorialFixture::payload()])->getStatusCode());
    }

    public function test_public_news_read_only_boundary_matches_the_configured_engine(): void
    {
        $config = require __DIR__.'/../../config/database.php';
        if ($config['default'] === 'mysql') {
            $this->assertArrayNotHasKey('news', $config['connections']);
            $pdo = (new MySqlConnector)->connect($config['connections']['mysql']);
            $this->assertSame('auth_runtime', explode('@', $pdo->query('SELECT CURRENT_USER()')->fetchColumn())[0]);
            $pdo->exec('SET TRANSACTION READ ONLY');
            $pdo->beginTransaction();
            try {
                $this->assertSame(63, (int) $pdo->query('SELECT id FROM noticias WHERE id = 63')->fetchColumn());
                $pdo->exec('UPDATE noticias SET titulo = titulo WHERE id = -1');
                $this->fail('La transacción pública permitió una escritura.');
            } catch (PDOException $exception) {
                $this->assertSame(1792, $exception->errorInfo[1]);
            } finally {
                $pdo->rollBack();
            }
            $this->assertSame(0, $pdo->exec('UPDATE noticias SET titulo = titulo WHERE id = -1'));

            return;
        }

        $pdo = (new SQLiteConnector)->connect($config['connections']['news']);
        $this->assertSame(63, (int) $pdo->query('SELECT id FROM noticias WHERE id = 63')->fetchColumn());
        foreach ([
            ['UPDATE noticias SET titulo = titulo WHERE id = 63', 8],
            ['UPDATE noticia_imagenes SET url = url WHERE noticia_id = 63', 8],
            ['SELECT id FROM usuarios LIMIT 1', 1],
            ['SELECT id FROM auditoria_noticias LIMIT 1', 1],
        ] as [$statement, $expected]) {
            try {
                $pdo->exec($statement);
                $this->fail('La conexión pública permitió una operación prohibida.');
            } catch (PDOException $exception) {
                $this->assertSame($expected, $exception->errorInfo[1]);
            }
        }
    }
}
