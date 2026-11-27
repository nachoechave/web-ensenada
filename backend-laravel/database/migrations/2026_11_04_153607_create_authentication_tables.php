<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $mysql = DB::getDriverName() === 'mysql';
        if ($mysql) {
            if (! Schema::hasTable('usuarios') && ! Schema::hasTable('usuario_roles')) {
                DB::unprepared(file_get_contents(database_path('schema/mysql-auth-flyway.sql')));
            } elseif (! Schema::hasTable('usuarios') || ! Schema::hasTable('usuario_roles') || ! Schema::hasColumn('usuarios', 'token_version')) {
                throw new RuntimeException('El esquema existente debe coincidir con Flyway V1 + V3; no se altera automáticamente.');
            }
        } else {
            Schema::create('usuarios', function (Blueprint $table): void {
                $table->id();
                $table->string('nombre');
                $table->string('email')->unique();
                $table->string('password');
                $table->string('rol');
                $table->boolean('activo');
                $table->bigInteger('token_version')->default(0);
            });
            Schema::create('usuario_roles', function (Blueprint $table): void {
                $table->foreignId('usuario_id')->constrained('usuarios');
                $table->string('rol');
                $table->primary(['usuario_id', 'rol']);
            });
        }
        Schema::create('auth_sessions', function (Blueprint $table): void {
            $table->string('token_hash', 64)->primary();
            $table->bigInteger('usuario_id');
            $table->foreign('usuario_id')->references('id')->on('usuarios');
            $table->bigInteger('token_version');
            $table->bigInteger('created_at');
            $table->bigInteger('expires_at')->index();
        });
        Schema::create('auth_login_windows', function (Blueprint $table): void {
            $table->string('ip_hash', 64)->primary();
            $table->unsignedInteger('attempts');
            $table->bigInteger('expires_at')->index();
        });
        Schema::create('auth_mutexes', function (Blueprint $table): void {
            $table->string('name', 40)->primary();
        });
        DB::table('auth_mutexes')->insert(['name' => 'login']);
        Schema::create('auth_schema_baseline', function (Blueprint $table): void {
            $table->string('name', 64)->primary();
            $table->longText('definition');
        });
        if ($mysql) {
            DB::unprepared(<<<'SQL'
                CREATE TRIGGER revoke_auth_sessions
                BEFORE UPDATE ON usuarios FOR EACH ROW
                BEGIN
                    IF NOT (CAST(OLD.password AS BINARY) <=> CAST(NEW.password AS BINARY))
                        OR NOT (CAST(OLD.email AS BINARY) <=> CAST(NEW.email AS BINARY))
                        OR (OLD.activo = 1 AND NEW.activo = 0) THEN
                        SET NEW.token_version = GREATEST(NEW.token_version, OLD.token_version + 1);
                        DELETE FROM auth_sessions WHERE usuario_id = OLD.id;
                    END IF;
                END
                SQL);
        } else {
            // The local invariant also protects direct SQL writes and bulk updates.
            DB::unprepared(<<<'SQL'
            CREATE TRIGGER revoke_auth_sessions
            AFTER UPDATE OF password, activo, email ON usuarios
            WHEN OLD.password <> NEW.password OR OLD.email <> NEW.email
                OR (OLD.activo = 1 AND NEW.activo = 0)
            BEGIN
                UPDATE usuarios SET token_version = token_version + 1 WHERE id = NEW.id;
                DELETE FROM auth_sessions WHERE usuario_id = NEW.id;
            END
            SQL);
        }
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS revoke_auth_sessions');
        Schema::dropIfExists('auth_schema_baseline');
        Schema::dropIfExists('auth_mutexes');
        Schema::dropIfExists('auth_login_windows');
        Schema::dropIfExists('auth_sessions');
        Schema::dropIfExists('usuario_roles');
        Schema::dropIfExists('usuarios');
    }
};
