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
*/

// Home redirect to dashboard
Route::get('/', function () {
    return redirect()->route('tenant.dashboard');
})->name('tenant.home');

// Dashboard
Route::get('/dashboard', function () {
    return Inertia::render('Tenant/Dashboard', [
        'tenant' => tenant()?->only(['id', 'name', 'slug']),
    ]);
})->name('tenant.dashboard');
