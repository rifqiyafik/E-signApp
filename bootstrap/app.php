<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Handle API exceptions with consistent JSON responses
        $exceptions->render(function (Throwable $e, Request $request) {
            // Only handle API requests
            if (!$request->is('api/*') && !$request->is('*/api/*')) {
                return null;
            }

            // Authentication errors
            if ($e instanceof AuthenticationException) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated. Please login to access this resource.',
                    'code' => 'unauthenticated',
                    'status' => 401,
                ], 401);
            }

            // Validation errors
            if ($e instanceof ValidationException) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed. Please check your input.',
                    'code' => 'validation_error',
                    'status' => 422,
                    'errors' => $e->errors(),
                ], 422);
            }

            // Not found errors
            if ($e instanceof NotFoundHttpException) {
                return response()->json([
                    'success' => false,
                    'message' => 'Resource not found.',
                    'code' => 'not_found',
                    'status' => 404,
                ], 404);
            }

            // Method not allowed
            if ($e instanceof MethodNotAllowedHttpException) {
                return response()->json([
                    'success' => false,
                    'message' => 'HTTP method not allowed for this endpoint.',
                    'code' => 'method_not_allowed',
                    'status' => 405,
                ], 405);
            }

            // Access denied / Forbidden
            if ($e instanceof AccessDeniedHttpException) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. You do not have permission to access this resource.',
                    'code' => 'forbidden',
                    'status' => 403,
                ], 403);
            }

            // Generic HTTP exceptions
            if ($e instanceof HttpException) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage() ?: 'An error occurred.',
                    'code' => 'http_error',
                    'status' => $e->getStatusCode(),
                ], $e->getStatusCode());
            }

            // Model not found (when using findOrFail, firstOrFail)
            if ($e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
                return response()->json([
                    'success' => false,
                    'message' => 'The requested resource was not found.',
                    'code' => 'model_not_found',
                    'status' => 404,
                ], 404);
            }

            // Query exception (database errors)
            if ($e instanceof \Illuminate\Database\QueryException) {
                // Don't expose SQL errors in production
                $message = app()->environment('production')
                    ? 'A database error occurred.'
                    : $e->getMessage();

                return response()->json([
                    'success' => false,
                    'message' => $message,
                    'code' => 'database_error',
                    'status' => 500,
                ], 500);
            }

            // Token mismatch (CSRF)
            if ($e instanceof \Illuminate\Session\TokenMismatchException) {
                return response()->json([
                    'success' => false,
                    'message' => 'Session expired. Please refresh and try again.',
                    'code' => 'token_mismatch',
                    'status' => 419,
                ], 419);
            }

            // Throttle / Rate limit
            if ($e instanceof \Illuminate\Http\Exceptions\ThrottleRequestsException) {
                return response()->json([
                    'success' => false,
                    'message' => 'Too many requests. Please slow down.',
                    'code' => 'rate_limit_exceeded',
                    'status' => 429,
                    'retry_after' => $e->getHeaders()['Retry-After'] ?? 60,
                ], 429);
            }

            // All other exceptions (catch-all)
            $statusCode = 500;
            $message = app()->environment('production')
                ? 'An unexpected error occurred. Please try again later.'
                : $e->getMessage();

            return response()->json([
                'success' => false,
                'message' => $message,
                'code' => 'server_error',
                'status' => $statusCode,
                'exception' => app()->environment('production') ? null : get_class($e),
                'trace' => app()->environment('production') ? null : collect($e->getTrace())->take(5)->toArray(),
            ], $statusCode);
        });
    })->create();

