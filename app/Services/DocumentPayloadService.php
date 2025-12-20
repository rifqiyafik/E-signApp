<?php

namespace App\Services;

use App\Models\Document;
use App\Models\DocumentVersion;

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

        $signers = $document->signers()
            ->orderBy('signer_index')
            ->get()
            ->map(function ($signer) {
                return [
                    'index' => $signer->signer_index,
                    'tenantId' => $signer->tenant_id,
                    'userId' => $signer->user_id,
                    'signedAt' => optional($signer->signed_at)->toIso8601String(),
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
            'signers' => $signers,
        ];
    }
}
