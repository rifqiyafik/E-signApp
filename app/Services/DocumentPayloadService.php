<?php

namespace App\Services;

use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\TenantUser as CentralTenantUser;
use App\Models\User as CentralUser;
use App\Models\UserCertificate;

class DocumentPayloadService
{
    public function build(Document $document, DocumentVersion $version, ?string $tenantId = null): array
    {
        $tenantId = $tenantId ?? (function_exists('tenant') ? tenant('id') : null);
        $downloadUrl = $tenantId
            ? url("/{$tenantId}/api/documents/{$document->id}/versions/v{$version->version_number}:download")
            : null;

        $verificationUrl = $version->verification_url ?? ($tenantId
            ? url("/{$tenantId}/api/verify/{$document->chain_id}/v{$version->version_number}")
            : null);

        $signerRows = $document->signers()
            ->orderBy('signer_index')
            ->get();

        $userIds = $signerRows->pluck('user_id')->filter()->unique()->values();
        $tenantIds = $signerRows->pluck('tenant_id')->filter()->unique()->values();
        $userMap = CentralUser::whereIn('global_id', $userIds)
            ->get()
            ->keyBy('global_id');
        $membershipMap = CentralTenantUser::whereIn('tenant_id', $tenantIds)
            ->whereIn('global_user_id', $userIds)
            ->get()
            ->keyBy(function ($membership) {
                return $membership->tenant_id . '|' . $membership->global_user_id;
            });
        $certificateMap = UserCertificate::whereIn('global_user_id', $userIds)
            ->get()
            ->keyBy('global_user_id');

        $versionCertificate = $certificateMap->get($version->user_id);
        $signatureMeta = [
            'algorithm' => $version->signature_algorithm ?? $versionCertificate?->signature_algorithm,
            'certificateFingerprint' => $version->signing_cert_fingerprint ?? $versionCertificate?->certificate_fingerprint,
            'certificateSubject' => $version->signing_cert_subject ?? $versionCertificate?->certificate_subject,
            'certificateSerial' => $version->signing_cert_serial ?? $versionCertificate?->certificate_serial,
        ];

        $tsaToken = null;
        if (is_string($version->tsa_token) && $version->tsa_token !== '') {
            $decoded = json_decode($version->tsa_token, true);
            if (is_array($decoded)) {
                $tsaToken = $decoded;
            }
        }
        $tsaPayload = $tsaToken ? [
            'signedAt' => $tsaToken['signedAt'] ?? optional($version->tsa_signed_at)->toIso8601String(),
            'fingerprint' => $tsaToken['tsaFingerprint'] ?? null,
            'algorithm' => $tsaToken['algorithm'] ?? null,
        ] : null;
        $ltvSnapshot = $version->ltv_snapshot ?: null;
        $ltvPayload = $ltvSnapshot ? [
            'enabled' => true,
            'generatedAt' => $ltvSnapshot['generatedAt'] ?? null,
            'rootCaFingerprint' => data_get($ltvSnapshot, 'rootCa.fingerprint'),
            'tsaFingerprint' => data_get($ltvSnapshot, 'tsa.fingerprint'),
        ] : [
            'enabled' => false,
        ];

        $signers = $signerRows
            ->map(function ($signer) use ($userMap, $membershipMap, $certificateMap) {
                $user = $userMap->get($signer->user_id);
                $membership = $membershipMap->get($signer->tenant_id . '|' . $signer->user_id);
                $certificate = $certificateMap->get($signer->user_id);

                return [
                    'index' => $signer->signer_index,
                    'tenantId' => $signer->tenant_id,
                    'userId' => $signer->user_id,
                    'name' => $user?->name,
                    'email' => $user?->email,
                    'role' => $membership?->role,
                    'signedAt' => optional($signer->signed_at)->toIso8601String(),
                    'certificate' => [
                        'serial' => $certificate?->certificate_serial,
                        'issuedBy' => $certificate?->certificate_issuer,
                        'validFrom' => optional($certificate?->valid_from)->toDateString(),
                        'validTo' => optional($certificate?->valid_to)->toDateString(),
                    ],
                ];
            })
            ->values()
            ->all();

        return [
            'documentId' => $document->id,
            'chainId' => $document->chain_id,
            'versionNumber' => $version->version_number,
            'verificationUrl' => $verificationUrl,
            'signedPdfDownloadUrl' => $downloadUrl,
            'signedPdfSha256' => $version->signed_pdf_sha256,
            'signature' => $signatureMeta,
            'tsa' => $tsaPayload,
            'ltv' => $ltvPayload,
            'signers' => $signers,
        ];
    }
}
