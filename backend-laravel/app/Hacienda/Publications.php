<?php

namespace App\Hacienda;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class Publications
{
    public const COLUMNS = ['p.id', 'p.titulo', 'p.descripcion', 'p.tipo', 'p.fecha_publicacion', 'p.estado'];

    private const FILE_COLUMNS = ['a.id as archivo_id', 'a.nombre_original', 'a.nombre_almacenado', 'a.tipo_mime', 'a.orden', 'a.tamanio'];

    public static function read(bool $public, ?int $id = null): array
    {
        // A single statement takes a consistent snapshot of publication state and document metadata.
        $rows = DB::table('publicaciones_hacienda as p')->leftJoin('archivos_hacienda as a', 'a.publicacion_hacienda_id', '=', 'p.id')
            ->when($public, fn ($query) => $query->where('p.estado', 'PUBLICADA'))
            ->when($id !== null, fn ($query) => $query->where('p.id', $id))
            ->orderByDesc('p.fecha_publicacion')->orderByDesc('p.id')->orderBy('a.orden')->orderBy('a.id')
            ->get([...self::COLUMNS, ...self::FILE_COLUMNS]);
        $result = [];
        foreach ($rows as $row) {
            $result[$row->id] ??= self::response($row);
            $file = self::document($row);
            if ($file !== null) {
                $result[$row->id]['archivos'][] = $file;
            }
        }

        return array_values($public
            ? array_filter($result, fn (array $row): bool => $row['estado'] === 'PUBLICADA' && $row['archivos'] !== [])
            : $result);
    }

    /** Lock the publication row before calling this in a write transaction. */
    public static function lockDocuments(int $id): array
    {
        return self::documents(self::lockDocumentRows($id));
    }

    /** Lock the publication row before calling this in a write transaction. */
    public static function lockDocumentRows(int $id): Collection
    {
        return DB::table('archivos_hacienda as a')->where('a.publicacion_hacienda_id', $id)
            ->orderBy('a.orden')->orderBy('a.id')->lockForUpdate()->get(self::FILE_COLUMNS);
    }

    public static function documents(Collection $rows): array
    {
        return $rows->map(self::document(...))->filter()->values()->all();
    }

    public static function response(object $row, array $files = []): array
    {
        return [
            'id' => (int) $row->id,
            'titulo' => $row->titulo,
            'descripcion' => $row->descripcion,
            'tipo' => $row->tipo,
            'fechaPublicacion' => $row->fecha_publicacion,
            'estado' => $row->estado,
            'archivos' => $files,
        ];
    }

    private static function document(object $row): ?array
    {
        if ($row->archivo_id === null || ! preg_match('~^'.DocumentStore::FILENAME.'$~D', $row->nombre_almacenado)) {
            return null;
        }

        return [
            'id' => (int) $row->archivo_id,
            'nombreOriginal' => basename(str_replace('\\', '/', $row->nombre_original)),
            'url' => '/uploads/hacienda/'.$row->nombre_almacenado,
            'tipoMime' => $row->tipo_mime,
            'orden' => (int) $row->orden,
            'tamanio' => (int) $row->tamanio,
        ];
    }
}
