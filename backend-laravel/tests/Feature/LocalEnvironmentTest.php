<?php

namespace Tests\Feature;

use App\Providers\AppServiceProvider;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class LocalEnvironmentTest extends TestCase
{
    public static function forbiddenConfigurations(): array
    {
        return [
            'not opted in' => [['auth_migration.local_only' => false]],
            'unsupported driver' => [['database.default' => 'pgsql']],
            'mysql not opted in' => [['database.default' => 'mysql', 'auth_migration.disposable_mysql' => false]],
            'external mysql host' => [['database.default' => 'mysql', 'auth_migration.disposable_mysql' => true, 'database.connections.mysql.host' => 'must-not-connect.invalid']],
            'external mysql database' => [['database.default' => 'mysql', 'auth_migration.disposable_mysql' => true, 'database.connections.mysql.database' => 'unrelated']],
            'external database url' => [['database.default' => 'sqlite', 'database.connections.sqlite.url' => 'mysql://must-not-connect.invalid/db']],
            'arbitrary sqlite' => [['database.default' => 'sqlite', 'database.connections.sqlite.database' => '/tmp/unrelated.sqlite']],
            'non local url' => [['app.url' => 'https://must-not-connect.invalid']],
            'http url' => [['app.url' => 'http://localhost:18443']],
            'missing jwt key' => [['auth_migration.jwt_secret' => '']],
        ];
    }

    #[DataProvider('forbiddenConfigurations')]
    public function test_boot_refuses_unsafe_configuration_before_any_connection(array $settings): void
    {
        config($settings);
        $this->expectException(RuntimeException::class);

        (new AppServiceProvider($this->app))->boot();
    }

    public function test_production_boot_is_disabled_even_with_other_valid_settings(): void
    {
        $this->app->instance('env', 'production');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Producción deshabilitada');

        (new AppServiceProvider($this->app))->boot();
    }
}
