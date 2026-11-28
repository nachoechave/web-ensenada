<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\HaciendaController;
use App\Http\Controllers\HaciendaDownloadsController;
use App\Http\Controllers\NewsImageController;
use App\Http\Controllers\NoticiasAdminController;
use App\Http\Controllers\NoticiasPublicasController;
use App\Http\Controllers\ProveedoresController;
use App\Http\Controllers\SiteContentController;
use App\Http\Controllers\SiteImageController;
use App\Http\Controllers\UsuariosAdminController;
use App\Http\Middleware\JsonAuthPayload;
use App\Http\Middleware\LoginRateLimit;
use Illuminate\Support\Facades\Route;

Route::post('admin/archivos/noticias', [NewsImageController::class, 'store'])->middleware(['auth.session', 'role:PRENSA,SUPER_ADMIN']);
Route::post('admin/archivos/sitio', [SiteImageController::class, 'store'])->middleware(['auth.session', 'role:SUPER_ADMIN']);

Route::get('site-content', [SiteContentController::class, 'show']);
Route::prefix('admin/site-content')->middleware(['auth.session', 'role:SUPER_ADMIN'])->group(function (): void {
    Route::get('/', [SiteContentController::class, 'show']);
    Route::put('/', [SiteContentController::class, 'update']);
});

Route::get('hacienda', [HaciendaController::class, 'index']);
Route::get('proveedores/documentos', [ProveedoresController::class, 'index']);
Route::prefix('admin/proveedores/documentos')->middleware(['auth.session', 'role:HACIENDA,SUPER_ADMIN'])->group(function (): void {
    Route::get('/', [ProveedoresController::class, 'indexAdmin']);
    Route::post('/', [ProveedoresController::class, 'store']);
    Route::put('orden', [ProveedoresController::class, 'order'])->middleware(JsonAuthPayload::class);
    Route::put('{id}', [ProveedoresController::class, 'update'])->middleware(JsonAuthPayload::class);
    Route::delete('{id}', [ProveedoresController::class, 'delete']);
});
Route::get('hacienda/{id}', [HaciendaController::class, 'show']);
Route::prefix('admin/hacienda')->middleware(['auth.session', 'role:HACIENDA,SUPER_ADMIN'])->group(function (): void {
    Route::get('/', [HaciendaController::class, 'indexAdmin']);
    Route::get('{id}', [HaciendaController::class, 'showAdmin']);
    Route::post('/', [HaciendaController::class, 'store'])->middleware(JsonAuthPayload::class);
    Route::put('{id}', [HaciendaController::class, 'update'])->middleware(JsonAuthPayload::class);
    Route::delete('{id}', [HaciendaController::class, 'archive']);
    Route::post('{id}/archivos', [HaciendaController::class, 'attach']);
    Route::put('{id}/archivos/orden', [HaciendaController::class, 'order'])->middleware(JsonAuthPayload::class);
    Route::delete('{id}/archivos/{archivoId}', [HaciendaController::class, 'detach']);
    Route::get('{id}/archivos/{archivoId}/contenido', [HaciendaDownloadsController::class, 'showAdmin']);
});

Route::prefix('noticias')->group(function (): void {
    Route::get('/', [NoticiasPublicasController::class, 'index']);
    Route::get('destacadas', [NoticiasPublicasController::class, 'featured']);
    Route::get('{identifier}', [NoticiasPublicasController::class, 'show']);
});

Route::prefix('auth')->group(function (): void {
    Route::post('login', [AuthController::class, 'login'])->middleware([LoginRateLimit::class, JsonAuthPayload::class])->name('auth.login');
    Route::post('logout', [AuthController::class, 'logout'])->name('auth.logout');
    Route::middleware('auth.session')->group(function (): void {
        Route::get('me', [AuthController::class, 'me'])->name('auth.me');
        Route::put('me', [AuthController::class, 'updateProfile'])->middleware(JsonAuthPayload::class)->name('auth.profile');
        Route::put('me/password', [AuthController::class, 'changePassword'])->middleware(JsonAuthPayload::class)->name('auth.password');
    });
});

Route::prefix('admin/usuarios')->middleware(['auth.session', 'role:SUPER_ADMIN'])->group(function (): void {
    Route::get('/', [UsuariosAdminController::class, 'index'])->name('admin.usuarios.index');
    Route::middleware(JsonAuthPayload::class)->group(function (): void {
        Route::post('/', [UsuariosAdminController::class, 'store'])->name('admin.usuarios.store');
        Route::put('{id}/roles', [UsuariosAdminController::class, 'roles'])->name('admin.usuarios.roles');
        Route::put('{id}/activo', [UsuariosAdminController::class, 'active'])->name('admin.usuarios.active');
        Route::put('{id}/password', [UsuariosAdminController::class, 'password'])->name('admin.usuarios.password');
    });
});

Route::prefix('admin/noticias')->middleware(['auth.session', 'role:PRENSA,SUPER_ADMIN'])->group(function (): void {
    Route::get('/', [NoticiasAdminController::class, 'index']);
    Route::get('auditoria', [NoticiasAdminController::class, 'audit']);
    Route::get('{id}', [NoticiasAdminController::class, 'show']);
    Route::post('/', [NoticiasAdminController::class, 'store'])->middleware(JsonAuthPayload::class);
    Route::put('{id}', [NoticiasAdminController::class, 'update'])->middleware(JsonAuthPayload::class);
    Route::delete('{id}', [NoticiasAdminController::class, 'archive']);
});
