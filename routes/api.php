<?php

use App\Http\Controllers\Api\TenantRegistrationController;
use Illuminate\Support\Facades\Route;

Route::post('/tenants/register', [TenantRegistrationController::class, 'register']);
