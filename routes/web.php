<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
});

Route::get('/login', function () {
    return Inertia::render('Login');
});

Route::get('/select-tenant', function () {
    return Inertia::render('SelectTenant');
});

Route::get('/superadmin/dashboard', function () {
    return Inertia::render('Admin/Dashboard');
});

Route::get('/admin/dashboard', function () {
    return Inertia::render('Tenant/Dashboard');
});

Route::get('/admin', function () {
    return redirect('/superadmin/dashboard');
});

