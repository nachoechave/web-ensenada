<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== '/tmp/ensenada-auth.sqlite') {
    throw new RuntimeException('Preparación exclusiva del SQLite descartable.');
}

DB::transaction(function (): void {
    if (Schema::hasTable('portal_site_content')) {
        return;
    }
    DB::unprepared(file_get_contents(database_path('schema/site-content-flyway.sql')));
    DB::table('portal_site_content')->insert([
        'id' => 1,
        'contenido_json' => trim(file_get_contents(__DIR__.'/../tests/Fixtures/site-content.json')),
    ]);
});
