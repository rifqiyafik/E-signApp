<?php

use App\Http\Controllers\Api\CentralAuthController;
use App\Http\Controllers\Api\SuperAdminTenantController;
use App\Http\Controllers\Api\SuperAdminTenantUserController;
use App\Http\Controllers\Api\TenantRegistrationController;
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

    // User management for tenants
    Route::post('/superadmin/tenants/{tenant}/users', [SuperAdminTenantUserController::class, 'store']);
    Route::post('/superadmin/tenants/{tenant}/users/assign', [SuperAdminTenantUserController::class, 'assign']);
});
