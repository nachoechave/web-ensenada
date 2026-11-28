<?php

namespace App\Providers;

use App\Http\Middleware\StrictRequestForgery;
use App\Support\IwindsProduction;
use App\Support\IwindsStaging;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->environment('staging')) {
            IwindsStaging::verify($this->app);
        } elseif ($this->app->environment('production')) {
            config(['cors.paths' => []]);
            IwindsProduction::verify($this->app);
        } else {
            $this->verifyLocal();
        }
        StrictRequestForgery::useOriginOnly(true);
        StrictRequestForgery::allowSameSite(false);
    }

    private function verifyLocal(): void
    {
        if (! $this->app->environment(['local', 'testing']) || config('auth_migration.local_only') !== true) {
            throw new RuntimeException('Producción deshabilitada: esta etapa solo permite local/testing explícitos.');
        }
        $driver = config('database.default');
        $connection = config('database.connections.'.$driver, []);
        $safeSqlite = $driver === 'sqlite'
            && empty($connection['url'])
            && in_array($connection['database'] ?? '', [':memory:', '/tmp/ensenada-auth.sqlite'], true);
        $safeMysql = $driver === 'mysql'
            && config('auth_migration.disposable_mysql') === true
            && in_array($connection['collation'] ?? null, IwindsStaging::MYSQL_COLLATIONS, true)
            && empty($connection['url'])
            && ($connection['host'] ?? '') === 'mysql-auth'
            && (int) ($connection['port'] ?? 0) === 3306
            && empty($connection['unix_socket'])
            && in_array($connection['database'] ?? '', ['ensenada_auth_test', 'ensenada_auth_unit'], true)
            && in_array($connection['username'] ?? '', ['auth_setup', 'auth_runtime'], true);
        if (! $safeSqlite && ! $safeMysql) {
            throw new RuntimeException('Solo se admiten SQLite local y MySQL descartable explícitamente autorizado.');
        }
        $url = parse_url(config('app.url'));
        if (($url['scheme'] ?? '') !== 'https' || ($url['host'] ?? '') !== 'localhost'
            || strlen(config('auth_migration.jwt_secret')) < 32 || ! config('app.key')) {
            throw new RuntimeException('Se requieren HTTPS local y claves sintéticas efímeras.');
        }
    }
}
