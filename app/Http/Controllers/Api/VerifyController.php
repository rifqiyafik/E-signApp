<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Services\DocumentPayloadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

        return response()->json(array_merge([
            'valid' => true,
        ], $payload));
    }

    public function show(string $chainId, int $version, DocumentPayloadService $payloadService): JsonResponse
    {
        $document = Document::where('chain_id', $chainId)->firstOrFail();
        $versionModel = $document->versions()
            ->where('version_number', $version)
            ->firstOrFail();

        $payload = $payloadService->build($document, $versionModel, $versionModel->tenant_id);

        return response()->json(array_merge([
            'valid' => true,
        ], $payload));
    }
}
