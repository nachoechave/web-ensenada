<?php

namespace Tests\Stage8;

use GuzzleHttp\Cookie\CookieJar;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;
use Tests\Http\DisposableDatabase;
use Tests\Http\LocalHttps;

final class ProvisioningTest extends TestCase
{
    public function test_clean_staging_provisions_once_and_serves_the_existing_contracts(): void
    {
        $db = DisposableDatabase::connect(runtime: true);
        self::assertStringContainsString('10.11.', $db->query('SELECT VERSION()')->fetchColumn());
        self::assertSame(0, (int) $db->query('SELECT COUNT(*) FROM portal_site_content')->fetchColumn());
        self::assertSame(0, (int) $db->query('SELECT COUNT(*) FROM usuarios')->fetchColumn());
        $status = $this->cli('status');
        self::assertSame(0, $status->getExitCode());
        self::assertStringContainsString('PENDIENTE', $status->getOutput());

        $marker = '.iwinds-api-enabled';
        self::assertFileDoesNotExist($marker);
        symlink('/tmp/provision-nonexistent', $marker);
        try {
            $closed = $this->cli('site-content');
            self::assertSame(1, $closed->getExitCode());
            self::assertStringContainsString('marcador debe estar ausente', $closed->getErrorOutput());
        } finally {
            unlink($marker);
        }

        $jsonPath = 'deploy/iwinds/site-content.staging.json';
        $document = file_get_contents($jsonPath);
        try {
            file_put_contents($jsonPath, '{"hero":{"imagenes":["javascript:alert(1)"]}}');
            self::assertStringContainsString('no supera la validación', $this->cli('site-content')->getErrorOutput());
            self::assertSame(0, (int) $db->query('SELECT COUNT(*) FROM portal_site_content')->fetchColumn());
        } finally {
            file_put_contents($jsonPath, $document);
        }

        $other = $this->command('site-content');
        $other->start();
        $site = $this->cli('site-content');
        $exits = [$other->wait(), $site->getExitCode()];
        sort($exits);
        self::assertSame([0, 1], $exits);
        self::assertSame(1, (int) $db->query('SELECT COUNT(*) FROM portal_site_content')->fetchColumn());
        self::assertSame($document, $db->query('SELECT contenido_json FROM portal_site_content WHERE id=1')->fetchColumn());

        $pipe = $this->cli('first-admin');
        self::assertSame(1, $pipe->getExitCode());
        self::assertStringContainsString('terminal interactiva', $pipe->getErrorOutput());
        $db->exec("INSERT INTO usuarios (nombre,email,password,rol,activo) VALUES ('Histórico','legacy@example.test','fixture','PRENSA',0)");
        $legacyId = (int) $db->lastInsertId();
        $db->exec("INSERT INTO usuario_roles VALUES ($legacyId, 'ROLE_ADMIN')");
        self::assertStringContainsString('identidad administrativa actual o histórica', $this->cli('first-admin')->getErrorOutput());
        $db->exec("UPDATE usuario_roles SET rol='PRENSA' WHERE usuario_id=$legacyId");

        $password = bin2hex(random_bytes(20));
        $duplicate = $this->interactive('LEGACY@example.test');
        self::assertSame(1, $duplicate->getExitCode());
        self::assertStringContainsString('cuenta con ese correo', $duplicate->getOutput());
        $mismatch = $this->interactive('admin@example.test', $password, $password.'x');
        self::assertSame(1, $mismatch->getExitCode());
        self::assertStringContainsString('debe coincidir', $mismatch->getOutput());
        $tooLong = $this->interactive('admin@example.test', str_repeat('A', 73));
        self::assertSame(1, $tooLong->getExitCode());

        $setup = DisposableDatabase::connect();
        $setup->exec("CREATE TRIGGER provision_reject_fixture BEFORE INSERT ON usuario_roles FOR EACH ROW
            BEGIN IF EXISTS (SELECT 1 FROM usuarios WHERE id=NEW.usuario_id AND email='rollback@example.test')
            THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='fixture rollback'; END IF; END");
        $rejected = $this->interactive('rollback@example.test', $password);
        self::assertSame(1, $rejected->getExitCode());
        self::assertStringNotContainsString('fixture rollback', $rejected->getOutput());
        self::assertSame(0, (int) $db->query("SELECT COUNT(*) FROM usuarios WHERE email='rollback@example.test'")->fetchColumn());
        self::assertSame(0, (int) $db->query("SELECT COUNT(*) FROM auth_mutexes WHERE name='provision-first-admin'")->fetchColumn());

        $admin = $this->interactive('  ADMIN@example.test  ', $password);
        self::assertSame(0, $admin->getExitCode());
        $saved = $db->query("SELECT id, rol, activo, token_version, password FROM usuarios WHERE email='admin@example.test'")->fetch();
        self::assertSame('SUPER_ADMIN', $saved['rol']);
        self::assertSame(1, (int) $saved['activo']);
        self::assertSame(0, (int) $saved['token_version']);
        self::assertTrue(password_verify($password, $saved['password']));
        self::assertSame(['SUPER_ADMIN'], $db->query('SELECT rol FROM usuario_roles WHERE usuario_id='.(int) $saved['id'])->fetchAll(\PDO::FETCH_COLUMN));
        self::assertSame(0, (int) $db->query('SELECT COUNT(*) FROM auth_sessions')->fetchColumn());
        foreach (['site-content', 'first-admin'] as $operation) {
            self::assertStringContainsString('ya se ejecutó', $this->cli($operation)->getErrorOutput());
        }
        $db->exec("UPDATE portal_site_content SET contenido_json='{}' WHERE id=1");
        self::assertSame(1, $this->cli('site-content')->getExitCode());
        self::assertSame('{}', $db->query('SELECT contenido_json FROM portal_site_content WHERE id=1')->fetchColumn());
        $db->exec("UPDATE auth_mutexes SET name='fixture-suspended-site' WHERE name='provision-site-content'");
        $db->exec('UPDATE portal_site_content SET id=17 WHERE id=1');
        try {
            $historical = $this->cli('site-content');
            self::assertSame(1, $historical->getExitCode());
            self::assertStringContainsString('Ya existe contenido institucional', $historical->getErrorOutput());
            $status = $this->cli('status');
            self::assertSame(1, $status->getExitCode());
            self::assertStringContainsString('FALTA ID=1', $status->getOutput());
            self::assertSame('{}', $db->query('SELECT contenido_json FROM portal_site_content WHERE id=17')->fetchColumn());
        } finally {
            $db->prepare('UPDATE portal_site_content SET id=1, contenido_json=? WHERE id=17')->execute([$document]);
            $db->exec("UPDATE auth_mutexes SET name='provision-site-content' WHERE name='fixture-suspended-site'");
        }
        self::assertSame($saved['password'], $db->query('SELECT password FROM usuarios WHERE id='.(int) $saved['id'])->fetchColumn());

        $status = $this->cli('status');
        self::assertSame(0, $status->getExitCode());
        self::assertStringContainsString('id=1: VÁLIDO', $status->getOutput());
        self::assertSame(2, substr_count($status->getOutput(), 'REGISTRADO'));
        self::assertStringNotContainsString('admin@example.test', $status->getOutput());

        $client = LocalHttps::client(['cookies' => new CookieJar]);
        $content = $client->get('/api/site-content');
        self::assertSame(200, $content->getStatusCode());
        self::assertSame($document, (string) $content->getBody());
        self::assertSame(401, $client->get('/api/admin/site-content')->getStatusCode());
        $login = $client->post('/api/auth/login', [
            'headers' => ['Origin' => 'https://localhost:18443', 'Sec-Fetch-Site' => 'same-origin'],
            'json' => ['email' => 'admin@example.test', 'password' => $password],
        ]);
        self::assertSame(200, $login->getStatusCode());
        self::assertSame(['SUPER_ADMIN'], json_decode((string) $login->getBody(), true, flags: JSON_THROW_ON_ERROR)['roles']);
        self::assertSame($document, (string) $client->get('/api/admin/site-content')->getBody());
        $news = $client->get('/api/noticias');
        self::assertSame(200, $news->getStatusCode());
        self::assertSame([], json_decode((string) $news->getBody(), true, flags: JSON_THROW_ON_ERROR)['content']);
        foreach (['/api/noticias/destacadas', '/api/hacienda', '/api/proveedores/documentos'] as $route) {
            $response = $client->get($route);
            self::assertSame(200, $response->getStatusCode(), $route);
            self::assertSame([], json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR), $route);
        }
        self::assertFileDoesNotExist($marker);
    }

    private function command(string $operation): Process
    {
        return new Process(['sh', 'docker/local-env.sh', 'php', 'deploy/iwinds/provision-staging.php', $operation, '--disposable'], timeout: 12);
    }

    private function cli(string $operation): Process
    {
        $process = $this->command($operation);
        $process->run();

        return $process;
    }

    private function interactive(string $email, ?string $password = null, ?string $confirmation = null): Process
    {
        $input = new InputStream;
        $process = new Process(['script', '-qef', '-E', 'always', '-c', $this->command('first-admin')->getCommandLine(), '/dev/null'], input: $input, timeout: 12);
        $process->start();
        $answers = ['Nombre de la persona' => 'Administración de prueba', 'Correo de la persona' => $email];
        if ($password !== null) {
            $answers += ['Contraseña nueva' => $password, 'Confirmar contraseña' => $confirmation ?? $password];
        }
        foreach ($answers as $prompt => $answer) {
            self::assertTrue($process->waitUntil(fn (): bool => str_contains($process->getOutput(), $prompt)), $prompt);
            $input->write($answer."\n");
        }
        $input->close();
        $process->wait();
        if ($password !== null) {
            self::assertFalse(str_contains($process->getOutput(), $password), 'La terminal expuso la contraseña sintética.');
        }

        return $process;
    }
}
