<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            Route::middleware(['web', 'auth'])
                ->prefix('admin')
                ->name('admin.')
                ->group(base_path('routes/admin.php'));

            Route::middleware(['web', 'auth', 'superadmin'])
                ->prefix('superadmin')
                ->name('superadmin.')
                ->group(base_path('routes/superadmin.php'));
        }
    )
  ->withMiddleware(function (Middleware $middleware): void {
    $middleware->trustProxies(at: '*');
    $middleware->append(\App\Http\Middleware\SecurityHeaders::class);
    $middleware->alias([
        'superadmin' => \App\Http\Middleware\SuperAdminMiddleware::class,
    ]);
})
    ->withExceptions(function (Exceptions $exceptions): void {
        // 419 Page Expired (token CSRF vencido/no coincide): en vez de la
        // pantalla de error, regresamos al login (o a la página anterior)
        // con un aviso claro para que la persona vuelva a intentar.
        $exceptions->render(function (\Illuminate\Session\TokenMismatchException $e, \Illuminate\Http\Request $request) {
            if ($request->is('login')) {
                return redirect()->route('login')
                    ->with('status', 'Tu sesión expiró por inactividad. Intenta iniciar sesión de nuevo.');
            }

            return redirect()->back()
                ->with('swal', [
                    'icon'  => 'info',
                    'title' => 'Tu sesión expiró',
                    'text'  => 'Por seguridad tuvimos que cerrarla por inactividad. Intenta de nuevo.',
                ]);
        });

        // Avisar por WhatsApp (Evolution API) cuando truena un error real de
        // servidor (500) — el número se configura en Superadmin →
        // Configuración → WhatsApp. Solo dispara para errores que Laravel
        // SÍ reporta (esto ya excluye 404, 403, validaciones, CSRF, etc. —
        // ver $internalDontReport del propio framework), así que aquí
        // llegan justo los que antes solo se veían tarde, revisando el log
        // a mano. Con throttle para no inundar el teléfono si un mismo
        // error se repite en ráfaga (ej. varios usuarios pegándole a la
        // misma ruta rota al mismo tiempo).
        $exceptions->reportable(function (\Throwable $e) {
            try {
                app(\App\Services\ErrorAlertService::class)->notify($e);
            } catch (\Throwable $ignored) {
                // El aviso nunca debe tumbar el manejo normal del error.
            }
        });
    })->create();
