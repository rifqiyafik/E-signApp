<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\TenantUser as CentralTenantUser;
use App\Models\Tenant\User as TenantUser;
use App\Models\User as CentralUser;
use App\Services\AuditLogService;
use App\Services\UserCertificateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\PersonalAccessClient;

class SuperAdminTenantUserController extends Controller
{
    public function store(Request $request, UserCertificateService $certificateService, AuditLogService $auditLogService): JsonResponse
    {
        $tenant = $this->resolveTenant($request->route('tenant'));

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role' => ['nullable', 'string', 'max:50'],
            'isOwner' => ['nullable', 'boolean'],
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
            'isOwner.boolean' => 'Is owner harus berupa boolean.',
        ], [
            'name' => 'nama',
            'email' => 'email',
            'password' => 'password',
            'password_confirmation' => 'konfirmasi password',
            'role' => 'role',
            'isOwner' => 'is owner',
        ]);

        if (CentralUser::where('email', $data['email'])->exists()) {
            return response()->json([
                'message' => 'Email sudah terdaftar. Silakan gunakan akun yang ada.',
                'code' => 'email_already_registered',
                'errors' => [
                    'email' => ['Email sudah terdaftar. Silakan gunakan akun yang ada.'],
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
        $isOwner = (bool) ($data['isOwner'] ?? false);

        CentralTenantUser::updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'global_user_id' => $centralUser->global_id,
            ],
            [
                'role' => $role,
                'is_owner' => $isOwner,
                'tenant_join_date' => now(),
            ]
        );

        $certificateService->ensureForUser($centralUser);

        $this->ensureTenantUser($tenant, $centralUser, $role, $isOwner, $certificateService);

        $auditLogService->log(
            $request,
            'tenant_user_created',
            $tenant->id,
            $request->user('central_api')?->global_id,
            CentralUser::class,
            $centralUser->global_id,
            [
                'role' => $role,
                'isOwner' => $isOwner,
            ]
        );

        return response()->json([
            'user' => $this->userPayload($centralUser, $tenant->id, $role, $isOwner),
        ], 201);
    }

    public function assign(Request $request, UserCertificateService $certificateService, AuditLogService $auditLogService): JsonResponse
    {
        $tenant = $this->resolveTenant($request->route('tenant'));

        $data = $request->validate([
            'user' => ['required', 'string'],
            'role' => ['nullable', 'string', 'max:50'],
            'isOwner' => ['nullable', 'boolean'],
        ], [
            'user.required' => 'User wajib diisi.',
            'user.string' => 'User harus berupa teks.',
            'role.string' => 'Role harus berupa teks.',
            'role.max' => 'Role maksimal 50 karakter.',
            'isOwner.boolean' => 'Is owner harus berupa boolean.',
        ], [
            'user' => 'user',
            'role' => 'role',
            'isOwner' => 'is owner',
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

        $role = $data['role'] ?? 'member';
        $isOwner = (bool) ($data['isOwner'] ?? false);

        CentralTenantUser::updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'global_user_id' => $centralUser->global_id,
            ],
            [
                'role' => $role,
                'is_owner' => $isOwner,
                'tenant_join_date' => now(),
            ]
        );

        $this->ensureTenantUser($tenant, $centralUser, $role, $isOwner, $certificateService);

        $auditLogService->log(
            $request,
            'tenant_user_assigned',
            $tenant->id,
            $request->user('central_api')?->global_id,
            CentralUser::class,
            $centralUser->global_id,
            [
                'role' => $role,
                'isOwner' => $isOwner,
            ]
        );

        return response()->json([
            'user' => $this->userPayload($centralUser, $tenant->id, $role, $isOwner),
        ]);
    }

    private function resolveTenant(string $tenant): Tenant
    {
        return Tenant::where('id', $tenant)
            ->orWhere('slug', $tenant)
            ->firstOrFail();
    }

    private function ensureTenantUser(
        Tenant $tenant,
        CentralUser $centralUser,
        string $role,
        bool $isOwner,
        UserCertificateService $certificateService
    ): void {
        $tenant->run(function () use ($centralUser, $tenant, $role, $isOwner, $certificateService) {
            TenantUser::updateOrCreate(
                ['global_id' => $centralUser->global_id],
                [
                    'name' => $centralUser->name,
                    'email' => $centralUser->email,
                    'password' => $centralUser->password,
                    'tenant_id' => $tenant->id,
                    'role' => $role,
                    'is_owner' => $isOwner,
                    'tenant_join_date' => now(),
                ]
            );

            if (!PersonalAccessClient::query()->exists()) {
                app(ClientRepository::class)->createPersonalAccessClient(
                    null,
                    'Tenant Personal Access',
                    config('app.url') ?: url('/')
                );
            }

            $this->ensurePassportKeys($certificateService);
        });
    }

    private function userPayload(CentralUser $user, string $tenantId, string $role, bool $isOwner): array
    {
        return [
            'userId' => $user->global_id,
            'name' => $user->name,
            'email' => $user->email,
            'tenantId' => $tenantId,
            'role' => $role,
            'isOwner' => $isOwner,
        ];
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
