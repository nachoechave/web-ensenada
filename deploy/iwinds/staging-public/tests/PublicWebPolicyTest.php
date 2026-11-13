<?php

declare(strict_types=1);

use App\Support\IwindsPublicProbe as Probe;
use PHPUnit\Framework\TestCase;

final class PublicWebPolicyTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir().'/public-web-'.bin2hex(random_bytes(8));
        mkdir($this->root, 0700);
        $this->write(Probe::ROTATION, '1 1 '.str_repeat('a', 64)."\n");
    }

    protected function tearDown(): void
    {
        foreach (scandir($this->root) as $name) {
            if ($name !== '.' && $name !== '..') {
                unlink($this->root.'/'.$name);
            }
        }
        rmdir($this->root);
    }

    public function test_public_and_admin_controls_expire_independently(): void
    {
        $public = ['REQUEST_URI' => '/api/site-content'];
        $admin = ['REQUEST_URI' => '/api/admin/noticias'];
        $proof = str_repeat('a', 64);
        $now = time();
        self::assertNull(Probe::requestMode($this->root, $public));
        $this->write(Probe::WEB_MARKER, Probe::webMarker($proof));
        self::assertSame('web', Probe::requestMode($this->root, $public));
        self::assertSame('web', Probe::requestMode($this->root, $admin));
        $this->write(Probe::ADMIN_MARKER, Probe::extendedAdminMarker($now, $now + 60, $proof));
        self::assertSame('web-admin', Probe::requestMode($this->root, $admin));
        self::assertSame('web', Probe::requestMode($this->root, $public));
        $this->write(Probe::ADMIN_MARKER, Probe::extendedAdminMarker($now - 60, $now - 1, $proof));
        self::assertSame('web', Probe::requestMode($this->root, $admin));
        self::assertSame('web', Probe::requestMode($this->root, $public));
        $this->write(Probe::FULL_MARKER, Probe::privilegedMarker('full', $now + 60, $proof));
        self::assertNull(Probe::requestMode($this->root, $public));
    }

    public function test_public_marker_must_be_private_and_authentic(): void
    {
        $public = ['REQUEST_URI' => '/api/site-content'];
        $this->write(Probe::WEB_MARKER, Probe::webMarker(str_repeat('b', 64)));
        self::assertNull(Probe::requestMode($this->root, $public));
        $this->write(Probe::WEB_MARKER, Probe::webMarker(str_repeat('a', 64)));
        chmod($this->root.'/'.Probe::WEB_MARKER, 0644);
        self::assertNull(Probe::requestMode($this->root, $public));
    }

    public function test_only_internal_rewrite_and_authorized_methods_pass(): void
    {
        $request = ['HTTPS' => 'on', 'HTTP_HOST' => 'prueba.ensenada.gov.ar', 'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/api/site-content', 'ENSENADA_REQUEST_LINE' => 'GET /api/site-content HTTP/1.1',
            'REDIRECT_ENSENADA_STAGING_ROUTE' => '1'];
        self::assertTrue(Probe::allows($request, 'web'));
        self::assertFalse(Probe::allows($request, 'public'));
        self::assertFalse(Probe::allows([...$request, 'REDIRECT_ENSENADA_STAGING_ROUTE' => '', 'HTTP_REDIRECT_ENSENADA_STAGING_ROUTE' => '1'], 'web'));
        self::assertFalse(Probe::allows([...$request, 'HTTP_FORWARDED' => 'proto=https'], 'web'));
        self::assertFalse(Probe::allows([...$request, 'HTTP_X_FORWARDED_FOR' => '192.0.2.5'], 'web'));
        self::assertFalse(Probe::allows([...$request, 'CONTENT_LENGTH' => '2'], 'web'));
        $write = ['REQUEST_METHOD' => 'PUT', 'HTTP_ORIGIN' => 'https://prueba.ensenada.gov.ar'];
        self::assertFalse(Probe::validWebOrigin($write));
        self::assertFalse(Probe::validWebOrigin([...$write, 'HTTP_SEC_FETCH_SITE' => 'same-site']));
        self::assertTrue(Probe::validWebOrigin([...$write, 'HTTP_SEC_FETCH_SITE' => 'same-origin']));
    }

    private function write(string $file, string $body): void
    {
        file_put_contents($this->root.'/'.$file, $body);
        chmod($this->root.'/'.$file, 0600);
    }
}
