<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\TenantUser as CentralTenantUser;
use App\Models\Tenant\User as TenantUser;
use App\Models\User as CentralUser;
use App\Services\UserCertificateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\PersonalAccessClient;

class TenantRegistrationController extends Controller
{
    public function register(Request $request, UserCertificateService $certificateService): JsonResponse
    {
        $request->merge([
            'tenantSlug' => $request->input('tenantSlug') ?: null,
            'role' => $request->input('role') ?: null,
        ]);

        $data = $request->validate([
            'tenantName' => ['required', 'string', 'max:150'],
            'tenantSlug' => ['nullable', 'string', 'max:60', 'alpha_dash'],
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role' => ['nullable', 'string', 'max:50'],
        ]);

        if (CentralUser::where('email', $data['email'])->exists()) {
            return response()->json([
                'message' => 'Email already registered. Please login.',
            ], 409);
        }

        if (Tenant::where('name', $data['tenantName'])->exists()) {
            return response()->json([
                'message' => 'Tenant name already exists.',
            ], 409);
        }

        $slug = $data['tenantSlug'] ?? null;
        if ($slug) {
            $slug = Str::slug($slug);

            if (Tenant::where('slug', $slug)->exists()) {
                return response()->json([
                    'message' => 'Tenant slug already exists.',
                ], 409);
            }
        } else {
            $slug = Tenant::generateSlug($data['tenantName']);
        }

        $role = $data['role'] ?? 'owner';

        $tenant = Tenant::create([
            'name' => $data['tenantName'],
            'slug' => $slug,
            'code' => Tenant::generateCode(),
        ]);

        $centralUser = null;

        try {
            DB::beginTransaction();

            $centralUser = CentralUser::create([
                'global_id' => (string) Str::ulid(),
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
            ]);

            $tenant->owner_id = $centralUser->id;
            $tenant->save();

            CentralTenantUser::updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'global_user_id' => $centralUser->global_id,
                ],
                [
                    'role' => $role,
                    'is_owner' => true,
                    'tenant_join_date' => now(),
                ]
            );

            $certificateService->ensureForUser($centralUser);

            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();
            $tenant->delete();
            throw $th;
        }

        $token = null;

        $tenant->run(function () use ($data, $tenant, $role, $centralUser, $certificateService, &$token) {
            $tenantUser = TenantUser::create([
                'global_id' => $centralUser->global_id,
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'tenant_id' => $tenant->id,
                'role' => $role,
                'is_owner' => true,
                'tenant_join_date' => now(),
            ]);

            if (!PersonalAccessClient::query()->exists()) {
                app(ClientRepository::class)->createPersonalAccessClient(
                    null,
                    'Tenant Personal Access',
                    config('app.url') ?: url('/')
                );
            }

            $this->ensurePassportKeys($certificateService);

            $token = $tenantUser->createToken('api')->accessToken;
        });

        return response()->json([
            'accessToken' => $token,
            'tenantId' => $tenant->id,
            'tenantSlug' => $tenant->slug,
            'userId' => $centralUser->global_id,
        ], 201);
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
