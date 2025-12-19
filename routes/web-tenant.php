<?php

use Illuminate\Support\Facades\Route;

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
    return view('tenant.welcome', [
        'tenant' => tenant(),
    ]);
})->name('tenant.home');

// Add more tenant web routes here
