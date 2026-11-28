<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LocalOnlyHttp
{
    public function handle(Request $request, Closure $next): Response
    {
        $origin = parse_url(config('app.url'));
        $expectedHost = $origin['host'].(isset($origin['port']) ? ':'.$origin['port'] : '');
        if (! $request->isSecure() || $request->getHttpHost() !== $expectedHost) {
            return response()->json(['detail' => 'Módulo local: se requiere el origen HTTPS configurado'], 403);
        }

        $response = $next($request);
        $response->headers->set('Cache-Control', 'no-store');
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        return $response;
    }
}
