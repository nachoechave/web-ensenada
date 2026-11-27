<?php

namespace App\Http\Controllers;

use App\Hacienda\DocumentStore;
use App\Hacienda\PublicationInput;
use App\Http\DownloadStream;
use App\News\NewsDto;
use App\News\NewsImageEncoder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

final class HaciendaDownloadsController extends Controller
{
    public function show(Request $request, string $nombre, DocumentStore $store): Response
    {
        $flag = $request->query('descargar', '');
        $flag = is_string($flag) ? strtolower(trim($flag)) : null;
        if (! in_array($flag, ['', 'false', 'off', 'no', '0', 'true', 'on', 'yes', '1'], true)) {
            return NewsDto::error($request, 400);
        }
        $row = DB::table('archivos_hacienda as a')
            ->join('publicaciones_hacienda as p', 'p.id', '=', 'a.publicacion_hacienda_id')
            ->where('a.nombre_almacenado', $nombre)->where('p.estado', 'PUBLICADA')
            ->first(['a.nombre_almacenado', 'a.tipo_mime', 'a.tamanio', 'p.estado']);
        if ($row === null || $row->estado !== 'PUBLICADA' || $row->nombre_almacenado !== $nombre) {
            return NewsDto::error($request, 404);
        }

        return $this->download($request, $store, $row, in_array($flag, ['true', 'on', 'yes', '1'], true));
    }

    public function showAdmin(Request $request, string $id, string $archivoId, DocumentStore $store): Response
    {
        $number = PublicationInput::id($id);
        $document = PublicationInput::id($archivoId);
        if ($number === null || $document === null) {
            return NewsDto::error($request, 400);
        }
        $row = DB::table('archivos_hacienda')->where('publicacion_hacienda_id', $number)->where('id', $document)
            ->first(['nombre_almacenado', 'tipo_mime', 'tamanio']);

        return $row === null ? NewsDto::error($request, 404) : $this->download($request, $store, $row, true);
    }

    private function download(Request $request, DocumentStore $store, object $row, bool $attachment): Response
    {
        $name = $row->nombre_almacenado;
        $extension = pathinfo($name, PATHINFO_EXTENSION);
        $limit = $extension === 'pdf' ? DocumentStore::MAX_BYTES : NewsImageEncoder::MAX_BYTES;
        if ((DocumentStore::MIME_TYPES[$extension] ?? null) !== $row->tipo_mime || (int) $row->tamanio > $limit
            || ($opened = $store->open($name)) === null) {
            return NewsDto::error($request, 404);
        }
        [$source, $size] = $opened;
        $signature = DocumentStore::SIGNATURES[$extension];
        if ($size !== (int) $row->tamanio || fread($source, strlen($signature)) !== $signature || ! rewind($source)) {
            fclose($source);

            return NewsDto::error($request, 404);
        }

        $disposition = $attachment ? 'attachment' : 'inline';
        // The stored ASCII name is already restricted to the safe alphabet used by Spring.
        $headers = [
            'Content-Type' => $row->tipo_mime,
            'Content-Disposition' => $disposition.'; filename="'.$name.'"; filename*=UTF-8\'\''.$name,
            'Content-Security-Policy' => "sandbox; default-src 'none'",
            'Accept-Ranges' => 'bytes',
            'Content-Length' => (string) $size,
        ];

        return DownloadStream::send($request, $source, $size, $headers);
    }
}
