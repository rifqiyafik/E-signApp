<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\DocumentSigner;
use App\Models\DocumentVersion;
use App\Models\TenantUser as CentralTenantUser;
use App\Models\Tenant\User as TenantUser;
use App\Models\User as CentralUser;
use App\Services\AuditLogService;
use App\Services\DocumentPayloadService;
use App\Services\DocumentStampService;
use App\Services\NotificationService;
use App\Services\RootCaService;
use App\Services\TsaService;
use App\Services\UserCertificateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DocumentWorkflowController extends Controller
{
    public function uploadDraft(
        Request $request,
        AuditLogService $auditLogService
    ): JsonResponse {
        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:pdf'],
        ], [
            'file.required' => 'File PDF wajib diunggah.',
            'file.file' => 'File harus berupa upload yang valid.',
            'file.mimes' => 'File harus berformat PDF.',
        ], [
            'file' => 'file PDF',
        ]);

        $tenantId = tenant('id');
        $user = $request->user();
        $file = $request->file('file');

        $document = Document::create([
            'chain_id' => (string) Str::ulid(),
            'original_filename' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType() ?? 'application/pdf',
            'created_by_tenant_id' => $tenantId,
            'created_by_user_id' => $user->global_id,
            'status' => Document::STATUS_DRAFT,
            'status_updated_at' => now(),
        ]);

        $draftHash = hash_file('sha256', $file->getRealPath());
        $draftSize = $file->getSize();

        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
        $disk = Storage::disk('central');
        $disk->makeDirectory('documents/' . $document->id);
        $relativePath = 'documents/' . $document->id . '/draft.pdf';
        $disk->putFileAs('documents/' . $document->id, $file, 'draft.pdf');

        $document->forceFill([
            'draft_file_disk' => 'central',
            'draft_file_path' => $relativePath,
            'draft_sha256' => $draftHash,
            'draft_uploaded_at' => now(),
        ])->save();

        $draftVersion = DocumentVersion::create([
            'document_id' => $document->id,
            'version_number' => 0,
            'file_disk' => 'central',
            'file_path' => $relativePath,
            'signed_pdf_sha256' => $draftHash,
            'signed_pdf_size' => $draftSize,
            'tenant_id' => $tenantId,
            'user_id' => $user->global_id,
        ]);

        $auditLogService->log(
            $request,
            'document_draft_created',
            $tenantId,
            $user->global_id,
            Document::class,
            $document->id,
            [
                'chainId' => $document->chain_id,
                'draftVersionId' => $draftVersion->id,
            ]
        );

        return response()->json([
            'document' => [
                'documentId' => $document->id,
                'chainId' => $document->chain_id,
                'status' => $document->status,
                'draftSha256' => $draftHash,
                'draftUploadedAt' => optional($document->draft_uploaded_at)->toIso8601String(),
                'originalFilename' => $document->original_filename,
            ],
        ], 201);
    }

    public function assignSigners(
        Request $request,
        Document $document,
        NotificationService $notificationService,
        AuditLogService $auditLogService
    ): JsonResponse {
        $data = $request->validate([
            'signers' => ['required', 'array', 'min:1'],
            'signers.*.user' => ['required', 'string'],
            'signers.*.role' => ['nullable', 'string', 'max:50'],
            'expiresAt' => ['nullable', 'date'],
        ], [
            'signers.required' => 'Signer wajib diisi.',
            'signers.array' => 'Signer harus berupa array.',
            'signers.min' => 'Minimal satu signer diperlukan.',
            'signers.*.user.required' => 'User signer wajib diisi.',
            'signers.*.user.string' => 'User signer harus berupa teks.',
            'signers.*.role.string' => 'Role signer harus berupa teks.',
            'signers.*.role.max' => 'Role signer maksimal 50 karakter.',
            'expiresAt.date' => 'Tanggal kadaluarsa tidak valid.',
        ], [
            'signers' => 'signers',
            'signers.*.user' => 'user signer',
            'signers.*.role' => 'role signer',
            'expiresAt' => 'tanggal kadaluarsa',
        ]);

        $tenantId = tenant('id');
        $user = $request->user();

        $this->ensureDocumentTenant($document, $tenantId);

        if ($document->status !== Document::STATUS_DRAFT) {
            return response()->json([
                'message' => 'Dokumen bukan draft.',
                'code' => 'document_not_draft',
            ], 409);
        }

        if ($document->signers()->exists()) {
            return response()->json([
                'message' => 'Signer sudah ditentukan.',
                'code' => 'signers_already_set',
            ], 409);
        }

        $draftVersion = $document->versions()->where('version_number', 0)->first();
        if (!$draftVersion) {
            return response()->json([
                'message' => 'Draft belum tersedia.',
                'code' => 'draft_missing',
            ], 409);
        }

        $errors = [];
        $signerUsers = [];
        foreach ($data['signers'] as $index => $signerData) {
            $value = trim($signerData['user']);
            $centralUser = CentralUser::where('global_id', $value)
                ->orWhere('email', $value)
                ->first();

            if (!$centralUser) {
                $errors["signers.$index.user"] = ['User signer tidak ditemukan.'];
                continue;
            }

            $signerUsers[] = [
                'user' => $centralUser,
                'role' => $signerData['role'] ?? null,
            ];
        }

        $uniqueIds = collect($signerUsers)->pluck('user.global_id')->unique();
        if ($uniqueIds->count() !== count($signerUsers)) {
            $errors['signers'] = ['Signer tidak boleh duplikat.'];
        }

        if ($errors) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'code' => 'validation_failed',
                'errors' => $errors,
            ], 422);
        }

        $now = now();
        $firstSignerId = null;

        foreach ($signerUsers as $index => $signer) {
            $signerIndex = $index + 1;
            $centralUser = $signer['user'];
            $role = $signer['role'];

            $this->ensureTenantMembership($tenantId, $centralUser, $role);

            $status = $signerIndex === 1 ? 'active' : 'queued';
            if ($signerIndex === 1) {
                $firstSignerId = $centralUser->global_id;
            }

            DocumentSigner::create([
                'document_id' => $document->id,
                'version_id' => $draftVersion->id,
                'signer_index' => $signerIndex,
                'tenant_id' => $tenantId,
                'user_id' => $centralUser->global_id,
                'status' => $status,
                'assigned_by_user_id' => $user->global_id,
                'assigned_at' => $now,
            ]);
        }

        $document->forceFill([
            'status' => Document::STATUS_NEED_SIGNATURE,
            'current_signer_index' => 1,
            'expires_at' => isset($data['expiresAt']) ? Carbon::parse($data['expiresAt']) : null,
            'status_updated_at' => $now,
        ])->save();

        if ($firstSignerId) {
            $notificationService->notify($tenantId, $firstSignerId, 'signature_requested', [
                'documentId' => $document->id,
                'chainId' => $document->chain_id,
                'signerIndex' => 1,
            ]);
        }

        $auditLogService->log(
            $request,
            'document_signers_assigned',
            $tenantId,
            $user->global_id,
            Document::class,
            $document->id,
            [
                'signerCount' => count($signerUsers),
            ]
        );

        return response()->json([
            'document' => [
                'documentId' => $document->id,
                'chainId' => $document->chain_id,
                'status' => $document->status,
                'currentSignerIndex' => $document->current_signer_index,
                'expiresAt' => optional($document->expires_at)->toIso8601String(),
            ],
        ]);
    }

    public function sign(
        Request $request,
        Document $document,
        DocumentPayloadService $payloadService,
        DocumentStampService $stampService,
        UserCertificateService $certificateService,
        RootCaService $rootCaService,
        TsaService $tsaService,
        NotificationService $notificationService,
        AuditLogService $auditLogService
    ): JsonResponse {
        $data = $request->validate([
            'consent' => ['required', 'accepted'],
            'idempotencyKey' => ['nullable', 'string', 'max:255'],
        ], [
            'consent.required' => 'Persetujuan wajib diisi.',
            'consent.accepted' => 'Persetujuan harus disetujui.',
            'idempotencyKey.string' => 'Idempotency key harus berupa teks.',
            'idempotencyKey.max' => 'Idempotency key maksimal 255 karakter.',
        ], [
            'consent' => 'persetujuan',
            'idempotencyKey' => 'idempotency key',
        ]);

        $tenantId = tenant('id');
        $user = $request->user();

        $this->ensureDocumentTenant($document, $tenantId);

        if (in_array($document->status, [Document::STATUS_CANCELED, Document::STATUS_COMPLETED, Document::STATUS_EXPIRED], true)) {
            return response()->json([
                'message' => 'Dokumen tidak dapat ditandatangani.',
                'code' => 'document_not_signable',
            ], 409);
        }

        if ($document->expires_at && now()->greaterThan($document->expires_at)) {
            $document->forceFill([
                'status' => Document::STATUS_EXPIRED,
                'status_updated_at' => now(),
            ])->save();

            return response()->json([
                'message' => 'Dokumen sudah kadaluarsa.',
                'code' => 'document_expired',
            ], 410);
        }

        $currentSignerIndex = $document->current_signer_index;
        $currentSigner = $currentSignerIndex
            ? $document->signers()->where('signer_index', $currentSignerIndex)->first()
            : null;

        if (!$currentSigner) {
            $currentSigner = $document->signers()
                ->where('status', 'active')
                ->orderBy('signer_index')
                ->first();
        }

        if (!$currentSigner) {
            return response()->json([
                'message' => 'Tidak ada signer aktif.',
                'code' => 'signer_not_ready',
            ], 409);
        }

        if ($currentSigner->user_id !== $user->global_id) {
            return response()->json([
                'message' => 'Anda belum berada pada giliran tanda tangan.',
                'code' => 'signer_not_allowed',
            ], 403);
        }

        $idempotencyKey = $data['idempotencyKey'] ?? $request->header('Idempotency-Key');
        $idempotencyKey = is_string($idempotencyKey) ? trim($idempotencyKey) : null;
        if ($idempotencyKey === '') {
            $idempotencyKey = null;
        }

        if ($idempotencyKey) {
            $existingVersion = DocumentVersion::where('tenant_id', $tenantId)
                ->where('document_id', $document->id)
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existingVersion) {
                return response()->json(
                    $payloadService->build($document, $existingVersion, $tenantId)
                );
            }
        }

        $centralUser = CentralUser::where('global_id', $user->global_id)->first();
        if (!$centralUser) {
            abort(500, 'Central user not found.');
        }

        $signingCredentials = $certificateService->getSigningCredentials($centralUser);
        $nextVersionNumber = ($document->versions()->max('version_number') ?? 0) + 1;
        $verificationUrl = url("/{$tenantId}/api/verify/{$document->chain_id}/v{$nextVersionNumber}");
        $fileName = 'v' . $nextVersionNumber . '.pdf';
        $relativePath = 'documents/' . $document->id . '/' . $fileName;

        $sourceVersion = $document->latestSignedVersion;
        $sourcePath = $sourceVersion?->file_path ?? $document->draft_file_path;
        $sourceDisk = $sourceVersion?->file_disk ?? $document->draft_file_disk;

        if (!$sourcePath || !$sourceDisk) {
            return response()->json([
                'message' => 'Draft belum tersedia.',
                'code' => 'draft_missing',
            ], 409);
        }

        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
        $disk = Storage::disk('central');
        $disk->makeDirectory('documents/' . $document->id);
        $absolutePath = $disk->path($relativePath);
        $inputPath = $disk->path($sourcePath);

        // Calculate source file hash for QR embedding
        $sourceHash = hash_file('sha256', $inputPath);

        $stampService->stamp(
            $inputPath,
            $absolutePath,
            $verificationUrl,
            [
                'signed_by' => $user->name,
                'signed_at' => now()->toIso8601String(),
                'signer_index' => $currentSigner->signer_index,
                'document_hash' => substr($sourceHash, 0, 16), // First 16 chars for compact QR
            ],
            [
                'certificate' => $signingCredentials['certificate'] ?? null,
                'privateKey' => $signingCredentials['privateKey'] ?? null,
                'privateKeyPassphrase' => $signingCredentials['privateKeyPassphrase'] ?? '',
                'caCertificate' => $signingCredentials['caCertificate'] ?? null,
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
        $signatureAlgorithm = $signingCredentials['signatureAlgorithm'] ?? null;
        $signingFingerprint = $signingCredentials['certificateFingerprint'] ?? null;
        $signingSubject = $signingCredentials['certificateSubject'] ?? null;
        $signingSerial = $signingCredentials['certificateSerial'] ?? null;

        $rootCa = $rootCaService->getRootCa();
        $tsaToken = $tsaService->issue($outputHash);
        $tsaSignedAt = isset($tsaToken['signedAt']) ? Carbon::parse($tsaToken['signedAt']) : null;
        $tsaTokenJson = json_encode($tsaToken, JSON_UNESCAPED_SLASHES);
        if ($tsaTokenJson === false) {
            $tsaTokenJson = null;
        }
        $tsaInfo = $tsaService->getTsa();
        $ltvSnapshot = [
            'generatedAt' => now()->toIso8601String(),
            'rootCa' => [
                'fingerprint' => $rootCa['fingerprint'] ?? null,
                'certificate' => $rootCa['certificate'] ?? null,
            ],
            'signer' => [
                'certificate' => $signingCredentials['certificate'] ?? null,
                'fingerprint' => $signingFingerprint,
                'subject' => $signingSubject,
                'serial' => $signingSerial,
            ],
            'tsa' => [
                'token' => $tsaToken,
                'certificate' => $tsaInfo['certificate'] ?? null,
                'fingerprint' => $tsaInfo['fingerprint'] ?? null,
            ],
        ];

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
            'tsa_signed_at' => $tsaSignedAt,
            'tsa_token' => $tsaTokenJson,
            'ltv_snapshot' => $ltvSnapshot,
        ]);

        $currentSigner->forceFill([
            'status' => 'signed',
            'signed_at' => $version->signed_at,
            'version_id' => $version->id,
        ])->save();

        $nextSigner = $document->signers()
            ->where('signer_index', '>', $currentSigner->signer_index)
            ->orderBy('signer_index')
            ->first();

        $newStatus = Document::STATUS_COMPLETED;
        $currentSignerIndex = null;

        if ($nextSigner) {
            $nextSigner->forceFill([
                'status' => 'active',
            ])->save();
            $newStatus = Document::STATUS_WAITING;
            $currentSignerIndex = $nextSigner->signer_index;

            $notificationService->notify($tenantId, $nextSigner->user_id, 'signature_requested', [
                'documentId' => $document->id,
                'chainId' => $document->chain_id,
                'signerIndex' => $nextSigner->signer_index,
            ]);
        }

        $document->forceFill([
            'status' => $newStatus,
            'current_signer_index' => $currentSignerIndex,
            'status_updated_at' => now(),
        ])->save();

        $auditLogService->log(
            $request,
            'document_signed',
            $tenantId,
            $user->global_id,
            Document::class,
            $document->id,
            [
                'versionId' => $version->id,
                'versionNumber' => $version->version_number,
            ]
        );

        if ($newStatus === Document::STATUS_COMPLETED) {
            $auditLogService->log(
                $request,
                'document_completed',
                $tenantId,
                $user->global_id,
                Document::class,
                $document->id
            );
        }

        return response()->json(
            $payloadService->build($document, $version, $tenantId)
        );
    }

    public function adminDocumentList(Request $request): JsonResponse
    {
        $tenantId = tenant('id');
        $documents = Document::where('created_by_tenant_id', $tenantId)
            ->with(['signers.user'])
            ->latest()
            ->get()
            ->map(function ($doc) {
                return [
                    'id' => $doc->id,
                    'documentId' => $doc->id, // alias for consistency
                    'title' => $doc->original_filename,
                    'status' => $doc->status,
                    'created_at' => $doc->created_at->toIso8601String(),
                    'signers' => $doc->signers->sortBy('signer_index')->map(function ($s) {
                        return [
                            'id' => $s->id,
                            'status' => $s->status,
                            'signer_order' => $s->signer_index,
                            'email' => $s->user->email ?? 'unknown',
                            'user' => [
                                'name' => $s->user->name ?? 'Unknown',
                                'email' => $s->user->email ?? 'unknown',
                            ]
                        ];
                    })->values(),
                ];
            });

        return response()->json(['data' => $documents]);
    }

    public function inbox(Request $request): JsonResponse
    {
        $tenantId = tenant('id');
        $user = $request->user();

        $signers = DocumentSigner::where('tenant_id', $tenantId)
            ->where('user_id', $user->global_id)
            ->with(['document', 'document.signers.user'])
            ->get();

        $needSignature = [];
        $waiting = [];      // You already signed, waiting for others
        $upcoming = [];     // Waiting for your turn (previous signers haven't signed yet)
        $completed = [];

        foreach ($signers as $signer) {
            $document = $signer->document;
            if (!$document) {
                continue;
            }

            $docSummary = $this->summarizeDocumentWithSigners($document, $signer);

            // Document completed - all signers done
            if ($document->status === Document::STATUS_COMPLETED) {
                $completed[] = $docSummary;
                continue;
            }

            // Your turn to sign
            if ($signer->status === 'active') {
                $needSignature[] = $docSummary;
                continue;
            }

            // You already signed, waiting for next signers
            if ($signer->status === 'signed') {
                $waiting[] = $docSummary;
                continue;
            }

            // Queued = waiting for your turn (previous signers haven't finished)
            if ($signer->status === 'queued') {
                $upcoming[] = $docSummary;
                continue;
            }
        }

        return response()->json([
            'needSignature' => $needSignature,
            'waiting' => $waiting,
            'upcoming' => $upcoming,
            'completed' => $completed,
        ]);
    }

    public function cancel(Request $request, Document $document, AuditLogService $auditLogService): JsonResponse
    {
        $tenantId = tenant('id');
        $user = $request->user();

        $this->ensureDocumentTenant($document, $tenantId);

        if (in_array($document->status, [Document::STATUS_COMPLETED, Document::STATUS_CANCELED], true)) {
            return response()->json([
                'message' => 'Dokumen tidak dapat dibatalkan.',
                'code' => 'document_not_cancelable',
            ], 409);
        }

        $document->forceFill([
            'status' => Document::STATUS_CANCELED,
            'canceled_at' => now(),
            'canceled_by_user_id' => $user->global_id,
            'status_updated_at' => now(),
        ])->save();

        $document->signers()->whereNull('signed_at')->update([
            'status' => 'canceled',
        ]);

        $auditLogService->log(
            $request,
            'document_canceled',
            $tenantId,
            $user->global_id,
            Document::class,
            $document->id
        );

        return response()->json([
            'document' => [
                'documentId' => $document->id,
                'status' => $document->status,
                'canceledAt' => optional($document->canceled_at)->toIso8601String(),
            ],
        ]);
    }

    private function summarizeDocument(Document $document, ?DocumentSigner $signer = null): array
    {
        $latestVersion = $document->latestSignedVersion;

        return [
            'documentId' => $document->id,
            'chainId' => $document->chain_id,
            'status' => $document->status,
            'currentSignerIndex' => $document->current_signer_index,
            'originalFilename' => $document->original_filename,
            'latestVersion' => $latestVersion ? [
                'versionNumber' => $latestVersion->version_number,
                'signedAt' => optional($latestVersion->signed_at)->toIso8601String(),
                'signedPdfDownloadUrl' => url("/" . tenant('id') . "/api/documents/{$document->id}/versions/v{$latestVersion->version_number}:download"),
            ] : null,
            'yourSignerIndex' => $signer?->signer_index,
        ];
    }

    private function summarizeDocumentWithSigners(Document $document, ?DocumentSigner $yourSigner = null): array
    {
        $latestVersion = $document->latestSignedVersion;

        // Get all signers for this document with their status
        $signers = $document->signers->sortBy('signer_index')->map(function ($s) use ($document) {
            return [
                'id' => $s->id,
                'signerIndex' => $s->signer_index,
                'status' => $s->status,
                'name' => $s->user?->name ?? null,
                'email' => $s->user?->email ?? null,
                'signedAt' => optional($s->signed_at)->toIso8601String(),
                'isCurrent' => $s->signer_index === $document->current_signer_index,
            ];
        })->values()->toArray();

        return [
            'documentId' => $document->id,
            'chainId' => $document->chain_id,
            'title' => $document->original_filename,
            'status' => $document->status,
            'currentSignerIndex' => $document->current_signer_index,
            'totalSigners' => count($signers),
            'signedCount' => collect($signers)->where('status', 'signed')->count(),
            'yourSignerIndex' => $yourSigner?->signer_index,
            'yourStatus' => $yourSigner?->status,
            'signers' => $signers,
            'latestVersion' => $latestVersion ? [
                'versionNumber' => $latestVersion->version_number,
                'signedAt' => optional($latestVersion->signed_at)->toIso8601String(),
            ] : null,
            'completedAt' => $document->status === Document::STATUS_COMPLETED
                ? optional($document->updated_at)->toIso8601String()
                : null,
            'createdAt' => optional($document->created_at)->toIso8601String(),
        ];
    }

    private function ensureDocumentTenant(Document $document, string $tenantId): void
    {
        if ($document->created_by_tenant_id && $document->created_by_tenant_id !== $tenantId) {
            abort(404, 'Document not found.');
        }
    }

    private function ensureTenantMembership(string $tenantId, CentralUser $user, ?string $role = null): void
    {
        $roleToSet = $role ?: 'member';

        $membership = CentralTenantUser::where('tenant_id', $tenantId)
            ->where('global_user_id', $user->global_id)
            ->first();

        if (!$membership) {
            CentralTenantUser::create([
                'tenant_id' => $tenantId,
                'global_user_id' => $user->global_id,
                'role' => $roleToSet,
                'is_owner' => false,
                'tenant_join_date' => now(),
            ]);
        } elseif ($role && $membership->role !== $roleToSet) {
            $membership->role = $roleToSet;
            $membership->save();
        }

        $tenantUser = TenantUser::where('global_id', $user->global_id)->first();
        if (!$tenantUser) {
            TenantUser::create([
                'global_id' => $user->global_id,
                'name' => $user->name,
                'email' => $user->email,
                'password' => $user->password,
                'tenant_id' => $tenantId,
                'role' => $roleToSet,
                'is_owner' => false,
                'tenant_join_date' => now(),
            ]);
        } elseif ($role && $tenantUser->role !== $roleToSet) {
            $tenantUser->role = $roleToSet;
            $tenantUser->save();
        }
    }
}
