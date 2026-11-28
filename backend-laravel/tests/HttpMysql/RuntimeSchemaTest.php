<?php

namespace Tests\HttpMysql;

use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;
use Tests\Http\DisposableDatabase;

class RuntimeSchemaTest extends TestCase
{
    public function test_news_schema_uses_flyway_ddl_without_data_backfills(): void
    {
        $v1 = strstr(file_get_contents('/audit/flyway/V1__portal_prensa.sql'), 'CREATE TABLE noticias');
        $v5 = preg_replace('/UPDATE noticias\b.*?;/s', '', file_get_contents('/audit/flyway/V5__noticias_slug.sql'));
        $v99 = explode(';', file_get_contents('/audit/flyway/V99__noticia_imagenes.sql'), 2)[0].';';
        $normalize = fn (string $sql): string => trim(preg_replace('/\s+/', ' ', $sql));
        $this->assertSame($normalize($v1."\n".$v5."\n".$v99), $normalize(file_get_contents(__DIR__.'/../../database/schema/mysql-news-flyway.sql')));
    }

    public function test_the_api_still_uses_the_original_flyway_definitions(): void
    {
        $database = DisposableDatabase::connect(runtime: true);
        $definitions = $database->query('SELECT name,definition FROM auth_schema_baseline ORDER BY name')->fetchAll(PDO::FETCH_KEY_PAIR);
        $this->assertSame(['archivos_hacienda', 'auditoria_noticias', 'documentos_proveedores', 'noticia_imagenes', 'noticias',
            'portal_site_content', 'publicaciones_hacienda', 'usuario_roles', 'usuarios'], array_keys($definitions));
        foreach ($definitions as $table => $expected) {
            $actual = $database->query("SHOW CREATE TABLE $table")->fetch(PDO::FETCH_ASSOC)['Create Table'];
            $this->assertSame($expected, preg_replace('/ AUTO_INCREMENT=\d+/', '', $actual));
        }
        $this->assertMatchesRegularExpression('/^(?:8[.][04][.]|10[.]11[.][0-9]+-MariaDB)/', $database->query('SELECT VERSION()')->fetchColumn());
    }

    public function test_site_content_uses_flyway_v4_ddl_without_initial_data(): void
    {
        $v4 = explode(';', file_get_contents('/audit/flyway/V4__portal_site_content.sql'), 2)[0].';';
        $actual = file_get_contents(__DIR__.'/../../database/schema/site-content-flyway.sql');
        $this->assertSame(trim(str_replace("\r\n", "\n", $v4)), trim(str_replace("\r\n", "\n", $actual)));
        $database = DisposableDatabase::connect(runtime: true);
        $this->assertSame(65535, (int) $database->query("SELECT CHARACTER_OCTET_LENGTH FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'portal_site_content' AND COLUMN_NAME = 'contenido_json'")->fetchColumn());
    }

    public function test_proveedores_uses_the_original_flyway_v7_ddl(): void
    {
        $v7 = file_get_contents('/audit/flyway/V7__proveedores_documentos.sql');
        $actual = file_get_contents(__DIR__.'/../../database/schema/mysql-proveedores-flyway.sql');
        $this->assertSame(trim(str_replace("\r\n", "\n", $v7)), trim(str_replace("\r\n", "\n", $actual)));
    }

    public function test_the_api_database_principal_cannot_create_tables(): void
    {
        $database = DisposableDatabase::connect(runtime: true);
        try {
            $database->exec('CREATE TABLE auth_runtime_must_not_create (id BIGINT)');
            $this->fail('La API no debe tener permisos DDL.');
        } catch (PDOException $exception) {
            $this->assertSame('42000', $exception->getCode());
            $this->assertSame(1142, $exception->errorInfo[1]);
        }
    }
}
