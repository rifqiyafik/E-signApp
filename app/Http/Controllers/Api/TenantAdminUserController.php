<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TenantUser as CentralTenantUser;
use App\Models\Tenant\User as TenantUser;
use App\Models\User as CentralUser;
use App\Services\AuditLogService;
use App\Services\UserCertificateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TenantAdminUserController extends Controller
{
    public function store(Request $request, UserCertificateService $certificateService, AuditLogService $auditLogService): JsonResponse
    {
        $tenantId = tenant('id');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role' => ['nullable', 'string', 'max:50'],
        ], [
            'name.required' => 'Nama wajib diisi.',
            'name.string' => 'Nama harus berupa teks.',
            'name.max' => 'Nama maksimal 120 karakter.',
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'email.max' => 'Email maksimal 255 karakter.',
            'password.required' => 'Password wajib diisi.',
            'password.string' => 'Password harus berupa teks.',
            'password.min' => 'Password minimal 8 karakter.',
            'password.confirmed' => 'Konfirmasi password tidak sama.',
            'role.string' => 'Role harus berupa teks.',
            'role.max' => 'Role maksimal 50 karakter.',
        ], [
            'name' => 'nama',
            'email' => 'email',
            'password' => 'password',
            'password_confirmation' => 'konfirmasi password',
            'role' => 'role',
        ]);

        if (CentralUser::where('email', $data['email'])->exists()) {
            return response()->json([
                'message' => 'Email sudah terdaftar.',
                'code' => 'email_already_registered',
                'errors' => [
                    'email' => ['Email sudah terdaftar.'],
                ],
            ], 409);
        }

        $centralUser = CentralUser::create([
            'global_id' => (string) Str::ulid(),
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
        ]);

        $role = $data['role'] ?? 'member';

        CentralTenantUser::create([
            'tenant_id' => $tenantId,
            'global_user_id' => $centralUser->global_id,
            'role' => $role,
            'is_owner' => false,
            'tenant_join_date' => now(),
        ]);

        TenantUser::create([
            'global_id' => $centralUser->global_id,
            'name' => $centralUser->name,
            'email' => $centralUser->email,
            'password' => $centralUser->password,
            'tenant_id' => $tenantId,
            'role' => $role,
            'is_owner' => false,
            'tenant_join_date' => now(),
        ]);

        $certificateService->ensureForUser($centralUser);

        $auditLogService->log(
            $request,
            'tenant_user_created',
            $tenantId,
            $request->user()?->global_id,
            CentralUser::class,
            $centralUser->global_id,
            [
                'role' => $role,
            ]
        );

        return response()->json([
            'user' => [
                'userId' => $centralUser->global_id,
                'name' => $centralUser->name,
                'email' => $centralUser->email,
                'role' => $role,
            ],
        ], 201);
    }

    public function assign(Request $request, AuditLogService $auditLogService): JsonResponse
    {
        $tenantId = tenant('id');

        $data = $request->validate([
            'user' => ['required', 'string'],
            'role' => ['nullable', 'string', 'max:50'],
        ], [
            'user.required' => 'User wajib diisi.',
            'user.string' => 'User harus berupa teks.',
            'role.string' => 'Role harus berupa teks.',
            'role.max' => 'Role maksimal 50 karakter.',
        ], [
            'user' => 'user',
            'role' => 'role',
        ]);

        $userValue = $data['user'];
        $centralUser = CentralUser::where('global_id', $userValue)
            ->orWhere('email', $userValue)
            ->first();

        if (!$centralUser) {
            return response()->json([
                'message' => 'User tidak ditemukan.',
                'code' => 'user_not_found',
                'errors' => [
                    'user' => ['User tidak ditemukan.'],
                ],
            ], 404);
        }

        $role = $data['role'];

        $membership = CentralTenantUser::where('tenant_id', $tenantId)
            ->where('global_user_id', $centralUser->global_id)
            ->first();

        if (!$membership) {
            CentralTenantUser::create([
                'tenant_id' => $tenantId,
                'global_user_id' => $centralUser->global_id,
                'role' => $role ?: 'member',
                'is_owner' => false,
                'tenant_join_date' => now(),
            ]);
        } elseif ($role && $membership->role !== $role) {
            $membership->role = $role;
            $membership->save();
        }

        $tenantUser = TenantUser::where('global_id', $centralUser->global_id)->first();
        if (!$tenantUser) {
            TenantUser::create([
                'global_id' => $centralUser->global_id,
                'name' => $centralUser->name,
                'email' => $centralUser->email,
                'password' => $centralUser->password,
                'tenant_id' => $tenantId,
                'role' => $role ?: 'member',
                'is_owner' => false,
                'tenant_join_date' => now(),
            ]);
        } elseif ($role && $tenantUser->role !== $role) {
            $tenantUser->role = $role;
            $tenantUser->save();
        }

        $auditLogService->log(
            $request,
            'tenant_user_assigned',
            $tenantId,
            $request->user()?->global_id,
            CentralUser::class,
            $centralUser->global_id,
            [
                'role' => $role,
            ]
        );

        return response()->json([
            'user' => [
                'userId' => $centralUser->global_id,
                'name' => $centralUser->name,
                'email' => $centralUser->email,
                'role' => $role ?: ($membership?->role ?? $tenantUser?->role ?? 'member'),
            ],
        ]);
    }
}
