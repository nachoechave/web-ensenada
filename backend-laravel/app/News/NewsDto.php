<?php

namespace App\News;

use Illuminate\Database\Connection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\Response;

final class NewsDto
{
    public const COLUMNS = ['id', 'titulo', 'slug', 'bajada', 'contenido', 'imagen', 'categoria', 'fecha_publicacion', 'estado', 'destacada'];

    public static function error(Request $request, int $status, ?string $detail = null): JsonResponse
    {
        return response()->json([
            'timestamp' => now('UTC')->format('Y-m-d\TH:i:s.v\Z'),
            'status' => $status,
            'error' => Response::$statusTexts[$status],
            'path' => $request->getBaseUrl().$request->getPathInfo(),
            ...($detail === null ? [] : ['detail' => $detail]),
        ], $status);
    }

    public static function list(Connection $db, Collection $rows, string $gallery = 'noticia_imagenes'): array
    {
        if ($rows->isEmpty()) {
            return [];
        }
        $images = $db->table($gallery)->whereIn('noticia_id', $rows->pluck('id')->all())
            ->orderBy('orden')->get(['noticia_id', 'orden', 'url'])->groupBy('noticia_id');

        return $rows->map(fn (object $row): array => [
            'id' => (int) $row->id,
            'titulo' => $row->titulo,
            'slug' => $row->slug,
            'bajada' => $row->bajada,
            'contenido' => $row->contenido,
            'imagen' => $row->imagen,
            'imagenes' => $images->get($row->id, collect())->pluck('url')->all(),
            'categoria' => $row->categoria,
            'fechaPublicacion' => $row->fecha_publicacion,
            'estado' => $row->estado,
            'destacada' => (bool) $row->destacada,
        ])->all();
    }
}
