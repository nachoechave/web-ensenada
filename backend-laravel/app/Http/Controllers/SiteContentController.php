<?php

namespace App\Http\Controllers;

use App\Auth\AuthTransactions;
use App\Auth\JwtSessions;
use App\Content\SiteContentDocument;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

final class SiteContentController extends Controller
{
    public function show(Request $request): Response
    {
        $json = DB::table('portal_site_content')->where('id', 1)->value('contenido_json');
        if ($json === null) {
            return $this->error($request, 404);
        }
        if (! SiteContentDocument::valid($json)) {
            Log::warning('El contenido institucional almacenado no supera la validación.');

            return $this->error($request, 500);
        }

        return $this->document($json);
    }

    public function update(Request $request, JwtSessions $sessions): Response
    {
        $type = trim(explode(';', $request->header('Content-Type', ''), 2)[0]);
        if (strcasecmp($type, 'application/json') !== 0) {
            return $this->error($request, 415);
        }
        // String.trim() in the Java API strips characters at or below U+0020.
        $json = trim($request->getContent(), "\x00..\x20");
        if (strlen($json) > SiteContentDocument::MAX_BYTES) {
            return $this->error($request, 413);
        }
        if (! SiteContentDocument::valid($json)) {
            return $this->error($request, 400);
        }

        try {
            return AuthTransactions::run(function () use ($request, $sessions, $json): Response {
                $actor = $sessions->authenticate($sessions->token($request), lockUser: true);
                if ($actor === null || ! $actor->hasAnyRole(['SUPER_ADMIN'])) {
                    return $this->error($request, $actor === null ? 401 : 403);
                }
                $singleton = DB::table('portal_site_content')->where('id', 1);
                if ($singleton->lockForUpdate()->first(['id']) === null) {
                    return $this->error($request, 404);
                }
                $singleton->update(['contenido_json' => $json, 'updated_at' => now('UTC')->format('Y-m-d H:i:s')]);

                return $this->document($json);
            });
        } catch (QueryException $error) {
            Log::warning('Operación de contenido institucional revertida.');

            return $this->error($request, in_array($error->errorInfo[1] ?? null, [5, 6, 1205, 1213], true) ? 503 : 500);
        }
    }

    private function document(string $json): Response
    {
        return response($json, 200, ['Content-Type' => 'application/json']);
    }

    private function error(Request $request, int $status): Response
    {
        return response()->json([
            'timestamp' => now('UTC')->format('Y-m-d\TH:i:s.v\Z'),
            'status' => $status,
            'error' => Response::$statusTexts[$status],
            'path' => $request->getPathInfo(),
        ], $status);
    }
}
