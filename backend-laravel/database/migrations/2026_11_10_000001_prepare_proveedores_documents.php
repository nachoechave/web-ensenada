<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('documentos_proveedores')) {
            if (DB::getDriverName() === 'mysql') {
                DB::unprepared(file_get_contents(database_path('schema/mysql-proveedores-flyway.sql')));
            } else {
                Schema::create('documentos_proveedores', function (Blueprint $table): void {
                    $table->id();
                    $table->string('titulo', 180);
                    $table->string('descripcion', 500);
                    $table->string('nombre_original', 255);
                    $table->string('nombre_almacenado', 255)->unique('uk_documentos_proveedores_archivo');
                    $table->string('tipo_mime', 120);
                    $table->bigInteger('tamanio');
                    $table->integer('orden');
                    $table->boolean('activo')->default(true);
                    $table->dateTime('created_at');
                    $table->dateTime('updated_at');
                    $table->index(['activo', 'orden', 'id'], 'idx_documentos_proveedores_publicos');
                });
            }
        }
        DB::table('auth_mutexes')->insertOrIgnore(['name' => 'proveedores']);
    }

    public function down(): void
    {
        // This table may have been created by Flyway and contain historical documents.
    }
};
