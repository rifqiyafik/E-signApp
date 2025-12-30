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
    /**
     * List all users in current tenant
     */
    public function index(Request $request): JsonResponse
    {
        $tenantId = tenant('id');

        // Get tenant users from tenant database (already in tenant context)
        $users = TenantUser::query()
            ->select(['id', 'global_id', 'name', 'email', 'role', 'is_owner', 'created_at'])
            ->orderBy('name')
            ->get();

        return response()->json([
            'users' => $users,
        ]);
    }

    public function store(Request $request, UserCertificateService $certificateService, AuditLogService $auditLogService): JsonResponse
    {
        $tenantId = tenant('id');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8'],
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
            'role.string' => 'Role harus berupa teks.',
            'role.max' => 'Role maksimal 50 karakter.',
        ], [
            'name' => 'nama',
            'email' => 'email',
            'password' => 'password',
            'role' => 'role',
        ]);

        $role = $data['role'] ?? 'member';

        // Check if user already exists in central database
        $existingCentralUser = CentralUser::where('email', $data['email'])->first();

        if ($existingCentralUser) {
            // Check if already assigned to this tenant
            $existingMembership = CentralTenantUser::where('tenant_id', $tenantId)
                ->where('global_user_id', $existingCentralUser->global_id)
                ->exists();

            if ($existingMembership) {
                return response()->json([
                    'success' => false,
                    'message' => 'User dengan email ini sudah terdaftar di tenant ini.',
                    'code' => 'user_already_in_tenant',
                    'errors' => [
                        'email' => ['User dengan email ini sudah terdaftar di tenant ini.'],
                    ],
                ], 409);
            }

            // User exists in central but not in this tenant - assign them
            CentralTenantUser::updateOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'global_user_id' => $existingCentralUser->global_id,
                ],
                [
                    'role' => $role,
                    'is_owner' => false,
                    'tenant_join_date' => now(),
                ]
            );

            // Ensure user exists in tenant database
            TenantUser::updateOrCreate(
                [
                    'global_id' => $existingCentralUser->global_id,
                ],
                [
                    'name' => $existingCentralUser->name,
                    'email' => $existingCentralUser->email,
                    'password' => $existingCentralUser->password,
                    'tenant_id' => $tenantId,
                    'role' => $role,
                    'is_owner' => false,
                    'tenant_join_date' => now(),
                ]
            );

            $certificateService->ensureForUser($existingCentralUser);

            $auditLogService->log(
                $request,
                'tenant_user_assigned',
                $tenantId,
                $request->user()?->global_id,
                CentralUser::class,
                $existingCentralUser->global_id,
                ['role' => $role, 'existing_user' => true]
            );

            return response()->json([
                'success' => true,
                'message' => 'User berhasil ditambahkan ke tenant.',
                'user' => [
                    'userId' => $existingCentralUser->global_id,
                    'name' => $existingCentralUser->name,
                    'email' => $existingCentralUser->email,
                    'role' => $role,
                ],
            ], 201);
        }

        // Create new user
        $centralUser = CentralUser::create([
            'global_id' => (string) Str::ulid(),
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
        ]);

        // Use updateOrCreate to prevent duplicate entry errors
        CentralTenantUser::updateOrCreate(
            [
                'tenant_id' => $tenantId,
                'global_user_id' => $centralUser->global_id,
            ],
            [
                'role' => $role,
                'is_owner' => false,
                'tenant_join_date' => now(),
            ]
        );

        TenantUser::updateOrCreate(
            [
                'global_id' => $centralUser->global_id,
            ],
            [
                'name' => $centralUser->name,
                'email' => $centralUser->email,
                'password' => $centralUser->password,
                'tenant_id' => $tenantId,
                'role' => $role,
                'is_owner' => false,
                'tenant_join_date' => now(),
            ]
        );

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

    /**
     * Remove user from tenant (does not delete from central database)
     */
    public function destroy(string $user, AuditLogService $auditLogService): JsonResponse
    {
        $tenantId = tenant('id');

        // Find user by global_id
        $tenantUser = TenantUser::where('global_id', $user)->first();

        if (!$tenantUser) {
            return response()->json([
                'message' => 'User tidak ditemukan.',
                'code' => 'user_not_found',
            ], 404);
        }

        // Prevent removing owner
        if ($tenantUser->is_owner) {
            return response()->json([
                'message' => 'Tidak dapat menghapus owner tenant.',
                'code' => 'cannot_remove_owner',
            ], 403);
        }

        // Remove from tenant database
        $tenantUser->delete();

        // Remove from central pivot table
        CentralTenantUser::where('tenant_id', $tenantId)
            ->where('global_user_id', $user)
            ->delete();

        $auditLogService->log(
            request(),
            'tenant_user_removed',
            $tenantId,
            request()->user()?->global_id,
            CentralUser::class,
            $user,
            []
        );

        return response()->json([
            'message' => 'User berhasil dihapus dari tenant.',
        ]);
    }
}
