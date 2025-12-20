<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\VerifyController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Tenant API Routes
|--------------------------------------------------------------------------
|
| Here you can register API routes for your tenant application.
| All routes here are prefixed with /{tenant}/api
|
| Example:
| Route::get('/users', [UserController::class, 'index']);
| -> Accessible at: /{tenant}/api/users
|
*/

// Public routes (no auth required)
Route::prefix('public')->group(function () {
    // Add public tenant routes here
    Route::get('/info', function () {
        return response()->json([
            'tenant' => tenant()?->only(['id', 'name', 'slug']),
        ]);
    });
});

// Auth (login is public)
Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);

// Verify endpoints (public)
Route::post('/verify', [VerifyController::class, 'verify']);
Route::get('/verify/{chainId}/v{version}', [VerifyController::class, 'show'])
    ->whereNumber('version');

// Protected routes (auth required)
Route::middleware(['auth:api'])->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me']);

    // Documents
    Route::post('/documents/sign', [DocumentController::class, 'sign']);
    Route::get('/documents/{document}', [DocumentController::class, 'show']);
    Route::get('/documents/{document}/versions', [DocumentController::class, 'versions']);
    Route::get('/documents/{document}/versions/latest:download', [DocumentController::class, 'downloadLatest']);
    Route::get('/documents/{document}/versions/v{version}:download', [DocumentController::class, 'downloadVersion'])
        ->whereNumber('version');

    // Add more authenticated routes here
    // Route::apiResource('users', UserController::class);
});
