<?php

namespace Tests\Unit;

use App\Support\IwindsProduction;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ProductionGateTest extends TestCase
{
    private function server(string $path = '/api/site-content', string $method = 'GET'): array
    {
        return ['HTTPS' => 'on', 'HTTP_HOST' => 'www.ensenada.gov.ar', 'REQUEST_METHOD' => $method,
            'REQUEST_URI' => $path, 'REDIRECT_ENSENADA_PRODUCTION_ROUTE' => '1',
            'REDIRECT_ENSENADA_PRODUCTION_LINE' => "$method $path HTTP/1.1",
            'HTTP_ORIGIN' => IwindsProduction::ORIGIN, 'HTTP_SEC_FETCH_SITE' => 'same-origin'];
    }

    #[DataProvider('routes')]
    public function test_expected_routes(string $method, string $path): void
    {
        self::assertTrue(IwindsProduction::allows($this->server($path, $method)));
    }

    public static function routes(): iterable
    {
        foreach (['/api/site-content', '/api/noticias', '/api/noticias/destacadas', '/api/noticias/123', '/api/noticias/slug-existente',
            '/api/noticias?page=0&size=12', '/api/hacienda', '/api/hacienda/2', '/api/proveedores/documentos',
            '/api/auth/me', '/api/admin/usuarios', '/api/admin/site-content', '/api/admin/noticias/auditoria',
            '/api/admin/hacienda/1/archivos/2/contenido', '/uploads/noticias/123e4567-e89b-42d3-a456-426614174000.webp',
            '/uploads/sitio/historica.jpg', '/uploads/hacienda/123e4567-e89b-42d3-a456-426614174000.pdf?descargar=true'] as $path) {
            yield ['GET', $path];
            yield ['HEAD', $path];
        }
        foreach (['/api/auth/login', '/api/auth/logout', '/api/admin/noticias', '/api/admin/archivos/sitio',
            '/api/admin/archivos/noticias', '/api/admin/hacienda/1/archivos', '/api/admin/proveedores/documentos'] as $path) {
            yield ['POST', $path];
        }
        foreach (['/api/auth/me/password', '/api/admin/usuarios/1/activo', '/api/admin/noticias/1', '/api/admin/site-content',
            '/api/admin/hacienda/1/archivos/orden', '/api/admin/proveedores/documentos/orden'] as $path) {
            yield ['PUT', $path];
        }
        foreach (['/api/admin/noticias/1', '/api/admin/hacienda/1/archivos/2', '/api/admin/proveedores/documentos/1'] as $path) {
            yield ['DELETE', $path];
        }
    }

    #[DataProvider('attacks')]
    public function test_rejects_manipulated_requests(array $changes): void
    {
        self::assertFalse(IwindsProduction::allows(array_replace($this->server(), $changes)));
    }

    public static function attacks(): iterable
    {
        foreach (['prueba.ensenada.gov.ar', 'ensenada.gov.ar', 'www.ensenada.gov.ar.evil.test', 'www.ensenada.gov.ar:443'] as $host) {
            yield [['HTTP_HOST' => $host]];
        }
        yield [['HTTPS' => 'off']];
        yield [['REDIRECT_ENSENADA_PRODUCTION_ROUTE' => '']];
        foreach (['HTTP_FORWARDED', 'HTTP_X_FORWARDED_HOST', 'HTTP_X_REAL_IP', 'HTTP_X_ORIGINAL_URL', 'HTTP_X_HTTP_METHOD_OVERRIDE', 'HTTP_TRANSFER_ENCODING'] as $header) {
            yield [[$header => 'spoofed']];
        }
        yield [['HTTP_AUTHORIZATION' => 'Bearer forged']];
        yield [['HTTP_ORIGIN' => 'https://evil.test']];
        yield [['CONTENT_LENGTH' => '1']];
        yield [['REQUEST_URI' => '/api/%61dmin/usuarios']];
        yield [['REDIRECT_ENSENADA_PRODUCTION_LINE' => 'GET https://www.ensenada.gov.ar/api/site-content HTTP/1.1']];
    }

    public function test_unsafe_calls_require_origin_and_fetch_metadata(): void
    {
        $server = $this->server('/api/auth/login', 'POST');
        unset($server['HTTP_ORIGIN']);
        self::assertFalse(IwindsProduction::allows($server));
        $server['HTTP_ORIGIN'] = IwindsProduction::ORIGIN;
        foreach (['same-site', 'cross-site', 'none', ''] as $site) {
            $server['HTTP_SEC_FETCH_SITE'] = $site;
            self::assertFalse(IwindsProduction::allows($server));
        }
    }

    public function test_no_activation_marker_is_distributed(): void
    {
        self::assertFileDoesNotExist(dirname(__DIR__, 2).'/.iwinds-production-enabled');
    }
}
