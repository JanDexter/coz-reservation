<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            // Load all module routes automatically
            $modulesPath = base_path('Modules');
            if (is_dir($modulesPath)) {
                $modules = array_filter(glob($modulesPath . '/*'), 'is_dir');
                foreach ($modules as $modulePath) {
                    $webRouteFile = $modulePath . '/routes/web.php';
                    if (file_exists($webRouteFile)) {
                        \Illuminate\Support\Facades\Route::middleware('web')
                            ->group($webRouteFile);
                    }
                    
                    $apiRouteFile = $modulePath . '/routes/api.php';
                    if (file_exists($apiRouteFile)) {
                        \Illuminate\Support\Facades\Route::middleware('api')
                            ->prefix('api')
                            ->group($apiRouteFile);
                    }
                }
            }
        }
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
            \App\Http\Middleware\SecurityHeaders::class,
            \App\Http\Middleware\EnsureAdminExists::class,
        ]);

        $middleware->alias([
            'verified' => \App\Http\Middleware\EnsureEmailIsVerified::class,
        ]);

        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->respond(function (\Symfony\Component\HttpFoundation\Response $response, \Throwable $exception, \Illuminate\Http\Request $request) {
            $statusCode = $response->getStatusCode();

            // Handle 404 errors
            if ($statusCode === 404) {
                // If it's an Inertia request, return Inertia response
                if ($request->header('X-Inertia')) {
                    return inertia('Errors/404', [
                        'status' => 404
                    ])
                    ->toResponse($request)
                    ->setStatusCode(404);
                }
                
                // For regular requests, return blade view
                return response()->view('errors.404', ['exception' => $exception], 404);
            }

            // Handle other error codes (500, 503, 403)
            if (in_array($statusCode, [500, 503, 403])) {
                // If it's an Inertia request, return Inertia response
                if ($request->header('X-Inertia')) {
                    return inertia('Errors/Error', [
                        'status' => $statusCode
                    ])
                    ->toResponse($request)
                    ->setStatusCode($statusCode);
                }
                
                // For regular requests, return blade view
                $viewName = view()->exists("errors.{$statusCode}") ? "errors.{$statusCode}" : 'errors.500';
                return response()->view($viewName, ['exception' => $exception], $statusCode);
            }

            return $response;
        });
    })->create();
