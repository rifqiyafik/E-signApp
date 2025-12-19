<?php

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

// Protected routes (auth required)
Route::middleware(['auth:api'])->group(function () {
    // Profile
    Route::get('/profile', function () {
        return response()->json([
            'user' => auth()->user(),
            'tenant' => tenant(),
        ]);
    });

    // Add more authenticated routes here
    // Route::apiResource('users', UserController::class);
});
