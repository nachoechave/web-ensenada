<?php

namespace Tests\Stage8;

use App\Hacienda\DocumentStore;
use App\Proveedores\ProviderStore;
use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;
use Tests\Fixtures\HaciendaDocuments;
use Tests\Http\DisposableDatabase;
use Tests\Http\EditorialFixture;
use Tests\Http\LocalHttps;

final class SchemaInitTest extends TestCase
{
    protected function setUp(): void
    {
        self::assertSame('true', getenv('AUTH_STAGE8_SQL_FIXTURES'));
        self::assertSame('mysql-auth', getenv('DB_HOST'));
        self::assertSame('ensenada_auth_test', getenv('DB_DATABASE'));
    }

    public function test_final_flyway_definitions_match_the_original_ddl(): void
    {
        $db = DisposableDatabase::connect();
        $body = static function (string $file): string {
            return trim(strstr(str_replace("\r\n", "\n", file_get_contents(__DIR__.'/../../deploy/iwinds/'.$file)), '-- Flyway V1 + V3.'));
        };
        self::assertSame($body('schema-init.sql'), $body('schema-init-mariadb.sql'));
        $tables = ['usuarios', 'usuario_roles', 'noticias', 'noticia_imagenes', 'auditoria_noticias', 'portal_site_content',
            'publicaciones_hacienda', 'archivos_hacienda', 'documentos_proveedores'];
        foreach ($tables as $table) {
            $historical = $db->query("SHOW CREATE TABLE ensenada_auth_unit.$table")->fetch(PDO::FETCH_NUM)[1];
            $installed = $db->query("SHOW CREATE TABLE $table")->fetch(PDO::FETCH_NUM)[1];
            self::assertSame($historical, preg_replace('/ AUTO_INCREMENT=\d+/', '', $installed), $table);
        }
        self::assertSame(0, (int) $db->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('auth_migration_history','flyway_schema_history')")->fetchColumn());
        $collation = getenv('DB_COLLATION');
        self::assertContains($collation, ['utf8mb4_0900_ai_ci', 'utf8mb4_unicode_520_ci']);
        self::assertSame(1, (int) $db->query("SELECT _utf8mb4'Árbol' COLLATE $collation = _utf8mb4'arbol'")->fetchColumn());
        self::assertSame($collation === 'utf8mb4_unicode_520_ci' ? 1 : 0,
            (int) $db->query("SELECT _utf8mb4'nota' COLLATE $collation = _utf8mb4'nota '")->fetchColumn());
    }

    public function test_constraints_and_trigger_are_enforced(): void
    {
        $db = DisposableDatabase::connect();
        $db->beginTransaction();
        try {
            $checkError = str_contains($db->query('SELECT VERSION()')->fetchColumn(), 'MariaDB') ? 4025 : 3819;
            foreach ([
                [1452, "INSERT INTO usuario_roles VALUES (-999, 'PRENSA')"],
                [1452, "INSERT INTO noticia_imagenes VALUES (-999, 0, '/synthetic.jpg')"],
                [1452, "INSERT INTO archivos_hacienda (publicacion_hacienda_id,nombre_original,nombre_almacenado,tipo_mime,orden,tamanio) VALUES (-999,'sintético','invalid.pdf','application/pdf',0,10)"],
                [1452, "INSERT INTO auth_sessions VALUES ('no-usuario', -999, 0, 1, 2)"],
                [$checkError, "INSERT INTO publicaciones_hacienda (titulo,descripcion,tipo,fecha_publicacion,estado) VALUES ('sintético','','PRESUPUESTO','2026-03-02','INVALIDO')"],
                [$checkError, "INSERT INTO archivos_hacienda (publicacion_hacienda_id,nombre_original,nombre_almacenado,tipo_mime,orden,tamanio) VALUES (301,'sintético','invalid.pdf','application/pdf',0,0)"],
                [1062, "INSERT INTO usuarios (nombre,email,password,rol,activo) VALUES ('sintético','HACIENDA@example.test','fixture','HACIENDA',1)"],
            ] as [$expected, $sql]) {
                try {
                    $db->exec($sql);
                    self::fail('La restricción debía rechazar la fila sintética.');
                } catch (PDOException $error) {
                    self::assertSame($expected, $error->errorInfo[1]);
                }
            }
            $db->exec("INSERT INTO usuarios (nombre,email,password,rol,activo) VALUES ('sintético','trigger-stage8@example.test','fixture','HACIENDA',1)");
            $id = (int) $db->lastInsertId();
            $db->exec("INSERT INTO auth_sessions VALUES ('synthetic-session', $id, 0, 1, 2)");
            $db->exec("UPDATE usuarios SET email='TRIGGER-stage8@example.test' WHERE id=$id");
            self::assertSame(1, (int) $db->query("SELECT token_version FROM usuarios WHERE id=$id")->fetchColumn());
            self::assertSame(0, (int) $db->query("SELECT COUNT(*) FROM auth_sessions WHERE usuario_id=$id")->fetchColumn());
        } finally {
            $db->rollBack();
        }
    }

    public function test_runtime_account_can_read_news_but_cannot_delete_users_or_access_the_oracle(): void
    {
        $runtime = DisposableDatabase::connect(runtime: true);
        foreach (['noticias' => 'id', 'noticia_imagenes' => 'noticia_id'] as $table => $column) {
            self::assertNotFalse($runtime->query("SELECT $column FROM $table WHERE 1=0"));
        }
        foreach (['DELETE FROM usuarios WHERE 1=0', 'SELECT id FROM ensenada_auth_unit.noticias WHERE 1=0'] as $sql) {
            try {
                $runtime->exec($sql);
                self::fail('La cuenta de ejecución no debe tener este permiso.');
            } catch (PDOException $error) {
                self::assertSame(1142, $error->errorInfo[1]);
            }
        }
    }

    public function test_historical_ids_and_urls_survive_an_explicit_id_restore(): void
    {
        $db = DisposableDatabase::connect();
        $pdf = HaciendaDocuments::pdf();
        $publication = 900001;
        $document = 900002;
        $provider = 900003;
        $haciendaName = '00000000-0000-4000-8000-000000900002.pdf';
        $providerName = '00000000-0000-4000-8000-000000900003.pdf';
        $db->beginTransaction();
        $title = 'Histórico sintético 📄';
        $db->prepare("INSERT INTO publicaciones_hacienda VALUES (?, ?, '', 'PRESUPUESTO', '2026-03-02', 'PUBLICADA')")->execute([$publication, $title]);
        $db->prepare("INSERT INTO archivos_hacienda VALUES (?, ?, 'Histórico.pdf', ?, 'application/pdf', 0, ?)")->execute([$document, $publication, $haciendaName, strlen($pdf)]);
        $db->prepare("INSERT INTO documentos_proveedores VALUES (?, 'Histórico sintético', '', 'Histórico.pdf', ?, 'application/pdf', ?, 0, 1, '2026-03-02', '2026-03-02')")->execute([$provider, $providerName, strlen($pdf)]);
        $db->commit();
        foreach ([DocumentStore::DIRECTORY.'/'.$haciendaName, ProviderStore::DIRECTORY.'/'.$providerName] as $path) {
            file_put_contents($path, $pdf, LOCK_EX);
            chmod($path, 0600);
        }
        $client = LocalHttps::client();
        $historical = EditorialFixture::json($client->get('/api/hacienda/'.$publication));
        self::assertSame($publication, $historical['id']);
        self::assertSame($title, $historical['titulo']);
        self::assertSame($document, $historical['archivos'][0]['id']);
        self::assertSame('/uploads/hacienda/'.$haciendaName, $historical['archivos'][0]['url']);
        $providers = array_column(EditorialFixture::json($client->get('/api/proveedores/documentos')), null, 'id');
        self::assertSame('/uploads/proveedores/'.$providerName, $providers[$provider]['url']);
        self::assertSame($pdf, (string) $client->get('/uploads/hacienda/'.$haciendaName)->getBody());
        self::assertSame($pdf, (string) $client->get('/uploads/proveedores/'.$providerName)->getBody());
        self::assertGreaterThan($provider, (int) $db->query("SELECT AUTO_INCREMENT FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='documentos_proveedores'")->fetchColumn());
    }
}
