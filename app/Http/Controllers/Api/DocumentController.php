<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\DocumentSigner;
use App\Models\DocumentVersion;
use App\Models\User as CentralUser;
use App\Services\DocumentPayloadService;
use App\Services\DocumentStampService;
use App\Services\UserCertificateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    public function sign(
        Request $request,
        DocumentPayloadService $payloadService,
        DocumentStampService $stampService,
        UserCertificateService $certificateService
    ): JsonResponse
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:pdf'],
            'consent' => ['required', 'accepted'],
            'idempotencyKey' => ['nullable', 'string', 'max:255'],
        ]);

        $file = $request->file('file');
        $tenantId = tenant('id');
        $user = $request->user();
        $inputHash = hash_file('sha256', $file->getRealPath());
        $idempotencyKey = $data['idempotencyKey'] ?? $request->header('Idempotency-Key');
        $idempotencyKey = is_string($idempotencyKey) ? trim($idempotencyKey) : null;
        if ($idempotencyKey === '') {
            $idempotencyKey = null;
        }

        if ($idempotencyKey) {
            $existingVersion = DocumentVersion::where('tenant_id', $tenantId)
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existingVersion) {
                return response()->json(
                    $payloadService->build($existingVersion->document, $existingVersion, $tenantId)
                );
            }
        }

        $connection = Document::query()->getConnection();

        $payload = $connection->transaction(function () use ($file, $inputHash, $tenantId, $user, $idempotencyKey, $payloadService, $stampService, $certificateService) {
            $existingVersion = DocumentVersion::where('signed_pdf_sha256', $inputHash)
                ->orderByDesc('created_at')
                ->first();

            if ($existingVersion) {
                $document = $existingVersion->document;
            } else {
                $document = Document::create([
                    'chain_id' => (string) Str::ulid(),
                    'original_filename' => $file->getClientOriginalName(),
                    'mime_type' => $file->getClientMimeType() ?? 'application/pdf',
                    'created_by_tenant_id' => $tenantId,
                    'created_by_user_id' => $user->global_id,
                ]);
            }

            $centralUser = CentralUser::where('global_id', $user->global_id)->first();

            if (!$centralUser) {
                abort(500, 'Central user not found.');
            }

            $signingCredentials = $certificateService->getSigningCredentials($centralUser);

            $nextVersionNumber = ($document->versions()->max('version_number') ?? 0) + 1;
            $signerIndex = ($document->signers()->max('signer_index') ?? 0) + 1;
            $verificationUrl = url("/{$tenantId}/api/verify/{$document->chain_id}/v{$nextVersionNumber}");
            $fileName = 'v' . $nextVersionNumber . '.pdf';
            $relativePath = 'documents/' . $document->id . '/' . $fileName;

            /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
            $disk = Storage::disk('central');
            $disk->makeDirectory('documents/' . $document->id);
            $absolutePath = $disk->path($relativePath);

            $stampService->stamp(
                $file->getRealPath(),
                $absolutePath,
                $verificationUrl,
                [
                    'signed_by' => $user->name,
                    'signed_at' => now()->toIso8601String(),
                    'signer_index' => $signerIndex,
                ],
                [
                    'certificate' => $signingCredentials['certificate'] ?? null,
                    'privateKey' => $signingCredentials['privateKey'] ?? null,
                    'privateKeyPassphrase' => $signingCredentials['privateKeyPassphrase'] ?? '',
                    'info' => [
                        'Name' => $user->name,
                        'Location' => (string) $tenantId,
                        'Reason' => 'Document signing',
                        'ContactInfo' => $user->email,
                    ],
                ]
            );

            if (!file_exists($absolutePath)) {
                abort(500, 'Failed to store signed PDF.');
            }

            $outputHash = hash_file('sha256', $absolutePath);
            $outputSize = filesize($absolutePath) ?: null;
            $signatureValue = null;
            $signatureAlgorithm = $signingCredentials['signatureAlgorithm'] ?? null;
            $signingFingerprint = $signingCredentials['certificateFingerprint'] ?? null;
            $signingSubject = $signingCredentials['certificateSubject'] ?? null;
            $signingSerial = $signingCredentials['certificateSerial'] ?? null;

            $pdfBytes = file_get_contents($absolutePath);
            $privateKey = $signingCredentials['privateKey'] ?? null;

            if ($pdfBytes === false || !$privateKey) {
                abort(500, 'Failed to read signed PDF.');
            }

            $privateKeyResource = openssl_pkey_get_private(
                $privateKey,
                $signingCredentials['privateKeyPassphrase'] ?? ''
            );

            if ($privateKeyResource === false) {
                abort(500, 'Failed to load signing key.');
            }

            if (!openssl_sign($pdfBytes, $signatureRaw, $privateKeyResource, OPENSSL_ALGO_SHA256)) {
                abort(500, 'Failed to sign PDF.');
            }

            $signatureValue = base64_encode($signatureRaw);

            $version = DocumentVersion::create([
                'document_id' => $document->id,
                'version_number' => $nextVersionNumber,
                'file_disk' => 'central',
                'file_path' => $relativePath,
                'signed_pdf_sha256' => $outputHash,
                'signed_pdf_size' => $outputSize,
                'signature_algorithm' => $signatureAlgorithm,
                'signature_value' => $signatureValue,
                'signing_cert_fingerprint' => $signingFingerprint,
                'signing_cert_subject' => $signingSubject,
                'signing_cert_serial' => $signingSerial,
                'verification_url' => $verificationUrl,
                'idempotency_key' => $idempotencyKey,
                'tenant_id' => $tenantId,
                'user_id' => $user->global_id,
                'signed_at' => now(),
            ]);

            DocumentSigner::create([
                'document_id' => $document->id,
                'version_id' => $version->id,
                'signer_index' => $signerIndex,
                'tenant_id' => $tenantId,
                'user_id' => $user->global_id,
                'signed_at' => $version->signed_at,
            ]);

            return $payloadService->build($document, $version, $tenantId);
        });

        return response()->json($payload);
    }

    public function show(Document $document, DocumentPayloadService $payloadService): JsonResponse
    {
        $document->load('latestVersion', 'signers');
        $latestVersion = $document->latestVersion;

        if (!$latestVersion) {
            abort(404);
        }

        $payload = $payloadService->build($document, $latestVersion, tenant('id'));

        return response()->json([
            'documentId' => $payload['documentId'],
            'chainId' => $payload['chainId'],
            'latestVersion' => [
                'versionNumber' => $payload['versionNumber'],
                'signedPdfDownloadUrl' => $payload['signedPdfDownloadUrl'],
                'signedPdfSha256' => $payload['signedPdfSha256'],
                'signedAt' => optional($latestVersion->signed_at)->toIso8601String(),
            ],
            'signers' => $payload['signers'],
        ]);
    }

    public function versions(Document $document): JsonResponse
    {
        $tenantId = tenant('id');

        $versions = $document->versions()
            ->orderBy('version_number')
            ->get()
            ->map(function (DocumentVersion $version) use ($document, $tenantId) {
                return [
                    'versionNumber' => $version->version_number,
                    'signedPdfSha256' => $version->signed_pdf_sha256,
                    'signedPdfDownloadUrl' => url("/{$tenantId}/api/documents/{$document->id}/versions/v{$version->version_number}:download"),
                    'signedAt' => optional($version->signed_at)->toIso8601String(),
                ];
            })
            ->values()
            ->all();

        return response()->json([
            'documentId' => $document->id,
            'chainId' => $document->chain_id,
            'versions' => $versions,
        ]);
    }

    public function downloadLatest(Document $document): StreamedResponse
    {
        $version = $document->latestVersion;

        if (!$version) {
            abort(404);
        }

        return $this->downloadVersionFile($document, $version);
    }

    public function downloadVersion(Document $document, int $version): StreamedResponse
    {
        $versionModel = $document->versions()
            ->where('version_number', $version)
            ->firstOrFail();

        return $this->downloadVersionFile($document, $versionModel);
    }

    private function downloadVersionFile(Document $document, DocumentVersion $version): StreamedResponse
    {
        $fileName = $document->id . '-v' . $version->version_number . '.pdf';

        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
        $disk = Storage::disk($version->file_disk);
        if (! $disk->exists($version->file_path)) {
            abort(404, 'Signed PDF not found.');
        }

        return $disk->download($version->file_path, $fileName);
    }
}
