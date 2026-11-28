<?php

namespace App\Http\Controllers;

use App\News\NewsDto;
use Closure;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NoticiasPublicasController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $page = $this->integerParameter($request, 'page', 0);
        $size = $this->integerParameter($request, 'size', 12);
        if ($page === null || $size === null) {
            return NewsDto::error($request, 400);
        }

        $page = max(0, $page);
        $size = min(48, max(1, $size));
        $offset = $page * $size;
        if ($offset > 2147483647) {
            return NewsDto::error($request, 400);
        }

        return $this->readSnapshot(function (Connection $db) use ($page, $size, $offset): JsonResponse {
            $query = $this->published($db);
            $total = $query->count();
            $pages = (int) ceil($total / $size);
            $rows = $offset < $total ? $query->orderByDesc('id')->offset($offset)->limit($size)->get(NewsDto::COLUMNS) : collect();

            return response()->json([
                'content' => NewsDto::list($db, $rows),
                'page' => $page,
                'size' => $size,
                'totalElements' => $total,
                'totalPages' => $pages,
                'first' => $page === 0,
                // Spring computes hasNext using signed 32-bit page + 1.
                'last' => $page < 2147483647 && $page + 1 >= $pages,
            ]);
        });
    }

    public function featured(): JsonResponse
    {
        return $this->readSnapshot(fn (Connection $db) => response()->json(NewsDto::list($db,
            $this->published($db)->where('destacada', true)->orderByDesc('id')->get(NewsDto::COLUMNS))));
    }

    public function show(Request $request, string $identifier): JsonResponse
    {
        $column = 'slug';
        if (ctype_digit($identifier)) {
            $column = 'id';
            $identifier = filter_var(ltrim($identifier, '0') ?: '0', FILTER_VALIDATE_INT);
        } elseif (! mb_check_encoding($identifier, 'UTF-8') || mb_strlen($identifier) > 280 || preg_match('/[\x00-\x1f\x7f]/', $identifier)) {
            $identifier = false;
        }
        if ($identifier === false) {
            return NewsDto::error($request, 404);
        }

        return $this->readSnapshot(function (Connection $db) use ($request, $column, $identifier): JsonResponse {
            $row = $this->published($db)->where($column, $identifier)->first(NewsDto::COLUMNS);

            return $row === null
                ? NewsDto::error($request, 404)
                : response()->json(NewsDto::list($db, collect([$row]))[0]);
        });
    }

    private function readSnapshot(Closure $read): JsonResponse
    {
        $db = DB::getDefaultConnection() === 'sqlite' ? DB::connection('news') : DB::connection();
        if ($db->getDriverName() === 'mysql') {
            // Applies only to the next transaction on the principal connection.
            $db->statement('SET TRANSACTION READ ONLY');
        }

        return $db->transaction($read);
    }

    private function published(Connection $db): Builder
    {
        $query = $db->table('noticias')->where('estado', 'PUBLICADA');
        if ($db->getDriverName() === 'mysql') {
            $query->whereRaw('CAST(estado AS BINARY) = ?', ['PUBLICADA']);
        }

        return $query;
    }

    private function integerParameter(Request $request, string $name, int $default): ?int
    {
        $values = [];
        foreach (explode('&', $request->server('QUERY_STRING', '')) as $pair) {
            [$key, $value] = array_pad(explode('=', $pair, 2), 2, '');
            $key = urldecode($key);
            if ($key === $name || $key === $name.'[]') {
                $values[$key][] = urldecode($value);
            }
        }
        $parameters = $values[$name] ?? $values[$name.'[]'] ?? [''];
        $value = $parameters[0];
        if ($value === '') {
            return count($parameters) === 1 ? $default : null;
        }
        $value = preg_replace('/[\x09-\x0d\x1c-\x20]+/', '', $value);
        if (preg_match('/^(-?)(?:0[xX]|#)([a-fA-F0-9]+)$/D', $value, $hex)) {
            $number = hexdec($hex[2]) * ($hex[1] === '-' ? -1 : 1);
        } elseif (preg_match('/^[+-]?[0-9]+$/D', $value)) {
            $number = (int) $value;
        } else {
            return null;
        }

        return $number >= -2147483648 && $number <= 2147483647 ? (int) $number : null;
    }
}
