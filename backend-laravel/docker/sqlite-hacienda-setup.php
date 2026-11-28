<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Fixtures\HaciendaFixtures;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== '/tmp/ensenada-auth.sqlite') {
    throw new RuntimeException('Preparación exclusiva del SQLite descartable.');
}

DB::transaction(function (): void {
    $publications = Schema::hasTable('publicaciones_hacienda');
    $documents = Schema::hasTable('archivos_hacienda');
    if ($publications !== $documents) {
        throw new RuntimeException('El esquema descartable de Hacienda está incompleto.');
    }
    if (! $publications) {
        DB::unprepared(file_get_contents(database_path('schema/sqlite-hacienda-fixture.sql')));
        HaciendaFixtures::insert(DB::connection()->getPdo());
    }
});
