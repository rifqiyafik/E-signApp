<?php

namespace App\Services\Tenant;

use App\Traits\Loggable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Nusawork Integration Service
 * 
 * Service untuk integrasi dengan Nusawork API.
 * Handles fetching dan syncing master data dari Nusawork.
 * 
 * CUSTOMIZE: Sesuaikan model-model yang di-sync dengan kebutuhan project Anda.
 */
class NusaworkIntegrationService
{
    use Loggable;

    protected string $domainUrl;
    protected string $apiToken;

    public function __construct($domainUrl, $apiToken = null)
    {
        $this->domainUrl = $domainUrl;
        $this->apiToken = $apiToken ?? '';
    }

    /**
     * Fetch master data dari Nusawork
     */
    public function fetchMasterData(): ?array
    {
        $this->logInfo('Fetching master data from Nusawork...');

        $response = Http::get($this->domainUrl . config('services.nusawork.master_data_path', '/emp/api/master-data'), [
            'company_structure' => 1,
            'show_education' => 1,
        ]);

        if ($response->failed()) {
            $this->logError('Failed to fetch data from Nusawork.', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);
            throw new \Exception('Failed to fetch data from Nusawork.');
        }

        $this->logInfo('Successfully fetched data from Nusawork.');
        return $response->json('data');
    }

    /**
     * Sync master data dari Nusawork
     * 
     * CUSTOMIZE: Sesuaikan dengan model-model yang ada di project Anda.
     * Contoh ini menggunakan JobLevel, EducationLevel, JobPosition.
     */
    public function syncMasterData(): void
    {
        $masterData = $this->fetchMasterData();

        if (!$masterData) {
            $this->logWarning('No master data found to sync.');
            return;
        }

        DB::transaction(function () use ($masterData) {
            $this->logInfo('Starting master data synchronization.');

            // CUSTOMIZE: Sync data sesuai model Anda
            // Contoh:
            // if (isset($masterData['job_level'])) {
            //     $this->syncJobLevels($masterData['job_level']);
            // }
            // if (isset($masterData['education'])) {
            //     $this->syncEducationLevels($masterData['education']);
            // }
            // if (isset($masterData['job_position'])) {
            //     $this->syncJobPositions($masterData['job_position']);
            // }

            $this->logInfo('Master data synchronization completed.');
        });
    }

    /**
     * Register Nusawork integration untuk tenant user
     */
    public function registerNusaworkIntegration($tenantUserCentral): bool
    {
        $domainUrl = $this->domainUrl;
        $apiToken = $this->apiToken;

        if (!$domainUrl || !$apiToken) {
            return false;
        }

        $userIdNusawork = $tenantUserCentral->getUserIdNusawork();

        $response = Http::withToken($apiToken)->put($domainUrl . '/api/integrations/nusahire', [
            'url' => config('app.url'),
            'user_id' => $userIdNusawork,
            'tenant_id' => $tenantUserCentral->tenant_id,
            'global_id' => $tenantUserCentral->global_user_id,
            'token' => $apiToken,
        ]);

        if ($response->successful()) {
            $tenantUserCentral->update([
                'is_nusawork_integrated' => true,
                'nusawork_integrated_at' => now(),
            ]);

            return true;
        }

        return false;
    }

    /**
     * Fetch data menggunakan API token
     */
    public function fetchWithAuth(string $endpoint, array $params = []): ?array
    {
        $response = Http::withToken($this->apiToken)
            ->get($this->domainUrl . $endpoint, $params);

        if ($response->failed()) {
            $this->logError('Failed to fetch data from Nusawork.', [
                'endpoint' => $endpoint,
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            throw new \Exception('Failed to fetch data from Nusawork.');
        }

        return $response->json('data');
    }
}
