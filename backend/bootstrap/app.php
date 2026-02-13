<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;
use App\Support\ApiResponse;

// ✅ pridané
use App\Http\Middleware\IsAdmin;
use App\Http\Middleware\EnsureUserActive;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // CORS (API + web)
        $middleware->append(\Illuminate\Http\Middleware\HandleCors::class);

        // ✅ DÔLEŽITÉ: Sanctum SPA – session-based auth pre API
        $middleware->api(prepend: [
            EnsureFrontendRequestsAreStateful::class,
        ]);

        // ✅ Admin route middleware alias (Laravel 12 namiesto Kernel.php)
        $middleware->alias([
            'admin' => IsAdmin::class,
            'active' => EnsureUserActive::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\Throwable $throwable, Request $request) {
            if (!$request->expectsJson()) {
                return null;
            }

            if ($throwable instanceof ValidationException) {
                return ApiResponse::error(
                    'The given data was invalid.',
                    $throwable->errors(),
                    $throwable->status
                );
            }

            if ($throwable instanceof AuthenticationException) {
                return ApiResponse::error('Unauthenticated', null, 401);
            }

            if ($throwable instanceof AuthorizationException) {
                return ApiResponse::error($throwable->getMessage() ?: 'Forbidden', null, 403);
            }

            if ($throwable instanceof NotFoundHttpException) {
                return ApiResponse::error('Not found.', null, 404);
            }

            if ($throwable instanceof HttpExceptionInterface) {
                $status = $throwable->getStatusCode();
                $message = $throwable->getMessage() ?: 'HTTP error';

                return ApiResponse::error($message, null, $status);
            }

            $status = 500;
            $message = config('app.debug') ? $throwable->getMessage() : 'Server error.';

            return ApiResponse::error($message, null, $status);
        });
    })
    ->create();
