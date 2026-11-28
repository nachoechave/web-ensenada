<?php

namespace App\Http\Middleware;

use App\Auth\JwtSessions;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateSession
{
    public function __construct(private JwtSessions $sessions) {}

    public function handle(Request $request, Closure $next): Response
    {
        $usuario = $this->sessions->authenticate($this->sessions->token($request));
        if (! $usuario) {
            return response()->json(['mensaje' => 'Sesión inválida o vencida'], 401)
                ->withCookie($this->sessions->cookie());
        }

        $request->setUserResolver(fn () => $usuario);

        return $next($request);
    }
}
