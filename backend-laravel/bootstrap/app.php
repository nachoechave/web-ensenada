<?php

use App\Content\SiteImageStore;
use App\Hacienda\DocumentStore;
use App\Http\Controllers\HaciendaDownloadsController;
use App\Http\Controllers\NewsImageController;
use App\Http\Controllers\ProveedoresController;
use App\Http\Controllers\SiteImageController;
use App\Http\Middleware\AuthenticateSession;
use App\Http\Middleware\LocalOnlyHttp;
use App\Http\Middleware\RequireRole;
use App\Http\Middleware\StrictRequestForgery;
use App\Http\Middleware\TrustLocalProxies;
use App\News\NewsImageStore;
use App\Proveedores\ProviderStore;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull;
use Illuminate\Foundation\Http\Middleware\TrimStrings;
use Illuminate\Http\Exceptions\OriginMismatchException;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(api: __DIR__.'/../routes/api.php', then: function (): void {
        Route::get('uploads/noticias/{filename}', [NewsImageController::class, 'show'])
            ->where('filename', NewsImageStore::FILENAME)->middleware(LocalOnlyHttp::class);
        Route::get('uploads/sitio/{filename}', [SiteImageController::class, 'show'])
            ->where('filename', SiteImageStore::FILENAME)->middleware(LocalOnlyHttp::class);
        Route::get('uploads/hacienda/{nombre}', [HaciendaDownloadsController::class, 'show'])
            ->where('nombre', DocumentStore::FILENAME)->middleware(LocalOnlyHttp::class);
        Route::get('uploads/proveedores/{nombre}', [ProveedoresController::class, 'download'])
            ->where('nombre', ProviderStore::FILENAME)->middleware(LocalOnlyHttp::class);
    })
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->replace(TrustProxies::class, TrustLocalProxies::class);
        $middleware->remove([TrimStrings::class, ConvertEmptyStringsToNull::class]);
        $middleware->api(prepend: [LocalOnlyHttp::class, StrictRequestForgery::class]);
        $middleware->alias(['auth.session' => AuthenticateSession::class, 'role' => RequireRole::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(fn (Request $request): bool => true);
        $exceptions->render(fn (OriginMismatchException $exception) => response()->json(['detail' => 'Origen de solicitud no permitido'], 403));
        $exceptions->render(function (PostTooLargeException $exception, Request $request) {
            return $request->isMethod('POST') && $request->is('api/admin/archivos/noticias', 'api/admin/archivos/sitio', 'api/admin/hacienda/*/archivos', 'api/admin/proveedores/documentos')
                ? response('', 413) : null;
        });
        $exceptions->render(fn (ValidationException $exception) => response()->json([
            'detail' => collect($exception->errors())->flatten()->first(),
            'errors' => $exception->errors(),
        ], 400));
        $exceptions->respond(function (Response $response): Response {
            $response->headers->set('Cache-Control', 'no-store');
            $response->headers->set('X-Content-Type-Options', 'nosniff');

            return $response;
        });
    })->create();
