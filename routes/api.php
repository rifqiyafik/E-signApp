<?php

use App\Http\Controllers\Api\CentralAuthController;
use App\Http\Controllers\Api\SuperAdminTenantController;
use App\Http\Controllers\Api\SuperAdminTenantUserController;
use App\Http\Controllers\Api\SuperAdminUserController;
use App\Http\Controllers\Api\TenantRegistrationController;
use App\Http\Controllers\Api\VerifyController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Central API Routes
|--------------------------------------------------------------------------
|
| These routes are for central (non-tenant) API endpoints.
| All routes here are prefixed with /api
|
*/

// Public verify endpoint (no auth required)
Route::post('/verify', [VerifyController::class, 'verify']);

// Public auth routes
Route::post('/auth/login', [CentralAuthController::class, 'login']);

// Protected auth routes (central token required)
Route::middleware(['auth:central_api'])->group(function () {
    Route::get('/auth/me', [CentralAuthController::class, 'me']);
    Route::post('/auth/select-tenant', [CentralAuthController::class, 'selectTenant']);
    Route::post('/auth/logout', [CentralAuthController::class, 'logout']);
});

// Superadmin routes (central token + superadmin required)
Route::middleware(['auth:central_api', \App\Http\Middleware\EnsureSuperAdmin::class])->group(function () {
    // Tenant registration (superadmin only)
    Route::post('/tenants/register', [TenantRegistrationController::class, 'register']);

    // Tenant management
    Route::get('/superadmin/tenants', [SuperAdminTenantController::class, 'index']);
    Route::patch('/superadmin/tenants/{tenant}', [SuperAdminTenantController::class, 'update']);
    Route::delete('/superadmin/tenants/{tenant}', [SuperAdminTenantController::class, 'destroy']);

    // User management for specific tenants
    Route::post('/superadmin/tenants/{tenant}/users', [SuperAdminTenantUserController::class, 'store']);
    Route::post('/superadmin/tenants/{tenant}/users/assign', [SuperAdminTenantUserController::class, 'assign']);

    // Global user management
    Route::get('/superadmin/users', [SuperAdminUserController::class, 'index']);
    Route::get('/superadmin/users/{user}', [SuperAdminUserController::class, 'show']);
    Route::patch('/superadmin/users/{user}', [SuperAdminUserController::class, 'update']);
    Route::delete('/superadmin/users/{user}', [SuperAdminUserController::class, 'destroy']);
});

