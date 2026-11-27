<?php

namespace Tests\Stage8;

use App\Support\IwindsPublicProbe;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;
use Tests\Http\DisposableDatabase;

final class ClosureStageTest extends TestCase
{
    public function test_offline_rotation_and_activation_preserve_identity_and_enforce_grants(): void
    {
        $db = DisposableDatabase::connect(runtime: true);
        $isCpanel = getenv('CLOSURE_TEST_CPANEL') === 'true';
        $admin = IwindsPublicProbe::ADMIN_MARKER;
        $full = IwindsPublicProbe::FULL_MARKER;
        $receipt = IwindsPublicProbe::ROTATION;
        $previous = getenv('CLOSURE_TEST_ROTATION_KEY');
        self::assertNotFalse($previous);
        $env = ['APP_KEY' => 'base64:'.base64_encode(str_repeat('x', 32)), 'AUTH_JWT_SECRET' => $previous];

        $run = static function (string $operation) use ($env): Process {
            $process = new Process([PHP_BINARY, 'deploy/iwinds/provision-staging.php', $operation, '--disposable'], env: $env, timeout: 12);
            $process->run();

            return $process;
        };

        if ($isCpanel) {
            self::assertStringContainsString('perfil de permisos requerido', $run('open-full')->getErrorOutput());
            self::assertFileDoesNotExist($full);
            self::assertSame(0, $run('open-admin')->getExitCode());
            self::assertSame('admin', IwindsPublicProbe::mode(getcwd()));
            self::assertStringContainsString('debe estar deshabilitada', $run('rotate-admin')->getErrorOutput());
            rename($admin, '/tmp/closure-admin-marker-retired');
            self::assertNull(IwindsPublicProbe::mode(getcwd()));

            return;
        }

        self::assertStringContainsString('rotación privada', $run('open-admin')->getErrorOutput());
        self::assertStringContainsString('terminal interactiva', $run('rotate-admin')->getErrorOutput());
        file_put_contents($full, (time() + 600)."\n");
        self::assertNull(IwindsPublicProbe::mode(getcwd()));
        rename($full, '/tmp/closure-unsigned-marker');

        $email = 'rotate-admin-fixture@example.test';
        $oldPassword = bin2hex(random_bytes(20));
        $db->prepare("INSERT INTO usuarios(nombre,email,password,rol,activo) VALUES ('Administrador sintético',?,?,'SUPER_ADMIN',1)")
            ->execute([$email, password_hash($oldPassword, PASSWORD_BCRYPT)]);
        $id = (int) $db->lastInsertId();
        $db->exec("INSERT INTO usuario_roles VALUES ($id,'SUPER_ADMIN')");
        $db->prepare('INSERT INTO auth_sessions VALUES (?, ?, 0, 1, ?)')->execute([str_repeat('a', 64), $id, time() + 3600]);

        $newPassword = bin2hex(random_bytes(20));
        $input = new InputStream;
        $rotate = new Process(['script', '-qef', '-E', 'always', '-c', 'php deploy/iwinds/provision-staging.php rotate-admin --disposable', '/dev/null'], env: $env, input: $input, timeout: 12);
        $rotate->start();
        foreach (['Correo del SUPER_ADMIN' => $email, 'Escribí ROTAR' => 'ROTAR', 'Contraseña nueva' => $newPassword, 'Confirmar contraseña' => $newPassword] as $prompt => $value) {
            self::assertTrue($rotate->waitUntil(fn (): bool => str_contains($rotate->getOutput(), $prompt)), $prompt);
            $input->write($value."\n");
        }
        $input->close();
        self::assertSame(0, $rotate->wait());
        self::assertFalse(str_contains($rotate->getOutput(), $newPassword), 'La contraseña sintética salió por la terminal.');
        $saved = $db->query("SELECT id,email,password,token_version FROM usuarios WHERE id=$id")->fetch();
        self::assertSame($email, $saved['email']);
        self::assertSame($id, (int) $saved['id']);
        self::assertSame(1, (int) $saved['token_version']);
        self::assertTrue(password_verify($newPassword, $saved['password']));
        self::assertFalse(password_verify($oldPassword, $saved['password']));
        self::assertSame(0, (int) $db->query("SELECT COUNT(*) FROM auth_sessions WHERE usuario_id=$id")->fetchColumn());
        self::assertSame(0600, fileperms($receipt) & 0777);
        self::assertFalse(str_contains(file_get_contents($receipt), $saved['password']));
        self::assertSame(0, $run('open-full')->getExitCode());
        self::assertSame('full', IwindsPublicProbe::mode(getcwd()));
        self::assertSame(1, $run('open-admin')->getExitCode());
        rename($full, '/tmp/closure-full-marker-retired');
        self::assertNull(IwindsPublicProbe::mode(getcwd()));
        $forged = file_get_contents($receipt);
        file_put_contents($receipt, preg_replace('/[a-f0-9]{64}/', str_repeat('0', 64), $forged));
        self::assertStringContainsString('rotación privada', $run('open-admin')->getErrorOutput());
        file_put_contents($receipt, $forged);
    }
}
