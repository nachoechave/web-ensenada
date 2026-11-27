<?php

namespace Tests\Fixtures;

use PDO;

final class HaciendaFixtures
{
    public const API = '/api/admin/hacienda';

    public const PUBLIC_API = '/api/hacienda';

    public static function payload(array $overrides = []): array
    {
        return [
            'titulo' => 'Borrador sintético de prueba',
            'descripcion' => 'Descripción sintética de prueba.',
            'tipo' => 'PRESUPUESTO',
            'fechaPublicacion' => '2026-03-02',
            'estado' => 'BORRADOR',
            ...$overrides,
        ];
    }

    public static function insert(PDO $db): void
    {
        $parent = $db->prepare('INSERT INTO publicaciones_hacienda (id,titulo,descripcion,tipo,fecha_publicacion,estado) VALUES (?,?,?,?,?,?)');
        $parent->execute([301, 'Informe público sintético', 'Descripción pública sintética.', 'SITUACION_ECONOMICO_FINANCIERA', '2026-04-03', 'PUBLICADA']);
        $parent->execute([302, 'Borrador sintético reservado', 'Descripción privada de borrador.', 'PRESUPUESTO', '2026-04-06', 'BORRADOR']);
        $parent->execute([303, 'Archivo sintético reservado', 'Descripción privada de archivo.', 'ORDENANZA_FISCAL', '2026-03-01', 'ARCHIVADA']);
        $parent->execute([304, 'Ordenanza pública sintética', 'Segunda publicación sintética.', 'ORDENANZA_PRESUPUESTARIA', '2026-04-03', 'PUBLICADA']);
        self::attach($db, 301, 451, 'Público segundo.png', 'image/png', 1);
        self::attach($db, 301, 453, 'Público primero.pdf', 'application/pdf', 0);
        self::attach($db, 302, 454, 'Borrador reservado.pdf', 'application/pdf', 0);
        self::attach($db, 302, 452, 'Primero por ID.pdf', 'application/pdf', 0);
        self::attach($db, 303, 455, 'Archivo reservado.jpg', 'image/jpeg', 0);
        self::attach($db, 304, 456, 'Ordenanza sintética.pdf', 'application/pdf', 0);
    }

    public static function attach(PDO $db, int $publication, int $id, string $name = 'Documento sintético.pdf', string $mime = 'application/pdf', int $order = 0): void
    {
        $extension = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png'][$mime];
        $stored = sprintf('00000000-0000-4000-8000-%012d.%s', $id, $extension);
        $db->prepare('INSERT INTO archivos_hacienda (id,publicacion_hacienda_id,nombre_original,nombre_almacenado,tipo_mime,orden,tamanio) VALUES (?,?,?,?,?,?,?)')
            ->execute([$id, $publication, $name, $stored, $mime, $order, 4096 + $id]);
    }
}
