<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

try {
    require __DIR__.'/../vendor/autoload.php';
    $app = require __DIR__.'/../bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();
    if (config('database.default') !== 'mysql' || config('database.connections.mysql.username') !== 'auth_runtime'
        || ! DB::table('auth_mutexes')->where('name', 'schema-ready')->exists()
        || ! DB::table('auth_mutexes')->where('name', 'news-slug')->exists()
        || ! DB::table('auth_mutexes')->where('name', 'proveedores')->exists()
        || DB::table('auth_schema_baseline')->count() !== 9) {
        throw new RuntimeException('La API requiere un esquema preparado y un usuario DML.');
    }
    DB::table('auth_sessions')->limit(1)->get();
    DB::table('noticias')->limit(1)->get();
    DB::table('portal_site_content')->limit(1)->get(['id']);
    DB::table('publicaciones_hacienda')->limit(1)->get(['id']);
    DB::table('archivos_hacienda')->limit(1)->get(['id']);
    DB::table('documentos_proveedores')->limit(1)->get(['id']);
} catch (Throwable $exception) {
    fwrite(STDERR, 'La API no arranca: '.$exception->getMessage().PHP_EOL);
    exit(1);
}
