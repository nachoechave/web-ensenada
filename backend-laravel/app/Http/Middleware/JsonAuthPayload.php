<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class JsonAuthPayload
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isJson()) {
            return response()->json(['detail' => 'Se requiere Content-Type application/json'], 415);
        }

        return $next($request);
    }
}
