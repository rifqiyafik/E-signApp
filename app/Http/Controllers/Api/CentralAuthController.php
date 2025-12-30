<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\TenantUser as CentralTenantUser;
use App\Models\User as CentralUser;
use App\Services\UserCertificateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\PersonalAccessClient;

class CentralAuthController extends Controller
{
    public function login(Request $request, UserCertificateService $certificateService): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ], [
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'password.required' => 'Password wajib diisi.',
            'password.string' => 'Password harus berupa teks.',
        ], [
            'email' => 'email',
            'password' => 'password',
        ]);

        $user = CentralUser::where('email', $data['email'])->first();

        if (!$user || !Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Email atau password salah.'],
            ]);
        }

        $this->ensureCentralPersonalAccessClient($certificateService);

        $user->forceFill([
            'last_login_ip' => $request->ip(),
            'last_login_at' => now(),
            'last_login_user_agent' => $request->userAgent(),
        ])->save();

        $token = $user->createToken('central')->accessToken;

        return response()->json([
            'accessToken' => $token,
            'user' => [
                'userId' => $user->global_id,
                'name' => $user->name,
                'email' => $user->email,
                'isSuperadmin' => (bool) $user->is_superadmin,
            ],
            'tenants' => $this->listTenants($user),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user('central_api');

        return response()->json([
            'user' => [
                'userId' => $user?->global_id,
                'name' => $user?->name,
                'email' => $user?->email,
                'isSuperadmin' => (bool) $user?->is_superadmin,
            ],
            'tenants' => $user ? $this->listTenants($user) : [],
        ]);
    }

    public function selectTenant(
        Request $request,
        UserCertificateService $certificateService
    ): JsonResponse {
        $data = $request->validate([
            'tenant' => ['required', 'string'],
        ], [
            'tenant.required' => 'Tenant wajib diisi.',
            'tenant.string' => 'Tenant harus berupa teks.',
        ], [
            'tenant' => 'tenant',
        ]);

        $user = $request->user('central_api');
        $tenant = Tenant::where('id', $data['tenant'])
            ->orWhere('slug', $data['tenant'])
            ->first();

        if (!$tenant) {
            return response()->json([
                'message' => 'Tenant tidak ditemukan.',
                'code' => 'tenant_not_found',
            ], 404);
        }

        if ($tenant->status !== 'active') {
            return response()->json([
                'message' => 'Tenant sedang tidak aktif.',
                'code' => 'tenant_inactive',
            ], 403);
        }

        $membership = CentralTenantUser::where('tenant_id', $tenant->id)
            ->where('global_user_id', $user->global_id)
            ->first();

        if (!$membership && $user->is_superadmin) {
            $membership = CentralTenantUser::create([
                'tenant_id' => $tenant->id,
                'global_user_id' => $user->global_id,
                'role' => 'super_admin',
                'is_owner' => false,
                'tenant_join_date' => now(),
            ]);
        }

        if (!$membership) {
            return response()->json([
                'message' => 'Anda tidak terdaftar pada tenant ini.',
                'code' => 'tenant_access_denied',
                'errors' => [
                    'tenant' => ['Anda tidak terdaftar pada tenant ini.'],
                ],
            ], 403);
        }

        $tenantToken = null;

        $tenant->run(function () use ($user, $tenant, $membership, $certificateService, &$tenantToken) {
            $this->ensureTenantPersonalAccessClient($certificateService);

            $tenantUser = \App\Models\Tenant\User::where('global_id', $user->global_id)->first();
            if (!$tenantUser) {
                $tenantUser = \App\Models\Tenant\User::create([
                    'global_id' => $user->global_id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'password' => $user->password,
                    'tenant_id' => $tenant->id,
                    'role' => $membership->role,
                    'is_owner' => (bool) $membership->is_owner,
                    'tenant_join_date' => $membership->tenant_join_date ?? now(),
                ]);
            }

            $tenantToken = $tenantUser->createToken('api')->accessToken;
        });

        return response()->json([
            'accessToken' => $tenantToken,
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'role' => $membership->role,
                'isOwner' => (bool) $membership->is_owner,
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user('central_api');
        $token = $user?->token();
        if ($token) {
            $token->revoke();
        }

        return response()->json([
            'message' => 'Logged out.',
        ]);
    }

    private function listTenants(CentralUser $user): array
    {
        if ($user->is_superadmin) {
            $memberships = CentralTenantUser::where('global_user_id', $user->global_id)
                ->get()
                ->keyBy('tenant_id');

            return Tenant::query()
                ->orderBy('name')
                ->get()
                ->map(function (Tenant $tenant) use ($memberships) {
                    $membership = $memberships->get($tenant->id);

                    return [
                        'id' => $tenant->id,
                        'name' => $tenant->name,
                        'slug' => $tenant->slug,
                        'status' => $tenant->status,
                        'role' => $membership?->role ?? 'super_admin',
                        'isOwner' => (bool) ($membership?->is_owner ?? false),
                    ];
                })
                ->values()
                ->all();
        }

        return CentralTenantUser::where('global_user_id', $user->global_id)
            ->with('tenant')
            ->get()
            ->map(function (CentralTenantUser $membership) {
                $tenant = $membership->tenant;

                return [
                    'id' => $tenant?->id,
                    'name' => $tenant?->name,
                    'slug' => $tenant?->slug,
                    'status' => $tenant?->status,
                    'role' => $membership->role,
                    'isOwner' => (bool) $membership->is_owner,
                ];
            })
            ->values()
            ->all();
    }

    private function ensureCentralPersonalAccessClient(UserCertificateService $certificateService): void
    {
        if (!PersonalAccessClient::query()->exists()) {
            app(ClientRepository::class)->createPersonalAccessClient(
                null,
                'Central Personal Access',
                config('app.url') ?: url('/')
            );
        }

        $this->ensurePassportKeys($certificateService);
    }

    private function ensureTenantPersonalAccessClient(UserCertificateService $certificateService): void
    {
        if (!PersonalAccessClient::query()->exists()) {
            app(ClientRepository::class)->createPersonalAccessClient(
                null,
                'Tenant Personal Access',
                config('app.url') ?: url('/')
            );
        }

        $this->ensurePassportKeys($certificateService);
    }

    private function ensurePassportKeys(UserCertificateService $certificateService): void
    {
        $privateKeyPath = storage_path('oauth-private.key');
        $publicKeyPath = storage_path('oauth-public.key');

        $privateKeyContent = file_exists($privateKeyPath) ? file_get_contents($privateKeyPath) : null;
        $publicKeyContent = file_exists($publicKeyPath) ? file_get_contents($publicKeyPath) : null;

        if ($privateKeyContent && $publicKeyContent) {
            $privateValid = openssl_pkey_get_private($privateKeyContent) !== false;
            $publicValid = openssl_pkey_get_public($publicKeyContent) !== false;

            if ($privateValid && $publicValid) {
                return;
            }
        }

        $opensslConfig = $certificateService->getOpenSslConfigPath();
        $privateKeyConfig = [
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'private_key_bits' => 2048,
        ];

        if ($opensslConfig) {
            $privateKeyConfig['config'] = $opensslConfig;
        }

        $privateKey = openssl_pkey_new($privateKeyConfig);
        if ($privateKey === false) {
            throw new \RuntimeException('Failed to generate Passport private key.');
        }

        $exportOptions = [];
        if ($opensslConfig) {
            $exportOptions['config'] = $opensslConfig;
        }

        $exported = $exportOptions
            ? openssl_pkey_export($privateKey, $privateKeyPem, null, $exportOptions)
            : openssl_pkey_export($privateKey, $privateKeyPem);

        if (!$exported) {
            throw new \RuntimeException('Failed to export Passport private key.');
        }

        $publicKeyDetails = openssl_pkey_get_details($privateKey);
        $publicKeyPem = $publicKeyDetails['key'] ?? null;

        if (!$publicKeyPem) {
            throw new \RuntimeException('Failed to extract Passport public key.');
        }

        $privateDir = dirname($privateKeyPath);
        if (!is_dir($privateDir)) {
            @mkdir($privateDir, 0775, true);
        }

        file_put_contents($privateKeyPath, $privateKeyPem);
        file_put_contents($publicKeyPath, $publicKeyPem);
    }
}
