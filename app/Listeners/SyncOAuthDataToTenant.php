<?php

namespace App\Listeners;

use App\Models\Tenant;
use App\Models\Tenant\User as TenantUser;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Stancl\Tenancy\Events\DatabaseMigrated;
use Stancl\Tenancy\Events\TenancyInitialized;

/**
 * Sync OAuth Data To Tenant
 * 
 * This listener syncs OAuth data from central to tenant database
 * when a tenant is created and initialized.
 * 
 * To use: Add this listener to TenancyServiceProvider events() method:
 * Events\TenantCreated::class => [
 *     JobPipeline::make([
 *         Jobs\CreateDatabase::class,
 *         Jobs\MigrateDatabase::class,
 *         \App\Listeners\SyncOAuthDataToTenant::class,
 *     ])...
 * ]
 */
class SyncOAuthDataToTenant
{
    /**
     * Handle the event.
     */
    public function handle(TenancyInitialized $event): void
    {
        $auth = Auth::user();

        if (!$auth) {
            return;
        }

        $tenant = Tenant::where('owner_id', $auth->id)
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$tenant) {
            return;
        }

        Log::debug('SyncOAuthDataToTenant: Central User', ['user' => $auth->id, 'tenant' => $tenant->id]);

        Event::listen(DatabaseMigrated::class, function () use ($auth, $tenant) {
            $this->syncUserToTenant($auth, $tenant);
            $this->syncOAuthData($tenant);
            $this->createTenantUser($auth, $tenant);

            // Optional: Uncomment if using Nusawork
            $this->dispatchNusaworkSync($auth, $tenant);
        });
    }

    /**
     * Sync user to central tenant_users
     */
    protected function syncUserToTenant($user, $tenant): void
    {
        try {
            if (!$user->tenants()->where('tenant_id', $tenant->id)->exists()) {
                $user->tenants()->attach($tenant->id);
            }

            $tenantUserCentral = $user->tenantUsers()->where('tenant_id', $tenant->id)->first();

            if ($tenantUserCentral) {
                $tenantUserCentral->update([
                    'role' => 'super_admin',
                    'is_owner' => true,
                    'tenant_join_date' => $tenantUserCentral->tenant_join_date ?? now(),
                    'updated_at' => now(),
                ]);
            }

            Log::info('SyncOAuthDataToTenant: User synced to tenant_users');
        } catch (\Exception $e) {
            Log::error('SyncOAuthDataToTenant: Failed to sync user', [
                'user_id' => $user->id,
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Sync OAuth data from central to tenant
     */
    protected function syncOAuthData($tenant): void
    {
        $connection = config('database.default');

        try {
            $oauthClients = DB::connection($connection)->table('oauth_clients')->get();
            $oauthPACs = DB::connection($connection)->table('oauth_personal_access_clients')->get();
            $oauthAccessTokens = DB::connection($connection)->table('oauth_access_tokens')->get();

            $tenant->run(function () use ($oauthClients, $oauthPACs, $oauthAccessTokens) {
                foreach ($oauthClients as $client) {
                    DB::table('oauth_clients')->insertOrIgnore((array) $client);
                }

                foreach ($oauthPACs as $pac) {
                    DB::table('oauth_personal_access_clients')->insertOrIgnore((array) $pac);
                }

                foreach ($oauthAccessTokens as $token) {
                    DB::table('oauth_access_tokens')->insertOrIgnore((array) $token);
                }
            });

            Log::info('SyncOAuthDataToTenant: OAuth data synced successfully');
        } catch (\Exception $e) {
            Log::error('SyncOAuthDataToTenant: Failed to sync OAuth data', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Create tenant user in tenant database
     */
    protected function createTenantUser($user, $tenant): void
    {
        $tenant->run(function () use ($user, $tenant) {
            $tenantUser = TenantUser::where('global_id', $user->global_id)->first();

            if ($tenantUser) {
                $tenantUser->update([
                    'tenant_id' => $tenant->id,
                    'email_verified_at' => $user->email_verified_at,
                    'google_id' => $user->google_id ?? null,
                    'role' => 'super_admin',
                    'is_owner' => true,
                    'tenant_join_date' => $tenantUser->tenant_join_date ?? now(),
                    'updated_at' => now(),
                ]);
            } else {
                TenantUser::create([
                    'global_id' => $user->global_id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'password' => $user->password,
                    'tenant_id' => $tenant->id,
                    'email_verified_at' => $user->email_verified_at,
                    'google_id' => $user->google_id ?? null,
                    'role' => 'super_admin',
                    'is_owner' => true,
                    'tenant_join_date' => now(),
                ]);
            }

            Log::info('SyncOAuthDataToTenant: Tenant user created/updated');
        });
    }

    // =====================================================
    // OPTIONAL: Nusawork Integration
    // Uncomment and customize if using Nusawork
    // =====================================================

    /*
    protected function dispatchNusaworkSync($user, $tenant): void
    {
        if (!empty($user->nusawork_id)) {
            \App\Jobs\SyncNusaworkMasterData::dispatch($user, $tenant->id);
            Log::info('SyncOAuthDataToTenant: Dispatched Nusawork sync job');
        }
    }
    */
}
