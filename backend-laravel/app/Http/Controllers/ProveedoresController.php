<?php

namespace App\Http\Controllers;

use App\Auth\AuthTransactions;
use App\Auth\JwtSessions;
use App\Hacienda\PublicationInput;
use App\Http\DownloadStream;
use App\News\NewsDto;
use App\Proveedores\ProviderFile;
use App\Proveedores\ProviderStore;
use Closure;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use JsonException;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class ProveedoresController extends Controller
{
    private const ROLES = ['HACIENDA', 'SUPER_ADMIN'];

    public function __construct(private JwtSessions $sessions) {}

    public function index(): Response
    {
        return response()->json($this->documents(true));
    }

    public function indexAdmin(): Response
    {
        return response()->json($this->documents(false));
    }

    public function download(Request $request, string $nombre, ProviderStore $store): Response
    {
        $row = $this->table()->where('nombre_almacenado', $nombre)->where('activo', 1)->first();
        $extension = pathinfo($nombre, PATHINFO_EXTENSION);
        if ($row === null || $row->nombre_almacenado !== $nombre || (int) $row->activo !== 1
            || (ProviderStore::MIME[$extension] ?? null) !== $row->tipo_mime || ($opened = $store->open($nombre)) === null) {
            return NewsDto::error($request, 404);
        }
        [$stream, $size] = $opened;
        if ($size !== (int) $row->tamanio) {
            fclose($stream);

            return NewsDto::error($request, 404);
        }
        $original = ProviderFile::original($row->nombre_original);
        $ascii = preg_replace('/[^\x20-\x7e]/u', '_', $original);
        $filename = addcslashes($ascii, '"\\');

        return DownloadStream::send($request, $stream, $size, [
            'Content-Type' => $row->tipo_mime,
            'Content-Disposition' => 'attachment; filename="'.$filename.'"; filename*=UTF-8\'\''.rawurlencode($original),
            'Content-Security-Policy' => "sandbox; default-src 'none'",
            'Accept-Ranges' => 'bytes',
            'Content-Length' => (string) $size,
        ]);
    }

    public function store(Request $request, ProviderStore $store): Response
    {
        if (strcasecmp(trim(explode(';', $request->header('Content-Type', ''), 2)[0]), 'multipart/form-data') !== 0) {
            return NewsDto::error($request, 415);
        }
        $file = $request->file('archivo');
        if ($file instanceof UploadedFile && $file->getError() === UPLOAD_ERR_INI_SIZE) {
            return response('', 413);
        }
        if (! $file instanceof UploadedFile || ! $file->isValid()) {
            return NewsDto::error($request, 400);
        }

        $created = [];
        $stream = null;
        try {
            $fields = $this->text($request->input('titulo'), $request->input('descripcion', ''));
            [$stream, $extension, $original, $size] = ProviderFile::prepare($file);
            $response = $this->write($request, function () use ($store, $fields, $stream, $extension, $original, $size, &$created): Response {
                if (! rewind($stream)) {
                    throw new RuntimeException;
                }
                $created[] = $name = $store->put($stream, $extension, $size);
                $row = [...$fields, 'nombre_original' => $original, 'nombre_almacenado' => $name,
                    'tipo_mime' => ProviderStore::MIME[$extension], 'tamanio' => $size, 'orden' => count($this->ids()),
                    'activo' => true, 'created_at' => now('UTC'), 'updated_at' => now('UTC')];
                $row['id'] = $this->table()->insertGetId($row);

                return response()->json($this->dto((object) $row), 201);
            });
            if ($response->getStatusCode() === 201) {
                array_pop($created);
            }

            return $response;
        } catch (InvalidArgumentException) {
            return NewsDto::error($request, 400);
        } catch (RuntimeException) {
            Log::warning('Falló el alta de un documento de Proveedores.');

            return NewsDto::error($request, 500);
        } finally {
            if ($stream !== null) {
                fclose($stream);
            }
            foreach ($created as $name) {
                try {
                    if (DB::transactionLevel() === 0 && ! $this->table()->where('nombre_almacenado', $name)->exists()) {
                        $store->discardUpload($name);
                    }
                } catch (Throwable) {
                    Log::warning('No se pudo confirmar el estado de un archivo de Proveedores tras la transacción.');
                }
            }
        }
    }

    public function update(Request $request, string $id): Response
    {
        $number = PublicationInput::id($id);
        $data = $this->json($request);
        $active = $data->activo ?? false;
        if ($number === null || $data === null || ! is_bool($active)) {
            return NewsDto::error($request, 400);
        }
        try {
            $fields = [...$this->text($data->titulo ?? null, $data->descripcion ?? null), 'activo' => $active];
        } catch (InvalidArgumentException) {
            return NewsDto::error($request, 400);
        }

        return $this->write($request, function () use ($request, $number, $fields): Response {
            $query = $this->table()->where('id', $number);
            $row = $query->lockForUpdate()->first();
            if ($row === null) {
                return NewsDto::error($request, 404);
            }
            $query->update([...$fields, 'updated_at' => now('UTC')]);

            return response()->json($this->dto((object) [...(array) $row, ...$fields]));
        });
    }

    public function delete(Request $request, string $id): Response
    {
        $number = PublicationInput::id($id);
        if ($number === null) {
            return NewsDto::error($request, 400);
        }

        return $this->write($request, function () use ($request, $number): Response {
            if ($this->table()->where('id', $number)->delete() === 0) {
                return NewsDto::error($request, 404);
            }
            $this->reorder($this->ids());

            return response()->noContent();
        });
    }

    public function order(Request $request): Response
    {
        $ids = $this->json($request)->ids ?? null;
        if (! is_array($ids) || count($ids) > 100 || array_filter($ids, fn ($id) => ! is_int($id)) !== []) {
            return NewsDto::error($request, 400);
        }

        return $this->write($request, function () use ($request, $ids): Response {
            $current = $this->ids();
            $sorted = $ids;
            sort($current);
            sort($sorted);
            if ($current !== $sorted) {
                return NewsDto::error($request, 400);
            }
            $this->reorder($ids);

            return response()->json($this->documents(false, lock: true));
        });
    }

    private function write(Request $request, Closure $operation): Response
    {
        try {
            return AuthTransactions::run(function () use ($request, $operation): Response {
                $actor = $this->sessions->authenticate($this->sessions->token($request), lockUser: true);
                if ($actor === null || ! $actor->hasAnyRole(self::ROLES)) {
                    return NewsDto::error($request, $actor === null ? 401 : 403);
                }
                if (DB::table('auth_mutexes')->where('name', 'proveedores')->lockForUpdate()->first() === null) {
                    throw new RuntimeException;
                }

                return $operation();
            });
        } catch (RuntimeException) {
            Log::warning('Operación de Proveedores revertida.');

            return NewsDto::error($request, 500);
        }
    }

    private function table(): Builder
    {
        return DB::table('documentos_proveedores');
    }

    private function ordered(): Builder
    {
        return $this->table()->orderBy('orden')->orderBy('id');
    }

    private function ids(): array
    {
        return $this->ordered()->lockForUpdate()->pluck('id')->map(fn ($id): int => (int) $id)->all();
    }

    private function reorder(array $ids): void
    {
        $now = now('UTC');
        foreach ($ids as $position => $id) {
            $this->table()->where('id', $id)->update(['orden' => $position, 'updated_at' => $now]);
        }
    }

    private function documents(bool $public, bool $lock = false): array
    {
        return $this->ordered()->when($public, fn (Builder $query) => $query->where('activo', 1))
            ->when($lock, fn (Builder $query) => $query->lockForUpdate())->get()->map($this->dto(...))->all();
    }

    private function dto(object $row): array
    {
        return ['id' => (int) $row->id, 'titulo' => $row->titulo, 'descripcion' => $row->descripcion,
            'nombreOriginal' => $row->nombre_original, 'url' => '/uploads/proveedores/'.$row->nombre_almacenado,
            'tipoMime' => $row->tipo_mime, 'tamanio' => (int) $row->tamanio, 'orden' => (int) $row->orden,
            'activo' => (int) $row->activo === 1];
    }

    private function text(mixed $title, mixed $description): array
    {
        if (! is_string($title) || strlen(mb_convert_encoding($title, 'UTF-16LE', 'UTF-8')) > 360
            || preg_match('/[^\x09-\x0d\x1c-\x20\x{1680}\x{2000}-\x{2006}\x{2008}-\x{200a}\x{2028}\x{2029}\x{205f}\x{3000}]/u', trim($title, "\x00..\x20")) !== 1) {
            throw new InvalidArgumentException('Ingresá un título válido');
        }
        if (! is_string($description) || ! mb_check_encoding($description, 'UTF-8')
            || strlen(mb_convert_encoding($description, 'UTF-16LE', 'UTF-8')) > 1000) {
            throw new InvalidArgumentException('La descripción supera el máximo permitido');
        }

        return ['titulo' => trim($title, "\x00..\x20"), 'descripcion' => trim($description, "\x00..\x20")];
    }

    private function json(Request $request): ?object
    {
        try {
            $data = json_decode($request->getContent(), false, 512, JSON_THROW_ON_ERROR);

            return is_object($data) ? $data : null;
        } catch (JsonException) {
            return null;
        }
    }
}
