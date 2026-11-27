<?php

namespace Tests\MySql;

use App\Models\Usuario;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FlywaySchemaTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_the_core_ddl_is_copied_exactly_from_flyway_v1_and_v3(): void
    {
        $v1 = file_get_contents('/audit/flyway/V1__portal_prensa.sql');
        $v3 = file_get_contents('/audit/flyway/V3__jwt_token_version.sql');
        $expected = rtrim(explode('CREATE TABLE noticias', $v1)[0])."\n".trim($v3)."\n";
        $actual = file_get_contents(database_path('schema/mysql-auth-flyway.sql'));

        $this->assertSame(str_replace("\r\n", "\n", $expected), str_replace("\r\n", "\n", $actual));
    }

    public function test_mysql_preserves_signed_ids_types_defaults_and_constraints(): void
    {
        $columns = collect(DB::select("SELECT TABLE_NAME,COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('usuarios','usuario_roles')"))
            ->keyBy(fn ($column) => $column->TABLE_NAME.'.'.$column->COLUMN_NAME);

        $this->assertSame('bigint', $columns['usuarios.id']->COLUMN_TYPE);
        $this->assertSame('bigint', $columns['usuario_roles.usuario_id']->COLUMN_TYPE);
        $this->assertSame('varchar(255)', $columns['usuarios.password']->COLUMN_TYPE);
        $this->assertSame('tinyint(1)', $columns['usuarios.activo']->COLUMN_TYPE);
        $this->assertSame('0', (string) $columns['usuarios.token_version']->COLUMN_DEFAULT);
        foreach ($columns as $column) {
            $this->assertSame('NO', $column->IS_NULLABLE);
        }
        $indexes = DB::select("SELECT INDEX_NAME,NON_UNIQUE,GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS columns_list FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='usuario_roles' GROUP BY INDEX_NAME,NON_UNIQUE");
        $this->assertCount(1, $indexes);
        $this->assertSame('PRIMARY', $indexes[0]->INDEX_NAME);
        $this->assertSame('usuario_id,rol', $indexes[0]->columns_list);
        $foreign = DB::selectOne("SELECT CONSTRAINT_NAME,UPDATE_RULE,DELETE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='usuario_roles'");
        $this->assertSame('fk_usuario_roles', $foreign->CONSTRAINT_NAME);
        $this->assertSame('NO ACTION', $foreign->UPDATE_RULE);
        $this->assertSame('NO ACTION', $foreign->DELETE_RULE);
    }

    public static function bcryptVectors(): array
    {
        $fixture = json_decode(file_get_contents(__DIR__.'/../Fixtures/java-bcrypt.json'), true, flags: JSON_THROW_ON_ERROR);

        return array_map(fn ($row) => [$row['password'], $row['hash']], $fixture['vectors']);
    }

    #[DataProvider('bcryptVectors')]
    public function test_java_bcrypt_hashes_stored_in_mysql_authenticate_without_rehash(string $password, string $hash): void
    {
        $user = Usuario::factory()->create(['password' => $hash]);

        $this->postJson('https://localhost:18443/api/auth/login', ['email' => $user->email, 'password' => $password], [
            'Origin' => 'https://localhost:18443', 'Sec-Fetch-Site' => 'same-origin',
        ])->assertOk()->assertJsonPath('roles', ['PRENSA']);

        $this->assertSame($hash, $user->fresh()->password);
    }
}
