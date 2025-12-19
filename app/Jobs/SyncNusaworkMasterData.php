<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\Tenant;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use App\Services\Tenant\NusaworkIntegrationService;
use App\Traits\Loggable;

/**
 * Job: SyncNusaworkMasterData
 * 
 * Job untuk sync master data dari Nusawork ke database tenant.
 * Dijalankan setelah tenant dibuat atau secara berkala.
 */
class SyncNusaworkMasterData implements ShouldQueue
{
    use Queueable, Loggable;

    protected User $user;
    protected ?string $tenantId;

    /**
     * Create a new job instance.
     */
    public function __construct(User $user, ?string $tenantId = null)
    {
        $this->user = $user;
        $this->tenantId = $tenantId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->afterCommit();

        try {
            $this->user->load('tenantUsers');

            if (!$this->tenantId) {
                $this->syncAllTenants();
            } else {
                $this->syncSpecificTenant($this->tenantId);
            }
        } catch (\Throwable $e) {
            $this->logError('Failed to sync Nusawork data', [
                'user_id' => $this->user->id,
                'tenant_id' => $this->tenantId,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Sync Nusawork data for all tenants
     */
    private function syncAllTenants(): void
    {
        $tenantUsers = $this->user->tenantUsers()
            ->whereNotNull('nusawork_id')
            ->get();

        foreach ($tenantUsers as $tenantUser) {
            $this->syncTenantData($tenantUser->tenant_id, $tenantUser);
        }
    }

    /**
     * Sync Nusawork data for specific tenant
     */
    private function syncSpecificTenant(string $tenantId): void
    {
        $tenantUser = $this->user->tenantUsers()
            ->where('tenant_id', $tenantId)
            ->whereNotNull('nusawork_id')
            ->first();

        if ($tenantUser) {
            $this->syncTenantData($tenantId, $tenantUser);
        } else {
            $this->logInfo('No Nusawork integration found for tenant', [
                'user_id' => $this->user->id,
                'tenant_id' => $tenantId,
            ]);
        }
    }

    /**
     * Sync data for specific tenant
     */
    private function syncTenantData(string $tenantId, $tenantUserCentral): void
    {
        try {
            $nusaworkId = $tenantUserCentral->nusawork_id;
            if (empty($nusaworkId)) {
                return;
            }

            $domainUrl = $tenantUserCentral->getDomainUrl();
            if (empty($domainUrl)) {
                return;
            }

            $apiToken = $tenantUserCentral->getTokenApi();
            if (empty($apiToken)) {
                return;
            }

            $this->logInfo('Starting Nusawork sync for tenant', [
                'user_id' => $this->user->id,
                'tenant_id' => $tenantId,
            ]);

            $tenant = Tenant::find($tenantId);
            if (!$tenant) {
                return;
            }

            $userId = $this->user->id;
            $tenant->run(function () use ($domainUrl, $apiToken, $tenantId, $userId, $tenantUserCentral) {
                $service = new NusaworkIntegrationService($domainUrl, $apiToken);
                $service->syncMasterData();

                $this->logInfo('Nusawork sync completed for tenant', [
                    'user_id' => $userId,
                    'tenant_id' => $tenantId,
                ]);

                // Register integration jika belum
                if (!$tenantUserCentral->is_nusawork_integrated) {
                    $integrationService = new NusaworkIntegrationService($domainUrl, $tenantUserCentral->getTokenApi());
                    $statusIntegration = $integrationService->registerNusaworkIntegration($tenantUserCentral);

                    if ($statusIntegration) {
                        $tenantUser = \App\Models\Tenant\User::where('global_id', $tenantUserCentral->global_user_id)->first();
                        if ($tenantUser) {
                            $tenantUser->update([
                                'is_nusawork_integrated' => true,
                                'nusawork_integrated_at' => now(),
                            ]);
                        }
                    }
                }
            });
        } catch (\Throwable $e) {
            $this->logError('Failed to sync Nusawork data for tenant', [
                'user_id' => $this->user->id,
                'tenant_id' => $tenantId,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
