<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Tenant Web Routes
|--------------------------------------------------------------------------
|
| Here you can register web routes for your tenant application.
| All routes here are prefixed with /{tenant}
|
| Example:
| Route::get('/dashboard', [DashboardController::class, 'index']);
| -> Accessible at: /{tenant}/dashboard
|
*/

Route::get('/', function () {
    return Inertia::render('Tenant/Welcome', [
        'tenant' => tenant()?->only(['id', 'name', 'slug']),
    ]);
})->name('tenant.home');

// Add more tenant web routes here
