<?php

namespace App\Http\Controllers;

use App\Auth\AuthTransactions;
use App\Auth\JwtSessions;
use App\Hacienda\DocumentStore;
use App\Hacienda\DocumentUpload;
use App\Hacienda\PublicationInput;
use App\Hacienda\Publications;
use App\News\NewsDto;
use App\News\NewsImageEncoder;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use JsonException;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class HaciendaController extends Controller
{
    private const ROLES = ['HACIENDA', 'SUPER_ADMIN'];

    public function __construct(private JwtSessions $sessions) {}

    public function index(): Response
    {
        return response()->json(Publications::read(true));
    }

    public function indexAdmin(): Response
    {
        return response()->json(Publications::read(false));
    }

    public function show(Request $request, string $id): Response
    {
        return $this->detail($request, $id, true);
    }

    public function showAdmin(Request $request, string $id): Response
    {
        return $this->detail($request, $id, false);
    }

    public function store(Request $request): Response
    {
        $row = PublicationInput::parse($request->getContent());
        if ($row === null || $row['estado'] === 'PUBLICADA') {
            return NewsDto::error($request, 400);
        }

        return $this->write($request, function () use ($row): Response {
            $id = DB::table('publicaciones_hacienda')->insertGetId($row);

            return response()->json(Publications::response((object) ['id' => $id, ...$row]), 201);
        });
    }

    public function update(Request $request, string $id): Response
    {
        $number = PublicationInput::id($id);
        $row = PublicationInput::parse($request->getContent());
        if ($number === null || $row === null) {
            return NewsDto::error($request, 400);
        }

        return $this->write($request, function () use ($request, $number, $row): Response {
            $publication = DB::table('publicaciones_hacienda')->where('id', $number);
            if ($publication->lockForUpdate()->first(['id']) === null) {
                return NewsDto::error($request, 404);
            }
            $documents = Publications::lockDocuments($number);
            if ($row['estado'] === 'PUBLICADA' && $documents === []) {
                return NewsDto::error($request, 400);
            }
            $publication->update($row);

            return response()->json(Publications::response((object) ['id' => $number, ...$row], $documents));
        });
    }

    public function archive(Request $request, string $id): Response
    {
        $number = PublicationInput::id($id);
        if ($number === null) {
            return NewsDto::error($request, 400);
        }

        return $this->write($request, function () use ($request, $number): Response {
            $publication = DB::table('publicaciones_hacienda')->where('id', $number);
            if ($publication->lockForUpdate()->first(['id']) === null) {
                return NewsDto::error($request, 404);
            }
            $publication->update(['estado' => 'ARCHIVADA']);

            return response()->noContent();
        });
    }

    public function attach(Request $request, string $id, DocumentStore $store, NewsImageEncoder $encoder): Response
    {
        $type = trim(explode(';', $request->header('Content-Type', ''), 2)[0]);
        if (strcasecmp($type, 'multipart/form-data') !== 0) {
            return NewsDto::error($request, 415);
        }
        $number = PublicationInput::id($id);
        $file = $request->file('archivo');
        if ($file instanceof UploadedFile && $file->getError() === UPLOAD_ERR_INI_SIZE) {
            return response('', 413);
        }
        if ($file instanceof UploadedFile && in_array($file->getError(), [UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE], true)) {
            return $this->storageFailure($request);
        }
        if ($number === null || ! $file instanceof UploadedFile || ! $file->isValid()) {
            return NewsDto::error($request, 400);
        }

        $created = [];
        $upload = null;
        try {
            $upload = DocumentUpload::prepare($file, $encoder);
            $response = $this->write($request, function () use ($request, $number, $store, $upload, &$created): Response {
                $publication = $this->lockPublication($number);
                if ($publication === null) {
                    return NewsDto::error($request, 404);
                }
                $documents = Publications::lockDocumentRows($number);
                if ($documents->count() >= 50) {
                    return NewsDto::error($request, 400);
                }
                if (! rewind($upload['stream'])) {
                    throw new RuntimeException;
                }
                $name = $store->put($upload['stream'], $upload['extension'], $upload['size']);
                $created[] = $name;
                DB::table('archivos_hacienda')->insert([
                    'publicacion_hacienda_id' => $number, 'nombre_original' => $upload['original'],
                    'nombre_almacenado' => $name, 'tipo_mime' => $upload['mime'],
                    'orden' => $documents->count(), 'tamanio' => $upload['size'],
                ]);

                return response()->json(Publications::response($publication, Publications::lockDocuments($number)), 201);
            });
            if ($response->getStatusCode() === 201) {
                array_pop($created);
            }

            return $response;
        } catch (InvalidArgumentException) {
            return NewsDto::error($request, 400);
        } catch (RuntimeException) {
            return $this->storageFailure($request);
        } finally {
            if ($upload !== null) {
                fclose($upload['stream']);
            }
            foreach ($created as $name) {
                $this->cleanFailedUpload($store, $name);
            }
        }
    }

    public function detach(Request $request, string $id, string $archivoId): Response
    {
        $number = PublicationInput::id($id);
        $document = PublicationInput::id($archivoId);
        if ($number === null || $document === null) {
            return NewsDto::error($request, 400);
        }

        return $this->write($request, function () use ($request, $number, $document): Response {
            $publication = $this->lockPublication($number);
            if ($publication === null) {
                return NewsDto::error($request, 404);
            }
            $documents = Publications::lockDocumentRows($number)->keyBy('archivo_id');
            if (! $documents->has($document)) {
                return NewsDto::error($request, 404);
            }
            $documents->forget($document);
            if ($publication->estado === 'PUBLICADA' && Publications::documents($documents) === []) {
                return NewsDto::error($request, 400);
            }
            DB::table('archivos_hacienda')->where('publicacion_hacienda_id', $number)->where('id', $document)->delete();
            $this->setDocumentOrder($number, $documents->keys()->all());

            return response()->json(Publications::response($publication, Publications::lockDocuments($number)));
        });
    }

    public function order(Request $request, string $id): Response
    {
        $number = PublicationInput::id($id);
        try {
            $body = json_decode($request->getContent(), false, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return NewsDto::error($request, 400);
        }
        $ids = $body->ids ?? null;
        if ($number === null || ! is_array($ids) || count($ids) > 50
            || array_filter($ids, fn ($item) => ! is_int($item) || $item < 1) !== []) {
            return NewsDto::error($request, 400);
        }

        return $this->write($request, function () use ($request, $number, $ids): Response {
            $publication = $this->lockPublication($number);
            if ($publication === null) {
                return NewsDto::error($request, 404);
            }
            $current = Publications::lockDocumentRows($number)->pluck('archivo_id')->map(fn ($item): int => (int) $item)->all();
            $requested = $ids;
            sort($current);
            sort($requested);
            if ($current !== $requested) {
                return NewsDto::error($request, 400);
            }
            $this->setDocumentOrder($number, $ids);

            return response()->json(Publications::response($publication, Publications::lockDocuments($number)));
        });
    }

    private function lockPublication(int $id): ?object
    {
        return DB::table('publicaciones_hacienda')->where('id', $id)->lockForUpdate()->first();
    }

    private function setDocumentOrder(int $publication, array $ids): void
    {
        foreach ($ids as $position => $id) {
            DB::table('archivos_hacienda')->where('publicacion_hacienda_id', $publication)->where('id', $id)
                ->update(['orden' => $position]);
        }
    }

    private function cleanFailedUpload(DocumentStore $store, string $name): void
    {
        try {
            // Only discard this request's file after confirming that no committed row references it.
            if (DB::transactionLevel() === 0
                && ! DB::table('archivos_hacienda')->where('nombre_almacenado', $name)->exists()
                && ! $store->removeUnassociatedUpload($name)) {
                Log::warning('No se pudo retirar un archivo de Hacienda sin asociación.');
            }
        } catch (Throwable) {
            Log::warning('No se pudo confirmar el estado de un archivo de Hacienda tras la transacción.');
        }
    }

    private function storageFailure(Request $request): Response
    {
        Log::warning('Falló el almacenamiento de un documento de Hacienda.');

        return NewsDto::error($request, 500);
    }

    private function detail(Request $request, string $id, bool $public): Response
    {
        $number = PublicationInput::id($id);
        if ($number === null) {
            return NewsDto::error($request, 400);
        }
        $row = Publications::read($public, $number)[0] ?? null;

        return $row === null ? NewsDto::error($request, 404) : response()->json($row);
    }

    private function write(Request $request, Closure $operation): Response
    {
        try {
            return AuthTransactions::run(function () use ($request, $operation): Response {
                $actor = $this->sessions->authenticate($this->sessions->token($request), lockUser: true);
                if ($actor === null || ! $actor->hasAnyRole(self::ROLES)) {
                    return NewsDto::error($request, $actor === null ? 401 : 403);
                }

                return $operation();
            });
        } catch (QueryException $error) {
            Log::warning('Operación de Hacienda revertida.');

            return NewsDto::error($request, in_array($error->errorInfo[1] ?? null, [5, 6, 1205, 1213], true) ? 503 : 500);
        }
    }
}
