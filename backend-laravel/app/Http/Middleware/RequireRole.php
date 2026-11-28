<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (! $request->user()) {
            return response()->json(['mensaje' => 'Sesión inválida o vencida'], 401);
        }
        if (! $request->user()->hasAnyRole($roles)) {
            return response()->json(['mensaje' => 'Tu cuenta no tiene permiso para esta acción'], 403);
        }

        return $next($request);
    }
}
