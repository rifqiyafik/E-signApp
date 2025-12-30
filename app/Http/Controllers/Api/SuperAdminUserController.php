<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User as CentralUser;
use App\Models\TenantUser as CentralTenantUser;
use App\Models\Tenant;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class SuperAdminUserController extends Controller
{
    /**
     * List all global users with their tenant memberships
     */
    public function index(Request $request): JsonResponse
    {
        $search = $request->query('search');

        $query = CentralUser::query()
            ->orderBy('created_at', 'desc');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('global_id', 'like', "%{$search}%");
            });
        }

        $users = $query->limit(100)->get();

        // Get all tenant memberships for these users
        $userIds = $users->pluck('global_id');
        $memberships = CentralTenantUser::whereIn('global_user_id', $userIds)
            ->get()
            ->groupBy('global_user_id');

        // Get all tenants for name lookup
        $tenantIds = CentralTenantUser::whereIn('global_user_id', $userIds)
            ->pluck('tenant_id')
            ->unique();
        $tenants = Tenant::whereIn('id', $tenantIds)->get()->keyBy('id');

        $result = $users->map(function ($user) use ($memberships, $tenants) {
            $userMemberships = $memberships->get($user->global_id, collect());

            return [
                'global_id' => $user->global_id,
                'name' => $user->name,
                'email' => $user->email,
                'is_superadmin' => (bool) $user->is_superadmin,
                'created_at' => optional($user->created_at)->toIso8601String(),
                'tenants' => $userMemberships->map(function ($m) use ($tenants) {
                    $tenant = $tenants->get($m->tenant_id);
                    return [
                        'tenant_id' => $m->tenant_id,
                        'tenant_name' => $tenant?->name ?? $m->tenant_id,
                        'tenant_slug' => $tenant?->slug ?? null,
                        'role' => $m->role,
                        'is_owner' => (bool) $m->is_owner,
                    ];
                })->values()->toArray(),
            ];
        });

        return response()->json([
            'users' => $result,
            'total' => $users->count(),
        ]);
    }

    /**
     * Get a single user with full details
     */
    public function show(string $userId): JsonResponse
    {
        $user = CentralUser::where('global_id', $userId)
            ->orWhere('email', $userId)
            ->firstOrFail();

        $memberships = CentralTenantUser::where('global_user_id', $user->global_id)->get();
        $tenantIds = $memberships->pluck('tenant_id');
        $tenants = Tenant::whereIn('id', $tenantIds)->get()->keyBy('id');

        return response()->json([
            'user' => [
                'global_id' => $user->global_id,
                'name' => $user->name,
                'email' => $user->email,
                'is_superadmin' => (bool) $user->is_superadmin,
                'created_at' => optional($user->created_at)->toIso8601String(),
                'updated_at' => optional($user->updated_at)->toIso8601String(),
                'tenants' => $memberships->map(function ($m) use ($tenants) {
                    $tenant = $tenants->get($m->tenant_id);
                    return [
                        'tenant_id' => $m->tenant_id,
                        'tenant_name' => $tenant?->name ?? $m->tenant_id,
                        'tenant_slug' => $tenant?->slug ?? null,
                        'role' => $m->role,
                        'is_owner' => (bool) $m->is_owner,
                        'joined_at' => optional($m->tenant_join_date)->toIso8601String(),
                    ];
                })->values()->toArray(),
            ],
        ]);
    }

    /**
     * Update a global user (name, email, password, superadmin status)
     */
    public function update(Request $request, string $userId, AuditLogService $auditLogService): JsonResponse
    {
        $user = CentralUser::where('global_id', $userId)->firstOrFail();

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'email' => ['sometimes', 'email', 'max:255'],
            'password' => ['sometimes', 'nullable', 'string', 'min:8'],
            'is_superadmin' => ['sometimes', 'boolean'],
        ], [
            'name.string' => 'Nama harus berupa teks.',
            'name.max' => 'Nama maksimal 120 karakter.',
            'email.email' => 'Format email tidak valid.',
            'email.max' => 'Email maksimal 255 karakter.',
            'password.string' => 'Password harus berupa teks.',
            'password.min' => 'Password minimal 8 karakter.',
        ]);

        // Check if email is being changed and if it conflicts
        if (isset($data['email']) && $data['email'] !== $user->email) {
            if (CentralUser::where('email', $data['email'])->where('global_id', '!=', $user->global_id)->exists()) {
                return response()->json([
                    'message' => 'Email sudah digunakan oleh user lain.',
                    'code' => 'email_conflict',
                    'errors' => [
                        'email' => ['Email sudah digunakan oleh user lain.'],
                    ],
                ], 409);
            }
        }

        $changes = [];

        if (isset($data['name']) && $data['name'] !== $user->name) {
            $changes['name'] = ['from' => $user->name, 'to' => $data['name']];
            $user->name = $data['name'];
        }

        if (isset($data['email']) && $data['email'] !== $user->email) {
            $changes['email'] = ['from' => $user->email, 'to' => $data['email']];
            $user->email = $data['email'];
        }

        if (!empty($data['password'])) {
            $changes['password'] = ['updated' => true];
            $user->password = Hash::make($data['password']);
        }

        if (isset($data['is_superadmin'])) {
            $changes['is_superadmin'] = ['from' => (bool) $user->is_superadmin, 'to' => $data['is_superadmin']];
            $user->is_superadmin = $data['is_superadmin'];
        }

        $user->save();

        // Sync changes to tenant databases
        $this->syncUserToTenants($user);

        // Log the action
        $auditLogService->log(
            $request,
            'global_user_updated',
            null,
            $request->user('central_api')?->global_id,
            CentralUser::class,
            $user->global_id,
            $changes
        );

        return response()->json([
            'message' => 'User berhasil diupdate.',
            'user' => [
                'global_id' => $user->global_id,
                'name' => $user->name,
                'email' => $user->email,
                'is_superadmin' => (bool) $user->is_superadmin,
            ],
        ]);
    }

    /**
     * Delete a global user and all their tenant associations
     */
    public function destroy(Request $request, string $userId, AuditLogService $auditLogService): JsonResponse
    {
        $user = CentralUser::where('global_id', $userId)->firstOrFail();

        // Prevent deleting yourself
        if ($request->user('central_api')?->global_id === $user->global_id) {
            return response()->json([
                'message' => 'Tidak dapat menghapus akun Anda sendiri.',
                'code' => 'cannot_delete_self',
            ], 403);
        }

        // Get all tenant memberships before deletion for cleanup
        $memberships = CentralTenantUser::where('global_user_id', $user->global_id)->get();

        // Remove from all tenant databases
        foreach ($memberships as $membership) {
            $tenant = Tenant::find($membership->tenant_id);
            if ($tenant) {
                $tenant->run(function () use ($user) {
                    \App\Models\Tenant\User::where('global_id', $user->global_id)->delete();
                });
            }
        }

        // Delete all central memberships
        CentralTenantUser::where('global_user_id', $user->global_id)->delete();

        // Log before deletion
        $auditLogService->log(
            $request,
            'global_user_deleted',
            null,
            $request->user('central_api')?->global_id,
            CentralUser::class,
            $user->global_id,
            [
                'name' => $user->name,
                'email' => $user->email,
                'tenant_count' => $memberships->count(),
            ]
        );

        // Delete the central user
        $user->delete();

        return response()->json([
            'message' => 'User berhasil dihapus.',
        ]);
    }

    /**
     * Sync user data to all tenant databases
     */
    private function syncUserToTenants(CentralUser $user): void
    {
        $memberships = CentralTenantUser::where('global_user_id', $user->global_id)->get();

        foreach ($memberships as $membership) {
            $tenant = Tenant::find($membership->tenant_id);
            if ($tenant) {
                $tenant->run(function () use ($user, $membership) {
                    \App\Models\Tenant\User::where('global_id', $user->global_id)
                        ->update([
                            'name' => $user->name,
                            'email' => $user->email,
                            'password' => $user->password,
                        ]);
                });
            }
        }
    }
}
