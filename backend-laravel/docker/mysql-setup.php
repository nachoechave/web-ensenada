<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Fixtures\HaciendaFixtures;
use Tests\Fixtures\NewsFixtures;

try {
    require __DIR__.'/../vendor/autoload.php';
    $app = require __DIR__.'/../bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();
    if (config('database.default') !== 'mysql' || config('database.connections.mysql.username') !== 'auth_setup') {
        throw new RuntimeException('Preparación exclusiva de la instancia MySQL descartable.');
    }
    $core = [];
    foreach (['usuarios', 'usuario_roles', 'noticias', 'noticia_imagenes', 'auditoria_noticias', 'portal_site_content',
        'publicaciones_hacienda', 'archivos_hacienda', 'documentos_proveedores'] as $table) {
        $result = (array) DB::selectOne("SHOW CREATE TABLE $table");
        $core[$table] = preg_replace('/ AUTO_INCREMENT=\d+/', '', $result['Create Table']);
    }
    $fromStage8Sql = getenv('AUTH_STAGE8_SQL_FIXTURES') === 'true';
    if (! $fromStage8Sql && Artisan::call('migrate', ['--no-interaction' => true]) !== 0) {
        throw new RuntimeException(Artisan::output());
    }
    foreach ($core as $name => $definition) {
        $result = (array) DB::selectOne("SHOW CREATE TABLE $name");
        if (preg_replace('/ AUTO_INCREMENT=\d+/', '', $result['Create Table']) !== $definition) {
            throw new RuntimeException('Se alteró una tabla Flyway durante la preparación.');
        }
        DB::table('auth_schema_baseline')->insert(['name' => $name, 'definition' => $definition]);
    }
    if (Artisan::call('db:seed', ['--no-interaction' => true]) !== 0) {
        throw new RuntimeException(Artisan::output());
    }
    DB::transaction(fn () => NewsFixtures::insert(DB::connection()->getPdo()));
    DB::transaction(fn () => HaciendaFixtures::insert(DB::connection()->getPdo()));
    DB::table('portal_site_content')->insert([
        'id' => 1,
        'contenido_json' => trim(file_get_contents(__DIR__.'/../tests/Fixtures/site-content.json')),
    ]);
    if ((int) DB::selectOne("SELECT COUNT(*) AS total FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE() AND TRIGGER_NAME='revoke_auth_sessions'")->total !== 1) {
        throw new RuntimeException('Falta el trigger de revocación.');
    }
    if (! $fromStage8Sql) {
        DB::table('auth_mutexes')->insert([['name' => 'schema-ready'], ['name' => 'news-slug']]);
    }
    echo 'Esquema Flyway conservado; auxiliares preparados; API con usuario DML separado.'.PHP_EOL;
} catch (Throwable $exception) {
    fwrite(STDERR, 'Preparación MySQL fallida: '.$exception->getMessage().PHP_EOL);
    exit(1);
}
