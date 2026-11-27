<?php

namespace Tests\Http;

use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\HandlerStack;
use PDO;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

final class EditorialFixture
{
    public const API = '/api/admin/noticias';

    public const PASSWORD = 'Synthetic-password-1234';

    public static function browser(?HandlerStack $handler = null): Client
    {
        return LocalHttps::client([
            'cookies' => new CookieJar,
            'handler' => $handler ?? HandlerStack::create(),
            'headers' => ['Accept' => 'application/json', 'Origin' => 'https://localhost:18443', 'Sec-Fetch-Site' => 'same-origin'],
        ]);
    }

    public static function login(Client $client, string $email): ResponseInterface
    {
        return $client->post('/api/auth/login', ['json' => ['email' => $email, 'password' => self::PASSWORD]]);
    }

    public static function json(ResponseInterface $response): array
    {
        return json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
    }

    public static function payload(array $overrides = []): array
    {
        return [
            'titulo' => 'Crónica sintética 4B', 'bajada' => 'Una bajada editorial', 'contenido' => 'Contenido editorial sintético',
            'imagen' => '/assets/ensenada-hero.jpg', 'imagenes' => ['/assets/escudo-ensenada.png', '/assets/ensenada-hero.jpg'],
            'categoria' => 'Cultura', 'fechaPublicacion' => '2026-04-07', 'estado' => 'BORRADOR', 'destacada' => true,
            ...$overrides,
        ];
    }

    public static function newsDatabase(PDO $auth): PDO
    {
        if (getenv('DB_CONNECTION') === 'mysql') {
            return $auth;
        }
        if (getenv('DB_CONNECTION') !== 'sqlite' || getenv('DB_DATABASE') !== '/tmp/ensenada-auth.sqlite') {
            throw new RuntimeException('La prueba requiere el SQLite descartable.');
        }
        $db = new PDO('sqlite:/tmp/ensenada-news.sqlite');
        $db->exec('PRAGMA foreign_keys = ON');
        $db->exec('PRAGMA busy_timeout = 5000');

        return $db;
    }

    public static function remove(PDO $auth, PDO $news, int $id): void
    {
        $auth->prepare('DELETE FROM auditoria_noticias WHERE noticia_id = ?')->execute([$id]);
        $news->prepare('DELETE FROM noticias WHERE id = ?')->execute([$id]);
    }
}
