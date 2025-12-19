<?php

namespace App\Services;

use App\Models\User;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Services\TenantService;
use App\Traits\Loggable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Passport\Passport;

/**
 * Nusawork Login Service
 * 
 * Service untuk menangani login melalui Nusawork SSO.
 * Handles token validation, user management, dan tenant management.
 */
class NusaworkLoginService
{
    use Loggable;

    /**
     * Error code untuk exception yang perlu ditampilkan ke user
     */
    private const ERROR_USER_FRIENDLY = 100;

    /**
     * Nusawork API path untuk verifikasi akses
     */
    private const NUSAWORK_ACCESS_PROFILE_PATH = '/emp/api/nusahire/integration/get_access_profile';

    /**
     * Selected tenant during login process
     */
    private ?Tenant $selectedTenant = null;

    /**
     * Handle Nusawork login callback
     *
     * @param array $input
     * @param Request $request
     * @return array
     * @throws \Exception
     */
    public function handleCallback(array $input, Request $request): array
    {
        try {
            $this->selectedTenant = null;

            // Parse dan validasi token
            $tokenData = $this->parseAndValidateToken($input['token']);

            // Handle user (create atau update)
            $user = $this->handleUser($input, $tokenData, $request);

            // Load tenants user
            $user->load('tenants');

            // Handle tenant management
            $this->handleTenantManagement($user, $input, $tokenData, $request);

            // Generate token dan response
            return $this->generateTokenResponse($user, $request);
        } catch (\Throwable $th) {
            $this->logError('Nusawork callback error: ', [
                'request' => $input,
                'file' => $th->getFile(),
                'line' => $th->getLine(),
                'message' => $th->getMessage(),
            ]);

            if ($th->getCode() === self::ERROR_USER_FRIENDLY) {
                throw new \Exception($th->getMessage(), self::ERROR_USER_FRIENDLY);
            }

            throw new \Exception(__('Something went wrong. Please try again later.'));
        }
    }

    /**
     * Parse dan validasi token Nusawork
     */
    private function parseAndValidateToken(string $token): array
    {
        $tokenParse = explode('.', $token);
        $payload = json_decode(base64_decode($tokenParse[1]), true);

        $nusaworkDomain = $payload['iss'] ?? null;
        $uid = $payload['uid'] ?? null;
        $nusaworkId = $nusaworkDomain . '|' . $uid;
        $isSuperAdmin = in_array("Super Admin", $payload['role']['role_name'] ?? []);

        if (!$uid || !$nusaworkDomain) {
            throw new \Exception(__('Invalid token'));
        }

        return [
            'nusawork_domain' => $nusaworkDomain,
            'uid' => $uid,
            'nusawork_id' => $nusaworkId,
            'is_super_admin' => $isSuperAdmin,
        ];
    }

    /**
     * Handle user creation atau update
     */
    private function handleUser(array $input, array $tokenData, Request $request): User
    {
        $user = User::where('email', $input['email'])->first();

        if (!$user) {
            // Buat user baru
            $user = User::create([
                'name' => $input['first_name'] . ' ' . $input['last_name'],
                'email' => $input['email'],
                'password' => Hash::make(uniqid()),
                'email_verified_at' => now(),
                'nusawork_id' => $tokenData['nusawork_id'],
                'avatar' => $input['photo'] ?? null,
                'last_login_ip' => $request->ip(),
                'last_login_at' => now(),
                'last_login_user_agent' => $request->userAgent(),
            ]);
        } else {
            // Update data user
            $user->update([
                'name' => $input['first_name'] . ' ' . $input['last_name'],
                'email_verified_at' => $user->email_verified_at ?? now(),
                'nusawork_id' => $tokenData['nusawork_id'],
                'avatar' => $input['photo'] ?? null,
                'last_login_ip' => $request->ip(),
                'last_login_at' => now(),
                'last_login_user_agent' => $request->userAgent(),
            ]);
        }

        return $user;
    }

    /**
     * Handle tenant management berdasarkan kondisi user
     */
    private function handleTenantManagement(User $user, array $input, array $tokenData, Request $request): void
    {
        if ($user->tenants->count() === 0) {
            $this->handleUserWithoutTenant($user, $input, $tokenData, $request);
        } else {
            $this->handleUserWithTenant($user, $input, $tokenData, $request);
        }
    }

    /**
     * Handle user yang belum memiliki tenant
     */
    private function handleUserWithoutTenant(User $user, array $input, array $tokenData, Request $request): void
    {
        Passport::actingAs($user);

        if (!empty($input['join_code'])) {
            $this->handleJoinWithCode($user, $input, $tokenData, $request);
        } else {
            $this->handleJoinWithCompany($user, $input, $tokenData, $request);
        }
    }

    /**
     * Handle user yang sudah memiliki tenant
     */
    private function handleUserWithTenant(User $user, array $input, array $tokenData, Request $request): void
    {
        if (!empty($input['join_code'])) {
            $this->handleJoinWithCode($user, $input, $tokenData, $request);
        } else {
            $this->handleExistingTenant($user, $input, $tokenData, $request);
        }
    }

    /**
     * Handle join dengan invitation code
     */
    private function handleJoinWithCode(User $user, array $input, array $tokenData, Request $request): void
    {
        $targetTenant = Tenant::where('code', $input['join_code'])->first();

        if (!$targetTenant) {
            throw new \Exception(__('Portal with invitation code not found.'), self::ERROR_USER_FRIENDLY);
        }

        $this->selectedTenant = $targetTenant;

        // Update tenant user dan attach
        $this->updateTenantUserInContext($targetTenant, $user, $tokenData, $input, $request);

        if (!$user->tenants()->where('tenant_id', $targetTenant->id)->exists()) {
            $user->tenants()->attach($targetTenant->id);
        }

        $this->updateTenantUserRecord($user, $targetTenant->id, $tokenData, $input);
    }

    /**
     * Handle join dengan company name
     */
    private function handleJoinWithCompany(User $user, array $input, array $tokenData, Request $request): void
    {
        $tenant = Tenant::where('name', $input['company']['name'])->first();

        // Jika tenant belum ada dan user adalah super admin, buat tenant baru
        if (!$tenant && $tokenData['is_super_admin']) {
            $this->createNewTenant($user, $input);
            $tenant = Tenant::where('name', $input['company']['name'])->first();
        }

        if ($tenant) {
            $this->selectedTenant = $tenant;

            $this->updateTenantUserInContext($tenant, $user, $tokenData, $input, $request);

            if (!$user->tenants()->where('tenant_id', $tenant->id)->exists()) {
                $user->tenants()->attach($tenant->id);
            }

            $this->updateTenantUserRecord($user, $tenant->id, $tokenData, $input);
        }
    }

    /**
     * Handle existing tenant
     */
    private function handleExistingTenant(User $user, array $input, array $tokenData, Request $request): void
    {
        $tenantName = $input['company']['name'];
        $tenant = Tenant::where('name', $tenantName)->first();

        if ($tenant) {
            $this->selectedTenant = $tenant;

            $this->updateTenantUserInContext($tenant, $user, $tokenData, $input, $request);

            if (!$user->tenants()->where('tenant_id', $tenant->id)->exists()) {
                $user->tenants()->attach($tenant->id);
            }

            $this->updateTenantUserRecord($user, $tenant->id, $tokenData, $input);
        }

        if (!$tenant && $tokenData['is_super_admin']) {
            Passport::actingAs($user);
            $this->createNewTenant($user, $input);
            $tenant = Tenant::where('name', $input['company']['name'])->first();

            if ($tenant) {
                $this->selectedTenant = $tenant;

                $this->updateTenantUserInContext($tenant, $user, $tokenData, $input, $request);

                if (!$user->tenants()->where('tenant_id', $tenant->id)->exists()) {
                    $user->tenants()->attach($tenant->id);
                }

                $this->updateTenantUserRecord($user, $tenant->id, $tokenData, $input);
            }
        }
    }

    /**
     * Update tenant_user record di central database
     */
    private function updateTenantUserRecord(User $user, string $tenantId, array $tokenData, array $input): void
    {
        $tenantUserCentral = $user->tenantUsers()->where('tenant_id', $tenantId)->first();
        $tenant = Tenant::find($tenantId);

        if ($tenantUserCentral) {
            $role = $this->determineUserRole($user, $tenant, $tokenData, $input);

            $tenantUserCentral->update([
                'nusawork_id' => $tokenData['nusawork_id'],
                'avatar' => $input['photo'] ?? $tenantUserCentral->avatar,
                'role' => $role,
                'tenant_join_date' => $tenantUserCentral->tenant_join_date ?? now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Update tenant user dalam konteks tenant
     */
    private function updateTenantUserInContext(Tenant $tenant, User $user, array $tokenData, array $input, Request $request): void
    {
        $role = $this->determineUserRole($user, $tenant, $tokenData, $input);

        $tenant->run(function () use ($user, $tenant, $tokenData, $input, $request, $role) {
            $tenantUser = \App\Models\Tenant\User::where('global_id', $user->global_id)->first();

            if ($tenantUser) {
                $existingRole = $tenantUser->role;
                $finalRole = ($existingRole === 'super_admin' || $existingRole === 'admin') ? $existingRole : $role;

                $tenantUser->update([
                    'tenant_id' => $tenant->id,
                    'nusawork_id' => $tokenData['nusawork_id'],
                    'avatar' => $input['photo'] ?? $tenantUser->avatar,
                    'role' => $finalRole,
                    'tenant_join_date' => $tenantUser->tenant_join_date ?? now(),
                    'last_login_ip' => $request->ip(),
                    'last_login_at' => now(),
                    'last_login_user_agent' => $request->userAgent(),
                ]);

                $tenantUser->syncRoles([$finalRole]);
            } else {
                $tenantUser = \App\Models\Tenant\User::create([
                    'global_id' => $user->global_id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'email_verified_at' => $user->email_verified_at,
                    'password' => $user->password,
                    'tenant_id' => $tenant->id,
                    'nusawork_id' => $tokenData['nusawork_id'],
                    'avatar' => $input['photo'] ?? $user->avatar,
                    'role' => $role,
                    'tenant_join_date' => now(),
                    'last_login_ip' => $request->ip(),
                    'last_login_at' => now(),
                    'last_login_user_agent' => $request->userAgent(),
                ]);

                $tenantUser->syncRoles([$role]);
            }
        });
    }

    /**
     * Buat tenant baru
     */
    private function createNewTenant(User $user, array $input): void
    {
        TenantService::store([
            'code' => Tenant::generateCode(),
            'name' => $input['company']['name'],
        ]);
    }

    /**
     * Generate token dan response
     */
    private function generateTokenResponse(User $user, Request $request): array
    {
        $token = $user->createToken('auth_token')->accessToken;

        return [
            'status' => 'success',
            'token' => $token,
            'user' => $user,
            'select_tenant' => $this->selectedTenant,
        ];
    }

    /**
     * Menentukan role user berdasarkan kondisi
     */
    private function determineUserRole(User $user, ?Tenant $tenant, array $tokenData, array $input): string
    {
        $existingRole = null;
        if ($tenant) {
            $tenantUserCentral = $user->tenantUsers()->where('tenant_id', $tenant->id)->first();
            if ($tenantUserCentral) {
                $existingRole = $tenantUserCentral->role;
            }
        }

        if ($existingRole === 'super_admin') {
            return $existingRole;
        }

        $role = 'member';

        if ($tokenData['is_super_admin']) {
            $role = 'super_admin';
        }

        if ($tenant && $tenant->owner_id === $user->id) {
            $role = 'super_admin';
        }

        if ($user->tenants->count() === 0 && !empty($input['join_code'])) {
            $role = 'member';
        }

        if ($user->tenants->count() > 0 && !empty($input['join_code'])) {
            $role = 'member';
        }

        return $role;
    }
}
