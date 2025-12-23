<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User as CentralUser;
use App\Models\UserCertificate;
use App\Services\RootCaService;
use App\Services\UserCertificateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PkiController extends Controller
{
    public function rootCa(RootCaService $rootCaService): JsonResponse
    {
        $rootCa = $rootCaService->getRootCa();

        return response()->json([
            'certificate' => $rootCa['certificate'],
            'fingerprint' => $rootCa['fingerprint'],
            'subject' => $rootCa['subject'],
            'validFrom' => optional($rootCa['validFrom'])->toIso8601String(),
            'validTo' => optional($rootCa['validTo'])->toIso8601String(),
        ]);
    }

    public function me(Request $request, UserCertificateService $certificateService): JsonResponse
    {
        $centralUser = $this->resolveCentralUser($request);
        $certificate = $certificateService->getForUser($centralUser);

        if (!$certificate) {
            return response()->json([
                'message' => 'Certificate not found.',
            ], 404);
        }

        return response()->json($this->formatCertificateResponse($certificate));
    }

    public function enroll(Request $request, UserCertificateService $certificateService): JsonResponse
    {
        $centralUser = $this->resolveCentralUser($request);
        $certificate = $certificateService->ensureForUser($centralUser);

        return response()->json($this->formatCertificateResponse($certificate));
    }

    public function renew(Request $request, UserCertificateService $certificateService): JsonResponse
    {
        $centralUser = $this->resolveCentralUser($request);
        $certificate = $certificateService->renewForUser($centralUser);

        return response()->json($this->formatCertificateResponse($certificate));
    }

    public function revoke(Request $request, UserCertificateService $certificateService): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $centralUser = $this->resolveCentralUser($request);
        $certificate = $certificateService->revokeForUser($centralUser, $data['reason'] ?? null);

        return response()->json($this->formatCertificateResponse($certificate));
    }

    private function resolveCentralUser(Request $request): CentralUser
    {
        $tenantUser = $request->user();

        if (!$tenantUser || !isset($tenantUser->global_id)) {
            abort(401, 'User not authenticated.');
        }

        $centralUser = CentralUser::where('global_id', $tenantUser->global_id)->first();

        if (!$centralUser) {
            abort(404, 'Central user not found.');
        }

        return $centralUser;
    }

    private function formatCertificateResponse(UserCertificate $certificate): array
    {
        return [
            'certificatePem' => $certificate->certificate,
            'fingerprint' => $certificate->certificate_fingerprint,
            'serial' => $certificate->certificate_serial,
            'subject' => $certificate->certificate_subject,
            'issuer' => $certificate->certificate_issuer,
            'validFrom' => optional($certificate->valid_from)->toIso8601String(),
            'validTo' => optional($certificate->valid_to)->toIso8601String(),
            'revokedAt' => optional($certificate->revoked_at)->toIso8601String(),
            'revokedReason' => $certificate->revoked_reason,
        ];
    }
}
