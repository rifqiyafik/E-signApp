<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tenant\User as TenantUser;
use App\Models\User as CentralUser;
use App\Models\TenantUser as CentralTenantUser;
use App\Services\UserCertificateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request, UserCertificateService $certificateService): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'deviceName' => ['nullable', 'string', 'max:100'],
        ]);

        $tenantId = tenant('id');
        $existingUser = CentralUser::where('email', $data['email'])->first();

        if ($existingUser) {
            return response()->json([
                'message' => 'Email already registered. Please login.',
            ], 409);
        }

        $centralUser = CentralUser::create([
            'global_id' => (string) Str::ulid(),
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
        ]);

        $tenantUser = null;

        try {
            $tenantUser = TenantUser::create([
                'global_id' => $centralUser->global_id,
                'name' => $centralUser->name,
                'email' => $centralUser->email,
                'password' => $centralUser->password,
                'tenant_id' => $tenantId,
                'role' => 'member',
                'is_owner' => false,
                'tenant_join_date' => now(),
            ]);

            CentralTenantUser::create([
                'tenant_id' => $tenantId,
                'global_user_id' => $centralUser->global_id,
                'role' => 'member',
                'is_owner' => false,
                'tenant_join_date' => now(),
            ]);

            $certificateService->ensureForUser($centralUser);
        } catch (\Throwable $th) {
            if ($tenantUser) {
                $tenantUser->delete();
            }
            $centralUser->delete();
            throw $th;
        }

        $token = $tenantUser->createToken($data['deviceName'] ?? 'api')->accessToken;

        return response()->json([
            'accessToken' => $token,
            'tenantId' => $tenantId,
            'userId' => $centralUser->global_id,
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'deviceName' => ['nullable', 'string', 'max:100'],
        ]);

        $user = TenantUser::where('email', $data['email'])->first();

        if (!$user || !Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        $user->forceFill([
            'last_login_ip' => $request->ip(),
            'last_login_at' => now(),
            'last_login_user_agent' => $request->userAgent(),
        ])->save();

        $token = $user->createToken($data['deviceName'] ?? 'api')->accessToken;

        return response()->json([
            'accessToken' => $token,
            'tenantId' => tenant('id'),
            'userId' => $user->global_id,
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $tenant = tenant();

        $membership = CentralTenantUser::where('tenant_id', $tenant?->id)
            ->where('global_user_id', $user?->global_id)
            ->first();

        return response()->json([
            'profile' => [
                'userId' => $user?->global_id,
                'name' => $user?->name,
                'email' => $user?->email,
            ],
            'tenant' => $tenant?->only(['id', 'name', 'slug']),
            'membership' => $membership ? [
                'role' => $membership->role,
                'isOwner' => (bool) $membership->is_owner,
                'joinedAt' => optional($membership->tenant_join_date)->toIso8601String(),
            ] : null,
        ]);
    }
}
