<?php

namespace App\Http\Controllers;

use App\Auth\AuthTransactions;
use App\Auth\JwtSessions;
use App\Models\Usuario;
use App\News\EditorialInput;
use App\News\NewsDto;
use Closure;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Normalizer;
use Symfony\Component\HttpFoundation\Response;

class NoticiasAdminController extends Controller
{
    private const ROLES = ['PRENSA', 'SUPER_ADMIN'];

    public function __construct(private JwtSessions $sessions) {}

    public function index(): JsonResponse
    {
        [$db, $prefix] = $this->editorialConnection();

        return $db->transaction(fn () => response()->json(NewsDto::list($db,
            $db->table($prefix.'noticias')->orderByDesc('id')->get(NewsDto::COLUMNS), $prefix.'noticia_imagenes')));
    }

    public function audit(): JsonResponse
    {
        $rows = DB::table('auditoria_noticias')->orderByDesc('fecha')->orderByDesc('id')->limit(100)->get();

        return response()->json($rows->map(fn (object $row): array => [
            'id' => (int) $row->id,
            'noticiaId' => (int) $row->noticia_id,
            'noticiaTitulo' => $row->noticia_titulo,
            'accion' => $row->accion,
            'estadoResultante' => $row->estado_resultante,
            'usuarioNombre' => $row->usuario_nombre,
            'usuarioEmail' => $row->usuario_email,
            'fecha' => $this->instant($row->fecha),
        ]));
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $id = $this->id($id);
        if ($id === null) {
            return NewsDto::error($request, 400);
        }
        [$db, $prefix] = $this->editorialConnection();

        return $db->transaction(function () use ($db, $prefix, $request, $id): JsonResponse {
            $row = $db->table($prefix.'noticias')->where('id', $id)->first(NewsDto::COLUMNS);

            return $row === null ? NewsDto::error($request, 404)
                : response()->json(NewsDto::list($db, collect([$row]), $prefix.'noticia_imagenes')[0]);
        });
    }

    public function store(Request $request): Response
    {
        $data = EditorialInput::parse($request->getContent());
        if ($data === null) {
            return NewsDto::error($request, 400);
        }

        return $this->write($request, function (Connection $db, string $prefix, Usuario $actor) use ($data): Response {
            if (! $db->table('auth_mutexes')->where('name', 'news-slug')->lockForUpdate()->first()) {
                return response()->json(['detail' => 'El módulo editorial no está preparado.'], 503);
            }
            $slug = $this->slug($db, $prefix.'noticias', $data['fields']['titulo']);
            $row = [...$data['fields'], 'slug' => $slug];
            $id = (int) $db->table($prefix.'noticias')->insertGetId($row);
            $this->gallery($db, $prefix, $id, $data['images']);
            $this->record($db, $id, $row['titulo'], 'CREACION', $row['estado'], $actor);

            return response()->json(NewsDto::list($db, collect([(object) ['id' => $id, ...$row]]), $prefix.'noticia_imagenes')[0], 201);
        }, retrySlug: true);
    }

    public function update(Request $request, string $id): Response
    {
        $id = $this->id($id);
        $data = EditorialInput::parse($request->getContent());
        if ($id === null || $data === null) {
            return NewsDto::error($request, 400);
        }

        return $this->write($request, function (Connection $db, string $prefix, Usuario $actor) use ($id, $data, $request): Response {
            $query = $db->table($prefix.'noticias')->where('id', $id);
            $previous = $query->lockForUpdate()->first(['slug', 'estado']);
            if ($previous === null) {
                return NewsDto::error($request, 404);
            }
            $row = $data['fields'];
            $query->update($row);
            $this->gallery($db, $prefix, $id, $data['images']);
            $action = $previous->estado === $row['estado'] ? 'EDICION' : ($row['estado'] === 'PUBLICADA' ? 'PUBLICACION' : 'DESPUBLICACION');
            $this->record($db, $id, $row['titulo'], $action, $row['estado'], $actor);

            return response()->json(NewsDto::list($db, collect([(object) ['id' => $id, 'slug' => $previous->slug, ...$row]]), $prefix.'noticia_imagenes')[0]);
        });
    }

    public function archive(Request $request, string $id): Response
    {
        $id = $this->id($id);
        if ($id === null) {
            return NewsDto::error($request, 400);
        }

        return $this->write($request, function (Connection $db, string $prefix, Usuario $actor) use ($id, $request): Response {
            $query = $db->table($prefix.'noticias')->where('id', $id);
            $previous = $query->lockForUpdate()->first(['titulo']);
            if ($previous === null) {
                return NewsDto::error($request, 404);
            }
            $query->update(['estado' => 'ARCHIVADA']);
            $this->record($db, $id, $previous->titulo, 'ARCHIVADO', 'ARCHIVADA', $actor);

            return response()->noContent();
        });
    }

    private function editorialConnection(): array
    {
        $db = DB::connection();
        if ($db->getDriverName() !== 'sqlite') {
            return [$db, ''];
        }

        $path = '/tmp/ensenada-news.sqlite';
        if (! is_file($path)) {
            abort(503, 'El módulo editorial no está preparado.');
        }
        if (! collect($db->select('PRAGMA database_list'))->contains('name', 'editorial')) {
            $db->statement('ATTACH DATABASE ? AS editorial', [$path]);
        }

        return [$db, 'editorial.'];
    }

    private function write(Request $request, Closure $operation, bool $retrySlug = false): Response
    {
        [$db, $prefix] = $this->editorialConnection();
        for ($attempt = 0; $attempt < 4; $attempt++) {
            try {
                return AuthTransactions::run(function () use ($request, $operation, $db, $prefix): Response {
                    $actor = $this->sessions->authenticate($this->sessions->token($request), lockUser: true);
                    if ($actor === null || ! $actor->hasAnyRole(self::ROLES)) {
                        return NewsDto::error($request, $actor === null ? 401 : 403);
                    }

                    return $operation($db, $prefix, $actor);
                });
            } catch (QueryException $exception) {
                if ($retrySlug && $exception instanceof UniqueConstraintViolationException
                    && preg_match('/^insert into (?:["`]?editorial["`]?\.)?["`]?noticias["`]?\s/i', $exception->getSql())) {
                    continue;
                }
                // QueryException may contain request values and the actor's identity in its SQL bindings.
                Log::warning('Operación editorial revertida.');

                return NewsDto::error($request, in_array($exception->errorInfo[1] ?? null, [5, 6, 1205, 1213], true) ? 503 : 500);
            }
        }

        return NewsDto::error($request, 409);
    }

    private function slug(Connection $db, string $table, string $title): string
    {
        $normalized = preg_replace('/\p{M}+/u', '', Normalizer::normalize($title, Normalizer::FORM_D));
        $base = trim(preg_replace('/[^a-z0-9]+/', '-', mb_strtolower($normalized)), '-');
        if ($base === '' || ctype_digit($base)) {
            $base = 'noticia-'.$base;
        }
        if (strlen($base) > 250) {
            $base = rtrim(substr($base, 0, 250), '-');
        }
        $candidate = $base;
        for ($suffix = 2; $db->table($table)->where('slug', $candidate)->exists(); $suffix++) {
            $candidate = $base.'-'.$suffix;
        }

        return $candidate;
    }

    private function gallery(Connection $db, string $prefix, int $id, array $images): void
    {
        $gallery = $db->table($prefix.'noticia_imagenes');
        $gallery->where('noticia_id', $id)->delete();
        $db->table($prefix.'noticia_imagenes')->insert(array_map(fn (int $order, string $url): array => ['noticia_id' => $id, 'orden' => $order, 'url' => $url], array_keys($images), $images));
    }

    private function record(Connection $db, int $id, string $title, string $action, string $state, Usuario $actor): void
    {
        $db->table('auditoria_noticias')->insert([
            'noticia_id' => $id, 'noticia_titulo' => $title, 'accion' => $action, 'estado_resultante' => $state,
            'usuario_nombre' => $actor->nombre, 'usuario_email' => $actor->email,
            'fecha' => now('UTC')->format('Y-m-d H:i:s.u'),
        ]);
    }

    private function id(string $value): ?int
    {
        if (! preg_match('/^[+-]?[0-9]+$/D', $value)) {
            return null;
        }
        $number = filter_var(($value[0] === '-' ? '-' : '').(ltrim($value, '+-0') ?: '0'), FILTER_VALIDATE_INT);

        return $number === false ? null : $number;
    }

    private function instant(string $value): string
    {
        $date = new DateTimeImmutable($value, new DateTimeZone('UTC'));
        $micros = $date->format('u');
        $fraction = $micros === '000000' ? '' : '.'.(str_ends_with($micros, '000') ? substr($micros, 0, 3) : $micros);

        return $date->format('Y-m-d\TH:i:s').$fraction.'Z';
    }
}
