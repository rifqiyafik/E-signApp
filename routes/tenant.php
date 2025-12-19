<?php

declare(strict_types=1);

use App\Http\Middleware\InitializeTenancyByPathOrId;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Tenant Routes
|--------------------------------------------------------------------------
|
| Here you can register the tenant routes for your application.
| These routes are loaded by the TenancyServiceProvider.
|
*/

// Web routes for tenant
Route::middleware([
    'web',
    InitializeTenancyByPathOrId::class,
])->prefix('{tenant}')->group(base_path('routes/web-tenant.php'));

// API routes for tenant
Route::middleware([
    'api',
    InitializeTenancyByPathOrId::class,
])->prefix('{tenant}/api')->group(base_path('routes/api-tenant.php'));
