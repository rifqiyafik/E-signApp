<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\TenantUser;
use App\Services\Tenant\NusaworkIntegrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Nusawork Controller
 * 
 * Controller untuk integrasi Nusawork dalam konteks tenant.
 */
class NusaworkController extends Controller
{
    /**
     * Mengambil master data dari Nusawork
     */
    public function getMasterData(): JsonResponse
    {
        try {
            $user = Auth::user();

            $tenantUser = TenantUser::where('global_user_id', $user->global_id)
                ->where('tenant_id', tenant('id'))
                ->first();

            if (!$tenantUser || !$tenantUser->nusawork_id) {
                return response()->json([
                    'status' => 'error',
                    'message' => __('Nusawork integration not found for your account.'),
                ], 400);
            }

            $integrationService = new NusaworkIntegrationService(
                $tenantUser->getDomainUrl(),
                $tenantUser->getTokenApi()
            );

            $masterData = $integrationService->fetchMasterData();

            return response()->json([
                'status' => 'success',
                'message' => __('Master data retrieved successfully.'),
                'data' => $masterData,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Sync master data dari Nusawork
     */
    public function syncMasterData(): JsonResponse
    {
        try {
            $user = Auth::user();

            $tenantUser = TenantUser::where('global_user_id', $user->global_id)
                ->where('tenant_id', tenant('id'))
                ->first();

            if (!$tenantUser || !$tenantUser->nusawork_id) {
                return response()->json([
                    'status' => 'error',
                    'message' => __('Nusawork integration not found for your account.'),
                ], 400);
            }

            $integrationService = new NusaworkIntegrationService(
                $tenantUser->getDomainUrl(),
                $tenantUser->getTokenApi()
            );

            $integrationService->syncMasterData();

            return response()->json([
                'status' => 'success',
                'message' => __('Master data synced successfully.'),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get integration status
     */
    public function getStatus(): JsonResponse
    {
        $user = Auth::user();

        $tenantUser = TenantUser::where('global_user_id', $user->global_id)
            ->where('tenant_id', tenant('id'))
            ->first();

        return response()->json([
            'status' => 'success',
            'data' => [
                'is_integrated' => $tenantUser?->isNusaworkIntegrated() ?? false,
                'integrated_at' => $tenantUser?->nusawork_integrated_at,
                'domain_url' => $tenantUser?->getDomainUrl(),
            ],
        ]);
    }
}
