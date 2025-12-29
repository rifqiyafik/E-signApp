<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\DocumentWorkflowController;
use App\Http\Controllers\Api\PkiController;
use App\Http\Controllers\Api\TenantAdminUserController;
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
    Route::get('/info', function () {
        return response()->json([
            'tenant' => tenant()?->only(['id', 'name', 'slug']),
        ]);
    });
});

// Auth (login/register is public)
Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);

// Verify endpoints (public)
Route::post('/verify', [VerifyController::class, 'verify']);
Route::get('/verify/{chainId}/v{version}', [VerifyController::class, 'show'])
    ->whereNumber('version');
Route::post('/verify/{chainId}/v{version}', [VerifyController::class, 'verifyFileForVersion'])
    ->whereNumber('version');

// PKI endpoints (public)
Route::get('/pki/root-ca', [PkiController::class, 'rootCa']);

// Protected routes (auth required)
Route::middleware(['auth:api'])->group(function () {
    // Auth
    Route::get('/auth/me', [AuthController::class, 'me']);

    // PKI (authenticated)
    Route::get('/pki/certificates/me', [PkiController::class, 'me']);
    Route::post('/pki/certificates/me/enroll', [PkiController::class, 'enroll']);
    Route::post('/pki/certificates/me/renew', [PkiController::class, 'renew']);
    Route::post('/pki/certificates/me/revoke', [PkiController::class, 'revoke']);

    // =========================================================================
    // Document Workflow Endpoints
    // =========================================================================

    // List all documents (tenant admin)
    Route::get('/documents', [DocumentWorkflowController::class, 'adminDocumentList']);

    // Upload draft document (tenant admin)
    Route::post('/documents/drafts', [DocumentWorkflowController::class, 'uploadDraft']);

    // Assign signers to document (tenant admin)
    Route::post('/documents/{document}/signers', [DocumentWorkflowController::class, 'assignSigners']);

    // Sign document (user's turn)
    Route::post('/documents/{document}/sign', [DocumentWorkflowController::class, 'sign']);

    // Get user's inbox (need_signature, waiting, completed)
    Route::get('/documents/inbox', [DocumentWorkflowController::class, 'inbox']);

    // Cancel document (tenant admin)
    Route::post('/documents/{document}/cancel', [DocumentWorkflowController::class, 'cancel']);

    // =========================================================================
    // Legacy Document Endpoints (Direct Sign)
    // =========================================================================

    Route::post('/documents/sign', [DocumentController::class, 'sign']);
    Route::get('/documents/{document}', [DocumentController::class, 'show']);
    Route::get('/documents/{document}/versions', [DocumentController::class, 'versions']);
    Route::get('/documents/{document}/versions/latest:download', [DocumentController::class, 'downloadLatest']);
    Route::get('/documents/{document}/versions/v{version}:download', [DocumentController::class, 'downloadVersion'])
        ->whereNumber('version');

    // =========================================================================
    // Tenant Admin User Management
    // =========================================================================

    // List all users in tenant
    Route::get('/admin/users', [TenantAdminUserController::class, 'index']);

    // Create new user and add to tenant
    Route::post('/admin/users', [TenantAdminUserController::class, 'store']);

    // Assign existing user to tenant
    Route::post('/admin/users/assign', [TenantAdminUserController::class, 'assign']);

    // Remove user from tenant
    Route::delete('/admin/users/{user}', [TenantAdminUserController::class, 'destroy']);
});

