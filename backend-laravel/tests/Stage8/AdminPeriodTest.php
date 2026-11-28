<?php

namespace Tests\Stage8;

use App\Support\IwindsPublicProbe as Probe;
use PHPUnit\Framework\TestCase;

final class AdminPeriodTest extends TestCase
{
    private string $root;

    private const PROOF = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir().'/admin-period-'.bin2hex(random_bytes(8));
        mkdir($this->root, 0700);
        file_put_contents($this->root.'/'.Probe::ROTATION, '1 1 '.self::PROOF."\n");
    }

    protected function tearDown(): void
    {
        foreach (scandir($this->root) ?: [] as $name) {
            if (! in_array($name, ['.', '..'], true)) {
                unlink($this->root.'/'.$name);
            }
        }
        rmdir($this->root);
    }

    public function test_extended_marker_has_a_distinct_signature_and_a_hard_limit(): void
    {
        $now = time();
        $until = $now + 7 * 86400;
        $this->write(Probe::extendedAdminMarker($now, $until, self::PROOF));
        self::assertSame('admin', Probe::mode($this->root));
        self::assertSame($until, Probe::activeAdminPeriodExpiry($this->root));

        foreach ([
            Probe::extendedAdminMarker($now, $until + 1, self::PROOF),
            Probe::extendedAdminMarker($now + 60, $until, self::PROOF),
            Probe::extendedAdminMarker($now - 120, $now, self::PROOF),
            Probe::extendedAdminMarker($now, $until, str_repeat('b', 64)),
            str_replace('synthetic-admin', 'full', Probe::extendedAdminMarker($now, $until, self::PROOF)),
            Probe::extendedAdminMarker($now, $until, self::PROOF).str_repeat('x', 128),
            Probe::privilegedMarker('admin', $until, self::PROOF),
        ] as $invalid) {
            $this->write($invalid);
            self::assertNull(Probe::mode($this->root));
            self::assertNull(Probe::activeAdminPeriodExpiry($this->root));
        }
    }

    public function test_extended_marker_fails_closed_on_conflicts_and_unsafe_files(): void
    {
        $now = time();
        $marker = Probe::extendedAdminMarker($now, $now + 86400, self::PROOF);
        $path = $this->root.'/'.Probe::ADMIN_MARKER;
        $this->write($marker, 0644);
        self::assertNull(Probe::mode($this->root));
        $this->write($marker);
        file_put_contents($this->root.'/'.Probe::FULL_MARKER, $marker);
        self::assertNull(Probe::mode($this->root));
        unlink($path);
        self::assertNull(Probe::mode($this->root));
        unlink($this->root.'/'.Probe::FULL_MARKER);
        file_put_contents($this->root.'/private-file', $marker);
        symlink($this->root.'/private-file', $path);
        self::assertNull(Probe::mode($this->root));
        unlink($path);
        $this->write($marker);
        unlink($this->root.'/'.Probe::ROTATION);
        self::assertNull(Probe::mode($this->root));
    }

    public function test_existing_modes_and_request_policy_are_preserved(): void
    {
        $now = time();
        $this->write(Probe::privilegedMarker('admin', $now + 1800, self::PROOF));
        self::assertSame('admin', Probe::mode($this->root));
        self::assertNull(Probe::activeAdminPeriodExpiry($this->root));
        unlink($this->root.'/'.Probe::ADMIN_MARKER);
        file_put_contents($this->root.'/'.Probe::FULL_MARKER, Probe::privilegedMarker('full', $now + 1800, self::PROOF));
        self::assertSame('full', Probe::mode($this->root));
        unlink($this->root.'/'.Probe::FULL_MARKER);
        file_put_contents($this->root.'/'.Probe::MARKER, ($now + 1800)."\n");
        self::assertSame('public', Probe::mode($this->root));

        $request = ['HTTPS' => 'on', 'REMOTE_USER' => 'basic-fixture', 'HTTP_HOST' => 'prueba.ensenada.gov.ar',
            'REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/uploads/noticias/fixture.webp',
            'ENSENADA_REQUEST_LINE' => 'GET /uploads/noticias/fixture.webp HTTP/2.0'];
        self::assertTrue(Probe::allows($request, 'admin'));
        self::assertFalse(Probe::allows($request, 'public'));
        self::assertFalse(Probe::allows([...$request, 'REMOTE_USER' => '', 'HTTP_REMOTE_USER' => 'forged'], 'admin'));
        self::assertFalse(Probe::allows([...$request, 'HTTP_HOST' => 'ensenada.gov.ar'], 'admin'));
        self::assertFalse(Probe::allows([...$request, 'HTTP_X_HTTP_METHOD_OVERRIDE' => 'DELETE'], 'admin'));
        $request['HTTP_AUTHORIZATION'] = 'Bearer fixture';
        self::assertFalse(Probe::stripApacheAuthorization($request));
    }

    private function write(string $marker, int $mode = 0600): void
    {
        $path = $this->root.'/'.Probe::ADMIN_MARKER;
        file_put_contents($path, $marker);
        chmod($path, $mode);
    }
}
