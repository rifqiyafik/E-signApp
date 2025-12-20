<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\UserCertificate;
use App\Services\DocumentPayloadService;
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

        $payload = $payloadService->build($version->document, $version, $version->tenant_id);
        $signatureValid = $this->verifyDetachedSignature($version);

        return response()->json(array_merge([
            'valid' => true,
            'signatureValid' => $signatureValid,
        ], $payload));
    }

    public function show(string $chainId, int $version, DocumentPayloadService $payloadService): JsonResponse
    {
        $document = Document::where('chain_id', $chainId)->firstOrFail();
        $versionModel = $document->versions()
            ->where('version_number', $version)
            ->firstOrFail();

        $payload = $payloadService->build($document, $versionModel, $versionModel->tenant_id);
        $signatureValid = $this->verifyDetachedSignature($versionModel);

        return response()->json(array_merge([
            'valid' => true,
            'signatureValid' => $signatureValid,
        ], $payload));
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
}
