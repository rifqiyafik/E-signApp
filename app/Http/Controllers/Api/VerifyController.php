<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\UserCertificate;
use App\Services\DocumentPayloadService;
use App\Services\RootCaService;
use App\Services\TsaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class VerifyController extends Controller
{
    public function verify(Request $request, DocumentPayloadService $payloadService): JsonResponse
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:pdf'],
        ]);

        $file = $data['file'];
        $hash = hash_file('sha256', $file->getRealPath());

        $version = DocumentVersion::where('signed_pdf_sha256', $hash)
            ->orderByDesc('created_at')
            ->first();

        if (!$version) {
            return response()->json([
                'valid' => false,
                'reason' => 'hash_not_found',
                'signedPdfSha256' => $hash,
            ]);
        }

        $responseData = $this->buildVerificationResponse($version->document, $version, $payloadService);

        return response()->json($responseData);
    }

    public function show(Request $request, string $chainId, int $version, DocumentPayloadService $payloadService)
    {
        $document = Document::where('chain_id', $chainId)->firstOrFail();
        $versionModel = $document->versions()
            ->where('version_number', $version)
            ->firstOrFail();

        $responseData = $this->buildVerificationResponse($document, $versionModel, $payloadService);

        $acceptHeader = (string) $request->header('accept', '');
        if (stripos($acceptHeader, 'text/html') !== false) {
            return view('verify', [
                'payload' => $responseData,
                'chainId' => $chainId,
                'version' => $version,
            ]);
        }

        return response()->json($responseData);
    }

    public function verifyFileForVersion(
        Request $request,
        string $chainId,
        int $version,
        DocumentPayloadService $payloadService
    ) {
        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:pdf'],
        ]);

        $file = $data['file'];
        $hash = hash_file('sha256', $file->getRealPath());

        $document = Document::where('chain_id', $chainId)->firstOrFail();
        $versionModel = $document->versions()
            ->where('version_number', $version)
            ->firstOrFail();

        if ($hash !== $versionModel->signed_pdf_sha256) {
            $responseData = [
                'valid' => false,
                'reason' => 'hash_mismatch',
                'signedPdfSha256' => $hash,
                'expectedSignedPdfSha256' => $versionModel->signed_pdf_sha256,
            ];

            $acceptHeader = (string) $request->header('accept', '');
            if (stripos($acceptHeader, 'text/html') !== false) {
                return view('verify', [
                    'payload' => $responseData,
                    'chainId' => $chainId,
                    'version' => $version,
                ]);
            }

            return response()->json($responseData);
        }

        $responseData = $this->buildVerificationResponse($document, $versionModel, $payloadService);
        $acceptHeader = (string) $request->header('accept', '');
        if (stripos($acceptHeader, 'text/html') !== false) {
            return view('verify', [
                'payload' => $responseData,
                'chainId' => $chainId,
                'version' => $version,
            ]);
        }

        return response()->json($responseData);
    }

    private function buildVerificationResponse(Document $document, DocumentVersion $version, DocumentPayloadService $payloadService): array
    {
        $payload = $payloadService->build($document, $version, $version->tenant_id);
        $signatureValid = $this->verifyDetachedSignature($version);
        $certificateStatus = $this->checkCertificateStatus($version);
        $tsaStatus = $this->checkTsaStatus($version);
        $ltvStatus = $this->checkLtvStatus($version, $tsaStatus);

        return array_merge([
            'valid' => true,
            'signatureValid' => $signatureValid,
            'certificateStatus' => $certificateStatus['status'],
            'rootCaFingerprint' => $certificateStatus['rootCaFingerprint'],
            'certificateRevokedAt' => $certificateStatus['revokedAt'],
            'certificateRevokedReason' => $certificateStatus['revokedReason'],
            'tsaStatus' => $tsaStatus['status'],
            'tsaSignedAt' => $tsaStatus['signedAt'],
            'tsaFingerprint' => $tsaStatus['tsaFingerprint'],
            'tsaReason' => $tsaStatus['reason'],
            'ltvStatus' => $ltvStatus['status'],
            'ltvGeneratedAt' => $ltvStatus['generatedAt'],
            'ltvIssues' => $ltvStatus['issues'],
        ], $payload);
    }

    private function checkCertificateStatus(DocumentVersion $version): array
    {
        $certificate = UserCertificate::where('global_user_id', $version->user_id)->first();
        if (!$certificate) {
            return [
                'status' => 'missing',
                'rootCaFingerprint' => null,
                'revokedAt' => null,
                'revokedReason' => null,
            ];
        }

        $rootCa = app(RootCaService::class)->getRootCa();
        $rootCaFingerprint = $rootCa['fingerprint'] ?? null;
        $caCert = $rootCa['certificate'] ?? null;
        $revokedAt = optional($certificate->revoked_at)->toIso8601String();
        $revokedReason = $certificate->revoked_reason;

        if ($certificate->revoked_at) {
            return [
                'status' => 'revoked',
                'rootCaFingerprint' => $rootCaFingerprint,
                'revokedAt' => $revokedAt,
                'revokedReason' => $revokedReason,
            ];
        }

        $info = openssl_x509_parse($certificate->certificate) ?: [];
        $validFrom = $info['validFrom_time_t'] ?? null;
        $validTo = $info['validTo_time_t'] ?? null;
        $now = time();
        if ($validFrom && $now < $validFrom) {
            return [
                'status' => 'not_yet_valid',
                'rootCaFingerprint' => $rootCaFingerprint,
                'revokedAt' => $revokedAt,
                'revokedReason' => $revokedReason,
            ];
        }
        if ($validTo && $now > $validTo) {
            return [
                'status' => 'expired',
                'rootCaFingerprint' => $rootCaFingerprint,
                'revokedAt' => $revokedAt,
                'revokedReason' => $revokedReason,
            ];
        }

        if (!$caCert) {
            return [
                'status' => 'untrusted',
                'rootCaFingerprint' => $rootCaFingerprint,
                'revokedAt' => $revokedAt,
                'revokedReason' => $revokedReason,
            ];
        }

        $caKey = openssl_pkey_get_public($caCert);
        if ($caKey === false) {
            return [
                'status' => 'untrusted',
                'rootCaFingerprint' => $rootCaFingerprint,
                'revokedAt' => $revokedAt,
                'revokedReason' => $revokedReason,
            ];
        }

        $verifyResult = openssl_x509_verify($certificate->certificate, $caKey);
        if ($verifyResult !== 1) {
            return [
                'status' => 'untrusted',
                'rootCaFingerprint' => $rootCaFingerprint,
                'revokedAt' => $revokedAt,
                'revokedReason' => $revokedReason,
            ];
        }

        return [
            'status' => 'valid',
            'rootCaFingerprint' => $rootCaFingerprint,
            'revokedAt' => $revokedAt,
            'revokedReason' => $revokedReason,
        ];
    }

    private function verifyDetachedSignature(DocumentVersion $version): ?bool
    {
        if (!$version->signature_value || !$version->user_id) {
            return null;
        }

        $certificate = UserCertificate::where('global_user_id', $version->user_id)->first();

        if (!$certificate) {
            return null;
        }

        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
        $disk = Storage::disk($version->file_disk);
        if (!$disk->exists($version->file_path)) {
            return null;
        }

        $pdfBytes = $disk->get($version->file_path);
        $signatureRaw = base64_decode($version->signature_value, true);

        if ($pdfBytes === false || $signatureRaw === false) {
            return null;
        }

        $result = openssl_verify($pdfBytes, $signatureRaw, $certificate->public_key, OPENSSL_ALGO_SHA256);

        if ($result === 1) {
            return true;
        }

        if ($result === 0) {
            return false;
        }

        return null;
    }

    private function checkTsaStatus(DocumentVersion $version): array
    {
        if (!$version->tsa_token) {
            return [
                'status' => 'missing',
                'reason' => null,
                'signedAt' => optional($version->tsa_signed_at)->toIso8601String(),
                'tsaFingerprint' => null,
            ];
        }

        $token = json_decode($version->tsa_token, true);
        if (!is_array($token)) {
            return [
                'status' => 'invalid',
                'reason' => 'bad_token',
                'signedAt' => optional($version->tsa_signed_at)->toIso8601String(),
                'tsaFingerprint' => null,
            ];
        }

        $snapshotCert = data_get($version->ltv_snapshot, 'tsa.certificate');
        $verification = app(TsaService::class)->verifyToken($token, $version->signed_pdf_sha256, $snapshotCert);

        return [
            'status' => $verification['status'] ?? 'invalid',
            'reason' => $verification['reason'] ?? null,
            'signedAt' => $verification['signedAt'] ?? ($token['signedAt'] ?? optional($version->tsa_signed_at)->toIso8601String()),
            'tsaFingerprint' => $verification['tsaFingerprint'] ?? ($token['tsaFingerprint'] ?? null),
        ];
    }

    private function checkLtvStatus(DocumentVersion $version, array $tsaStatus): array
    {
        if (!$version->ltv_snapshot) {
            return [
                'status' => 'missing',
                'generatedAt' => null,
                'issues' => ['missing_snapshot'],
            ];
        }

        $issues = [];
        if (!data_get($version->ltv_snapshot, 'rootCa.certificate')) {
            $issues[] = 'root_ca_missing';
        }
        if (!data_get($version->ltv_snapshot, 'signer.certificate')) {
            $issues[] = 'signer_cert_missing';
        }
        if (!data_get($version->ltv_snapshot, 'tsa.token')) {
            $issues[] = 'tsa_token_missing';
        }
        if (($tsaStatus['status'] ?? null) !== 'valid') {
            $issues[] = 'tsa_invalid';
        }

        return [
            'status' => $issues ? 'incomplete' : 'ready',
            'generatedAt' => $version->ltv_snapshot['generatedAt'] ?? null,
            'issues' => $issues,
        ];
    }
}
