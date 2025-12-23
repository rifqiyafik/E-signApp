<?php

namespace Tests\Feature;

use App\Models\DocumentVersion;
use App\Models\Tenant;
use App\Models\TenantUser as CentralTenantUser;
use App\Models\Tenant\User as TenantUser;
use App\Models\User as CentralUser;
use App\Services\UserCertificateService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\PersonalAccessClient;
use Tests\TestCase;

class ApiEndpointsTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensureTenantDatabaseDirectory();
        Storage::fake('central');
        $this->ensurePassportKeys();
    }

    public function test_central_tenant_register_endpoint(): void
    {
        $payload = [
            'tenantName' => 'Nusa Work ' . Str::random(4),
            'name' => 'Rifqi Test',
            'email' => 'rifqi+' . Str::random(6) . '@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ];

        $response = $this->postJson('/api/tenants/register', $payload);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'accessToken',
                'tenantId',
                'tenantSlug',
                'userId',
            ]);
    }

    public function test_public_info_endpoint(): void
    {
        $tenant = $this->createTenant();

        $response = $this->get("/{$tenant->slug}/api/public/info");

        $response->assertStatus(200)
            ->assertJsonPath('tenant.id', $tenant->id);
    }

    public function test_auth_register_login_and_me(): void
    {
        $tenant = $this->createTenant();
        $this->ensurePersonalAccessClient($tenant);

        $email = 'user+' . Str::random(6) . '@example.com';

        $register = $this->postJson("/{$tenant->slug}/api/auth/register", [
            'name' => 'Test User',
            'email' => $email,
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ]);

        $register->assertStatus(201)
            ->assertJsonStructure(['accessToken', 'tenantId', 'userId']);

        $login = $this->postJson("/{$tenant->slug}/api/auth/login", [
            'email' => $email,
            'password' => 'secret123',
        ]);

        $login->assertStatus(200)
            ->assertJsonStructure(['accessToken', 'tenantId', 'userId']);

        $token = $login->json('accessToken');

        $me = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->get("/{$tenant->slug}/api/auth/me");

        $me->assertStatus(200)
            ->assertJsonPath('profile.email', $email)
            ->assertJsonPath('tenant.id', $tenant->id);
    }

    public function test_documents_sign_versions_download_and_verify(): void
    {
        $tenant = $this->createTenant();
        $this->ensurePersonalAccessClient($tenant);

        $email = 'signer+' . Str::random(6) . '@example.com';

        $register = $this->postJson("/{$tenant->slug}/api/auth/register", [
            'name' => 'Signer User',
            'email' => $email,
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ]);

        $register->assertStatus(201);
        $token = $register->json('accessToken');

        $pdfUpload = $this->makePdfUpload();

        $sign = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->post("/{$tenant->slug}/api/documents/sign", [
                'file' => $pdfUpload,
                'consent' => 'true',
            ]);

        $sign->assertStatus(200)
            ->assertJsonStructure([
                'documentId',
                'chainId',
                'versionNumber',
                'verificationUrl',
                'signedPdfDownloadUrl',
                'signedPdfSha256',
                'signature' => [
                    'algorithm',
                    'certificateFingerprint',
                    'certificateSubject',
                    'certificateSerial',
                ],
                'tsa',
                'ltv',
                'signers',
            ]);

        $this->assertNotEmpty($sign->json('tsa.signedAt'));
        $this->assertNotEmpty($sign->json('tsa.fingerprint'));
        $this->assertTrue((bool) $sign->json('ltv.enabled'));
        $this->assertNotEmpty($sign->json('ltv.rootCaFingerprint'));

        $documentId = $sign->json('documentId');
        $chainId = $sign->json('chainId');

        $show = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->get("/{$tenant->slug}/api/documents/{$documentId}");

        $show->assertStatus(200)
            ->assertJsonPath('latestVersion.versionNumber', 1);

        $versions = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->get("/{$tenant->slug}/api/documents/{$documentId}/versions");

        $versions->assertStatus(200)
            ->assertJsonPath('versions.0.versionNumber', 1);

        $downloadLatest = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->get("/{$tenant->slug}/api/documents/{$documentId}/versions/latest:download");
        $downloadLatest->assertStatus(200);

        $downloadV1 = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->get("/{$tenant->slug}/api/documents/{$documentId}/versions/v1:download");
        $downloadV1->assertStatus(200);

        $version = DocumentVersion::where('document_id', $documentId)->firstOrFail();
        $signedBytes = Storage::disk($version->file_disk)->get($version->file_path);

        $verify = $this->post("/{$tenant->slug}/api/verify", [
            'file' => $this->makePdfUpload('signed.pdf', $signedBytes),
        ]);

        $verify->assertStatus(200)
            ->assertJsonPath('valid', true)
            ->assertJsonPath('signatureValid', true)
            ->assertJsonPath('certificateStatus', 'valid')
            ->assertJsonPath('tsaStatus', 'valid')
            ->assertJsonPath('ltvStatus', 'ready');

        $verifyChain = $this->getJson("/{$tenant->slug}/api/verify/{$chainId}/v1");
        $verifyChain->assertStatus(200)
            ->assertJsonPath('valid', true)
            ->assertJsonPath('tsaStatus', 'valid')
            ->assertJsonPath('ltvStatus', 'ready');
    }

    public function test_pki_root_ca_and_user_certificate_endpoints(): void
    {
        $tenant = $this->createTenant();
        $this->ensurePersonalAccessClient($tenant);

        $email = 'pki+' . Str::random(6) . '@example.com';

        $register = $this->postJson("/{$tenant->slug}/api/auth/register", [
            'name' => 'PKI User',
            'email' => $email,
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ]);

        $register->assertStatus(201);
        $token = $register->json('accessToken');

        $rootCa = $this->get("/{$tenant->slug}/api/pki/root-ca");
        $rootCa->assertStatus(200)
            ->assertJsonStructure([
                'certificate',
                'fingerprint',
                'subject',
                'validFrom',
                'validTo',
            ]);

        $me = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->get("/{$tenant->slug}/api/pki/certificates/me");

        $me->assertStatus(200)
            ->assertJsonStructure([
                'certificatePem',
                'fingerprint',
                'serial',
                'subject',
                'issuer',
                'validFrom',
                'validTo',
                'revokedAt',
                'revokedReason',
            ]);

        $revoke = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson("/{$tenant->slug}/api/pki/certificates/me/revoke", [
                'reason' => 'testing revoke',
            ]);

        $revoke->assertStatus(200)
            ->assertJsonPath('revokedReason', 'testing revoke');

        $enroll = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson("/{$tenant->slug}/api/pki/certificates/me/enroll");

        $enroll->assertStatus(200)
            ->assertJsonPath('revokedAt', null);
    }

    public function test_auth_login_with_seeded_user(): void
    {
        $tenant = $this->createTenant();
        $this->ensurePersonalAccessClient($tenant);

        $email = 'seeded+' . Str::random(6) . '@example.com';

        $centralUser = CentralUser::create([
            'global_id' => (string) Str::ulid(),
            'name' => 'Seeded User',
            'email' => $email,
            'password' => 'secret123',
        ]);

        CentralTenantUser::create([
            'tenant_id' => $tenant->id,
            'global_user_id' => $centralUser->global_id,
            'role' => 'member',
            'is_owner' => false,
            'tenant_join_date' => now(),
        ]);

        $tenant->run(function () use ($centralUser, $tenant) {
            TenantUser::create([
                'global_id' => $centralUser->global_id,
                'name' => $centralUser->name,
                'email' => $centralUser->email,
                'password' => 'secret123',
                'tenant_id' => $tenant->id,
                'role' => 'member',
                'is_owner' => false,
                'tenant_join_date' => now(),
            ]);
        });

        $login = $this->postJson("/{$tenant->slug}/api/auth/login", [
            'email' => $email,
            'password' => 'secret123',
        ]);

        $login->assertStatus(200)
            ->assertJsonStructure(['accessToken', 'tenantId', 'userId']);
    }

    private function createTenant(): Tenant
    {
        $tenant = Tenant::create([
            'name' => 'Tenant ' . Str::random(6),
            'slug' => 'tenant-' . Str::lower(Str::random(6)),
            'code' => Tenant::generateCode(),
        ]);

        return $tenant;
    }

    private function ensureTenantDatabaseDirectory(): void
    {
        $dir = database_path('testing');
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
    }

    private function ensurePersonalAccessClient(Tenant $tenant): void
    {
        $tenant->run(function () {
            $this->ensurePassportKeys();

            if (!PersonalAccessClient::query()->exists()) {
                app(ClientRepository::class)->createPersonalAccessClient(
                    null,
                    'Tenant Personal Access',
                    config('app.url') ?: url('/')
                );
            }
        });
    }

    private function ensurePassportKeys(): void
    {
        $privateKeyPath = storage_path('oauth-private.key');
        $publicKeyPath = storage_path('oauth-public.key');

        $privateKeyContent = file_exists($privateKeyPath) ? file_get_contents($privateKeyPath) : null;
        $publicKeyContent = file_exists($publicKeyPath) ? file_get_contents($publicKeyPath) : null;

        if ($privateKeyContent && $publicKeyContent) {
            $privateValid = openssl_pkey_get_private($privateKeyContent) !== false;
            $publicValid = openssl_pkey_get_public($publicKeyContent) !== false;

            if ($privateValid && $publicValid) {
                return;
            }
        }

        $opensslConfig = app(UserCertificateService::class)->getOpenSslConfigPath();
        $privateKeyConfig = [
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'private_key_bits' => 2048,
        ];

        if ($opensslConfig) {
            $privateKeyConfig['config'] = $opensslConfig;
        }

        $privateKey = openssl_pkey_new($privateKeyConfig);
        if ($privateKey === false) {
            throw new \RuntimeException('Failed to generate Passport private key.');
        }

        $exportOptions = [];
        if ($opensslConfig) {
            $exportOptions['config'] = $opensslConfig;
        }

        $exported = $exportOptions
            ? openssl_pkey_export($privateKey, $privateKeyPem, null, $exportOptions)
            : openssl_pkey_export($privateKey, $privateKeyPem);

        if (!$exported) {
            throw new \RuntimeException('Failed to export Passport private key.');
        }

        $publicKeyDetails = openssl_pkey_get_details($privateKey);
        $publicKeyPem = $publicKeyDetails['key'] ?? null;

        if (!$publicKeyPem) {
            throw new \RuntimeException('Failed to extract Passport public key.');
        }

        $privateDir = dirname($privateKeyPath);
        if (!is_dir($privateDir)) {
            @mkdir($privateDir, 0775, true);
        }

        file_put_contents($privateKeyPath, $privateKeyPem);
        file_put_contents($publicKeyPath, $publicKeyPem);
    }

    private function makePdfUpload(string $name = 'document.pdf', ?string $bytes = null): UploadedFile
    {
        $bytes = $bytes ?? $this->generatePdfBytes();
        $path = tempnam(sys_get_temp_dir(), 'pdf');
        file_put_contents($path, $bytes);

        return new UploadedFile($path, $name, 'application/pdf', null, true);
    }

    private function generatePdfBytes(): string
    {
        $pdf = new \TCPDF();
        $pdf->AddPage();
        $pdf->SetFont('helvetica', '', 12);
        $pdf->Write(0, 'E-Signer test PDF');

        return $pdf->Output('', 'S');
    }
}
