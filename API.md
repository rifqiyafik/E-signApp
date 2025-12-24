# E-Signer API Documentation (Multi-tenant)

Dokumentasi ini menjelaskan alur end-to-end untuk API multi-tenant:
mulai dari provisioning tenant, autentikasi, signing dokumen,
download versi, hingga verifikasi dokumen.

## 1) Overview

-   Tenancy: path-based. Semua route tenant berada di `/{tenant}/api`.
-   Tenant identifier: slug terlebih dulu, lalu ID (fallback).
-   Global login: `/api/auth/login` (central). Setelah login, pilih tenant untuk mendapatkan token tenant.
-   Auth: Laravel Passport personal access token (central & tenant).
-   Data: tenant/users/certificates/documents disimpan di central DB.
-   Tenant DB: menyimpan `users`, OAuth tables, dan data tenant-specific.
-   PKI: Root CA internal + TSA internal; sertifikat user diterbitkan oleh CA dan statusnya dicek saat verify (valid/expired/revoked/untrusted).
-   Workflow: Draft -> Need Signature -> Waiting -> Completed (+ Canceled/Expired).
-   Signing pipeline: draft PDF -> stamp QR (tanpa teks) -> sign (X.509) + embed chain -> hash -> TSA timestamp -> simpan versi baru + LTV snapshot.

## 2) Terminologi

-   `tenant`: slug atau tenant ID (ULID) di path.
-   `documentId`: ID dokumen.
-   `chainId`: ID rantai dokumen (berubah saat versi baru).
-   `versionNumber`: versi dokumen (mulai dari 1). Versi `0` dipakai untuk draft internal.
-   `documentStatus`: `draft | need_signature | waiting | completed | canceled | expired`.

## 3) Base URL dan tenant parameter

Base URL format:

-   `http://13.229.151.205/{tenant}/api`

Contoh:

-   `http://13.229.151.205/nusanett/api` (slug)
-   `http://13.229.151.205/01KCTVRDZ7F51PJHM5C70PK00W/api` (tenant ID)

Behavior:

-   Tenant tidak ditemukan -> `404`.
-   Tenant DB belum ada -> `500`.

## 4) Auth dan headers

Protected endpoints wajib menyertakan:

-   Central API: `Authorization: Bearer {centralAccessToken}`
-   Tenant API: `Authorization: Bearer {tenantAccessToken}`

Content type:

-   JSON: `Content-Type: application/json`
-   Upload PDF: `Content-Type: multipart/form-data`

Idempotency (sign):

-   Header: `Idempotency-Key: {string}`
-   Atau body `idempotencyKey` (opsional).

## 5) Local setup (dev)

### 5.1) Quick start (recommended)

1. Konfigurasi `.env` (DB central).
2. Jalankan migrasi central:

```powershell
php artisan migrate
```

3. Jalankan server:

```powershell
php artisan serve --host=127.0.0.1 --port=8000
```

4. Buat superadmin (sekali saja) via tinker:

```powershell
php artisan tinker
```

```php
use App\Models\User;
use Illuminate\Support\Facades\Hash;

$superadmin = User::create([
    'global_id' => (string) \Illuminate\Support\Str::ulid(),
    'name' => 'Superadmin',
    'email' => 'admin@example.com',
    'password' => Hash::make('secret123'),
    'is_superadmin' => true,
]);
```

5. Login global:

```
POST /api/auth/login
```

6. Buat tenant + user pertama via endpoint central (butuh token superadmin):

```
POST /api/tenants/register
```

Endpoint ini otomatis:

-   Membuat tenant.
-   Membuat user central + membership.
-   Membuat tenant user di tenant DB.
-   Memastikan passport keys ada.
-   Membuat personal access client jika belum ada.
-   Mengembalikan accessToken.

### 5.2) Manual provisioning (CLI)

1. Migrasi central:

```powershell
php artisan migrate
```

2. Buat tenant via tinker:

```powershell
php artisan tinker
```

```php
use App\Models\Tenant;

$tenant = Tenant::create([
    'name' => 'Demo Tenant',
    'code' => Tenant::generateCode(),
    'slug' => 'demo',
]);
```

3. Migrasi tenant:

```powershell
php artisan tenants:migrate --tenants=demo
```

4. Buat passport keys + personal access client di tenant DB:

```powershell
php artisan tenants:run "passport:install" --tenants=demo --option=force=1 --option=no-interaction=1
```

5. (Opsional) Buat tenant user:

```powershell
php artisan tinker
```

```php
use App\Models\Tenant\User as TenantUser;

TenantUser::create([
    'global_id' => 'u-001',
    'name' => 'Test User',
    'email' => 'test@example.com',
    'password' => bcrypt('secret123'),
]);
```

## 6) Ringkasan endpoint

Central API (public):

-   `POST /api/auth/login`

Central API (protected):

-   `GET /api/auth/me`
-   `POST /api/auth/select-tenant`
-   `POST /api/auth/logout`

Central API (superadmin):

-   `POST /api/tenants/register`
-   `GET /api/superadmin/tenants`
-   `PATCH /api/superadmin/tenants/{tenant}`
-   `DELETE /api/superadmin/tenants/{tenant}`
-   `POST /api/superadmin/tenants/{tenant}/users`
-   `POST /api/superadmin/tenants/{tenant}/users/assign`

Tenant public:

-   `GET /{tenant}/api/public/info`
-   `POST /{tenant}/api/auth/register`
-   `POST /{tenant}/api/auth/login`
-   `POST /{tenant}/api/verify`
-   `GET /{tenant}/api/verify/{chainId}/v{version}`
-   `POST /{tenant}/api/verify/{chainId}/v{version}`
-   `GET /{tenant}/api/pki/root-ca`

Tenant protected:

-   `GET /{tenant}/api/auth/me`
-   `GET /{tenant}/api/pki/certificates/me`
-   `POST /{tenant}/api/pki/certificates/me/enroll`
-   `POST /{tenant}/api/pki/certificates/me/renew`
-   `POST /{tenant}/api/pki/certificates/me/revoke`
-   `POST /{tenant}/api/documents/drafts` (tenant admin)
-   `POST /{tenant}/api/documents/{documentId}/signers` (tenant admin)
-   `POST /{tenant}/api/documents/{documentId}/sign`
-   `GET /{tenant}/api/documents/inbox`
-   `POST /{tenant}/api/documents/{documentId}/cancel` (tenant admin)
-   `POST /{tenant}/api/admin/users` (tenant admin)
-   `POST /{tenant}/api/admin/users/assign` (tenant admin)
-   `POST /{tenant}/api/documents/sign` (legacy direct sign)
-   `GET /{tenant}/api/documents/{documentId}`
-   `GET /{tenant}/api/documents/{documentId}/versions`
-   `GET /{tenant}/api/documents/{documentId}/versions/latest:download`
-   `GET /{tenant}/api/documents/{documentId}/versions/v{version}:download`

## 7) Central API

### POST /api/auth/login

Login global (central).

Body:

-   `email` (string, required)
-   `password` (string, required)

Response:

-   `accessToken` (central token)
-   `user` -> `userId`, `name`, `email`, `isSuperadmin`
-   `tenants[]` -> list tenant yang terkait user

### GET /api/auth/me

Header:

-   `Authorization: Bearer {centralAccessToken}`

Response:

-   `user`
-   `tenants[]`

### POST /api/auth/select-tenant

Header:

-   `Authorization: Bearer {centralAccessToken}`

Body:

-   `tenant` (string, required, slug atau tenant ID)

Response:

-   `accessToken` (tenant token)
-   `tenant` -> `id`, `name`, `slug`, `role`, `isOwner`

### POST /api/auth/logout

Header:

-   `Authorization: Bearer {centralAccessToken}`

Response:

-   `message`

### POST /api/tenants/register

Buat tenant baru sekaligus user pertama (testing/dev).

Headers:

-   `Content-Type: application/json`
-   `Authorization: Bearer {centralAccessToken}` (superadmin)

Body:

-   `tenantName` (string, required)
-   `tenantSlug` (string, optional, auto-generate jika kosong)
-   `name` (string, required)
-   `email` (string, required)
-   `password` (string, required, min 8)
-   `password_confirmation` (string, required)
-   `role` (string, optional, default: `super_admin`)

Response:

-   `accessToken`
-   `tenantId`
-   `tenantSlug`
-   `userId`

Example request:

```json
{
    "tenantName": "Demo Company",
    "tenantSlug": "demo",
    "name": "Rifqi Yafik",
    "email": "rifqi@domain.com",
    "password": "secret123",
    "password_confirmation": "secret123",
    "role": "Direktur"
}
```

Example response:

```json
{
    "accessToken": "eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9...",
    "tenantId": "01KCTVRDZ7F51PJHM5C70PK00W",
    "tenantSlug": "demo",
    "userId": "01KCTVRDNTV8QZDACE56SX1HXG"
}
```

Notes:

-   Endpoint ini hanya untuk superadmin. Untuk production sebaiknya pakai flow invitation/approval.
-   Konflik `email` -> `409` dengan `code: email_already_registered`.
-   Konflik `tenantName` -> `409` dengan `code: tenant_name_exists`.
-   Konflik `tenantSlug` -> `409` dengan `code: tenant_slug_exists`.

### GET /api/superadmin/tenants

List tenant (superadmin).

### PATCH /api/superadmin/tenants/{tenant}

Update tenant (name, slug, plan, status).

### DELETE /api/superadmin/tenants/{tenant}

Soft delete tenant (status = deleted).

### POST /api/superadmin/tenants/{tenant}/users

Buat user baru dan assign ke tenant.

### POST /api/superadmin/tenants/{tenant}/users/assign

Assign user existing ke tenant.

## 8) Tenant Public API

### GET /public/info

Response:

-   `tenant` -> `id`, `name`, `slug`

Example response:

```json
{
    "tenant": {
        "id": "01KCTVRDZ7F51PJHM5C70PK00W",
        "name": "Demo Tenant",
        "slug": "demo"
    }
}
```

### POST /auth/register

Path:

-   `/{tenant}/api/auth/register`

Body:

-   `name` (string, required)
-   `email` (string, required)
-   `password` (string, required, min 8)
-   `password_confirmation` (string, required)

Response:

-   `accessToken`
-   `tenantId`
-   `userId`

Notes:

-   Register membuat keypair + X.509 certificate via OpenSSL (ditandatangani Root CA internal).
-   Private key disimpan encrypted.
-   Konflik `email` -> `409` dengan `code: email_already_registered`.
-   Untuk flow global, gunakan `/api/auth/login` + `/api/auth/select-tenant`. Endpoint ini bisa dipakai admin tenant untuk menambah user.

### POST /auth/login

Path:

-   `/{tenant}/api/auth/login`

Body:

-   `email` (string, required)
-   `password` (string, required)

Response:

-   `accessToken`
-   `tenantId`
-   `userId`

Notes:

-   Credential salah -> `422` dengan `code: validation_failed` dan `errors.email`.

### GET /pki/root-ca

Path:

-   `/{tenant}/api/pki/root-ca`

Response:

-   `certificate` (PEM)
-   `fingerprint`
-   `subject`
-   `validFrom`
-   `validTo`

### POST /verify

Path:

-   `/{tenant}/api/verify`

Headers:

-   `Content-Type: multipart/form-data`

Body:

-   `file` (PDF, required)

Response (valid):

-   `valid: true`
-   payload sama seperti sign (lihat section 9)
-   `signatureValid: true | false | null`
-   `certificateStatus: valid | expired | revoked | untrusted | not_yet_valid | missing`
-   `rootCaFingerprint`
-   `certificateRevokedAt` (nullable)
-   `certificateRevokedReason` (nullable)
-   `tsaStatus: valid | invalid | missing`
-   `tsaSignedAt` (nullable)
-   `tsaFingerprint` (nullable)
-   `tsaReason` (nullable)
-   `ltvStatus: ready | incomplete | missing`
-   `ltvGeneratedAt` (nullable)
-   `ltvIssues` (array, optional)

Response (invalid):

-   `valid: false`
-   `reason: "hash_not_found" | "hash_mismatch"`
-   `signedPdfSha256`
-   `expectedSignedPdfSha256` (nullable)

### GET /verify/{chainId}/v{version}

Path:

-   `/{tenant}/api/verify/{chainId}/v{version}`

Response:

-   `valid: true`
-   payload sama seperti sign
-   `signatureValid: true | false | null`
-   `certificateStatus`, `rootCaFingerprint`, `certificateRevokedAt`, `certificateRevokedReason`

Notes:

-   Endpoint ini adalah `verificationUrl` yang ditanam ke QR.
-   Jika `Accept: text/html`, response berupa halaman verifikasi (bukan JSON).

### POST /verify/{chainId}/v{version}

Path:

-   `/{tenant}/api/verify/{chainId}/v{version}`

Headers:

-   `Content-Type: multipart/form-data`

Body:

-   `file` (PDF, required)

Response (valid):

-   sama seperti `GET /verify/{chainId}/v{version}`

Response (invalid):

-   `valid: false`
-   `reason: "hash_mismatch"`
-   `signedPdfSha256` (hash dari file yang diupload)
-   `expectedSignedPdfSha256` (hash versi asli dari server)

## 9) Tenant Protected API

### GET /auth/me

Path:

-   `/{tenant}/api/auth/me`

Response:

-   `profile` -> `userId`, `name`, `email`
-   `tenant` -> `id`, `name`, `slug`
-   `membership` -> `role`, `isOwner`, `joinedAt`

### GET /pki/certificates/me

Path:

-   `/{tenant}/api/pki/certificates/me`

Response:

-   `certificatePem`, `fingerprint`, `serial`, `subject`, `issuer`
-   `validFrom`, `validTo`
-   `revokedAt`, `revokedReason`

### POST /pki/certificates/me/enroll

Path:

-   `/{tenant}/api/pki/certificates/me/enroll`

Response:

-   sama seperti `GET /pki/certificates/me`

Notes:

-   Membuat sertifikat baru jika belum ada atau sudah revoked.

### POST /pki/certificates/me/renew

Path:

-   `/{tenant}/api/pki/certificates/me/renew`

Response:

-   sama seperti `GET /pki/certificates/me`

Notes:

-   Rotasi keypair + sertifikat baru (CA-signed).

### POST /pki/certificates/me/revoke

Path:

-   `/{tenant}/api/pki/certificates/me/revoke`

Body (optional):

-   `reason` (string)

Response:

-   sama seperti `GET /pki/certificates/me`

### POST /documents/drafts

Path:

-   `/{tenant}/api/documents/drafts`

Headers:

-   `Authorization: Bearer {tenantAccessToken}`
-   `Content-Type: multipart/form-data`

Body:

-   `file` (PDF, required)

Response:

-   `document` -> `documentId`, `chainId`, `status`, `draftSha256`, `draftUploadedAt`

### POST /documents/{documentId}/signers

Path:

-   `/{tenant}/api/documents/{documentId}/signers`

Body:

-   `signers[]` (array, required)
-   `signers[].user` (string, global_id atau email)
-   `signers[].role` (string, optional)
-   `expiresAt` (date, optional)

Response:

-   `document` -> `documentId`, `chainId`, `status`, `currentSignerIndex`, `expiresAt`

### POST /documents/{documentId}/sign

Path:

-   `/{tenant}/api/documents/{documentId}/sign`

Body:

-   `consent` (boolean, required, must be true)
-   `idempotencyKey` (string, optional)

Response:

-   sama seperti `POST /documents/sign` (lihat di bawah)
-   tambahan: `documentStatus`, `currentSignerIndex`, `expiresAt`

### GET /documents/inbox

Path:

-   `/{tenant}/api/documents/inbox`

Response:

-   `needSignature[]`
-   `waiting[]`
-   `completed[]`

### POST /documents/{documentId}/cancel

Path:

-   `/{tenant}/api/documents/{documentId}/cancel`

Response:

-   `document` -> `documentId`, `status`, `canceledAt`

### POST /admin/users

Path:

-   `/{tenant}/api/admin/users`

Body:

-   `name`, `email`, `password`, `password_confirmation`
-   `role` (optional)

Response:

-   `user` -> `userId`, `name`, `email`, `role`

### POST /admin/users/assign

Path:

-   `/{tenant}/api/admin/users/assign`

Body:

-   `user` (global_id atau email)
-   `role` (optional)

Response:

-   `user` -> `userId`, `name`, `email`, `role`

### POST /documents/sign

Path:

-   `/{tenant}/api/documents/sign`

Headers:

-   `Authorization: Bearer {accessToken}`
-   `Content-Type: multipart/form-data`
-   Optional: `Idempotency-Key: {string}`

Body:

-   `file` (PDF, required)
-   `consent` (boolean, required, must be true)
-   `idempotencyKey` (string, optional)

Behavior:

-   Endpoint legacy untuk direct sign (tanpa workflow).
-   Untuk workflow berurutan gunakan: `POST /documents/drafts` -> `POST /documents/{documentId}/signers` -> `POST /documents/{documentId}/sign`.
-   Menerima PDF yang sudah pernah ditandatangani.
-   Membuat versi baru dan menambah signer baru.
-   QR (tanpa teks) ditanam di halaman terakhir.
-   Digital signature menyertakan sertifikat user + chain Root CA.
-   TSA internal menghasilkan timestamp + LTV snapshot.

Response:

-   `documentId`
-   `chainId`
-   `versionNumber`
-   `verificationUrl`
-   `signedPdfDownloadUrl`
-   `signedPdfSha256`
-   `signature` (lihat object di bawah)
-   `tsa` (lihat object di bawah)
-   `ltv` (lihat object di bawah)
-   `signers[]` (lihat object di bawah)

Example response:

```json
{
    "documentId": "01KCTX...",
    "chainId": "01KCTY...",
    "versionNumber": 1,
    "verificationUrl": "http://127.0.0.1:8000/demo/api/verify/01KCTY.../v1",
    "signedPdfDownloadUrl": "http://127.0.0.1:8000/demo/api/documents/01KCTX.../versions/v1:download",
    "signedPdfSha256": "ad8f6b6d4a...",
    "signature": {
        "algorithm": "sha256WithRSAEncryption",
        "certificateFingerprint": "2c8c3b4b...",
        "certificateSubject": "CN=Test User, emailAddress=test@example.com",
        "certificateSerial": "1f9a..."
    },
    "tsa": {
        "signedAt": "2025-12-22T09:20:00+00:00",
        "fingerprint": "7d9c...",
        "algorithm": "sha256WithRSAEncryption"
    },
    "ltv": {
        "enabled": true,
        "generatedAt": "2025-12-22T09:20:00+00:00",
        "rootCaFingerprint": "a1b2...",
        "tsaFingerprint": "7d9c..."
    },
    "signers": [
        {
            "index": 1,
            "tenantId": "demo",
            "userId": "u-001",
            "name": "Test User",
            "email": "test@example.com",
            "role": "Direktur",
            "signedAt": "2025-12-20T08:52:44+00:00",
            "certificate": {
                "serial": "A1B2C3D4",
                "issuedBy": "CN=Test User, emailAddress=test@example.com, O=E-Signer, C=ID",
                "validFrom": "2025-01-01",
                "validTo": "2027-01-01"
            }
        }
    ]
}
```

Idempotency:

-   Gunakan `idempotencyKey` atau `Idempotency-Key` untuk mencegah duplicate versi.

### GET /documents/{documentId}

Path:

-   `/{tenant}/api/documents/{documentId}`

Response:

-   `documentId`, `chainId`
-   `latestVersion` -> `versionNumber`, `signedPdfDownloadUrl`, `signedPdfSha256`, `signedAt`
-   `signers[]`

### GET /documents/{documentId}/versions

Path:

-   `/{tenant}/api/documents/{documentId}/versions`

Response:

-   `documentId`, `chainId`
-   `versions[]` -> `versionNumber`, `signedPdfSha256`, `signedPdfDownloadUrl`, `signedAt`

### GET /documents/{documentId}/versions/latest:download

Path:

-   `/{tenant}/api/documents/{documentId}/versions/latest:download`

Response:

-   File PDF (versi terbaru)

### GET /documents/{documentId}/versions/v{version}:download

Path:

-   `/{tenant}/api/documents/{documentId}/versions/v{version}:download`

Response:

-   File PDF (versi tertentu)

## 10) Response object reference

Signature object:

-   `algorithm`
-   `certificateFingerprint`
-   `certificateSubject`
-   `certificateSerial`

TSA object:

-   `signedAt` (nullable)
-   `fingerprint` (nullable)
-   `algorithm` (nullable)

LTV object:

-   `enabled`
-   `generatedAt` (nullable)
-   `rootCaFingerprint` (nullable)
-   `tsaFingerprint` (nullable)

Verify response extras:

-   `certificateStatus` -> `valid | expired | revoked | untrusted | not_yet_valid | missing`
-   `rootCaFingerprint`
-   `certificateRevokedAt` (nullable)
-   `certificateRevokedReason` (nullable)
-   `tsaStatus` -> `valid | invalid | missing`
-   `tsaSignedAt` (nullable)
-   `tsaFingerprint` (nullable)
-   `tsaReason` (nullable)
-   `ltvStatus` -> `ready | incomplete | missing`
-   `ltvGeneratedAt` (nullable)
-   `ltvIssues` (optional array)
-   `expectedSignedPdfSha256` (nullable, hanya saat `hash_mismatch`)

Signer object:

-   `index` (urutan signer)
-   `tenantId`
-   `userId`
-   `name`
-   `email`
-   `role`
-   `status` (`queued | active | signed | canceled`)
-   `assignedAt`
-   `assignedByUserId`
-   `versionId`
-   `signedAt` (ISO string)
-   `certificate` -> `serial`, `issuedBy`, `validFrom`, `validTo`

Document extras (sign/verify response):

-   `documentStatus`
-   `currentSignerIndex`
-   `expiresAt`

Notes:

-   Field signature dapat bernilai `null` untuk versi lama yang belum menyimpan metadata.

UserCertificateResponse object:

-   `certificatePem`
-   `fingerprint`
-   `serial`
-   `subject`
-   `issuer`
-   `validFrom`, `validTo`
-   `revokedAt` (nullable)
-   `revokedReason` (nullable)

## 11) Error responses (umum)

Laravel default error format:

Semua error JSON sekarang konsisten:

-   `message` (string)
-   `code` (string)
-   `errors` (object, optional untuk field-level)
-   `hint` (string, optional untuk error DB permission)
-   `errorId` (string, optional untuk tracing server error)

Contoh:

`422 Unprocessable Entity`

```json
{
    "message": "The email field is required.",
    "code": "validation_failed",
    "errors": {
        "email": ["Email wajib diisi."]
    }
}
```

`409 Conflict` (email sudah terdaftar)

```json
{
    "message": "Email sudah terdaftar. Silakan login.",
    "code": "email_already_registered",
    "errors": {
        "email": ["Email sudah terdaftar. Silakan login."]
    }
}
```

`409 Conflict` (tenant name/slug)

```json
{
    "message": "Nama tenant sudah digunakan.",
    "code": "tenant_name_exists",
    "errors": {
        "tenantName": ["Nama tenant sudah digunakan."]
    }
}
```

`401 Unauthorized`

```json
{
    "message": "Unauthenticated.",
    "code": "unauthenticated"
}
```

`404 Not Found`

```json
{
    "message": "Resource not found.",
    "code": "not_found"
}
```

`500 Internal Server Error`

```json
{
    "message": "Internal server error.",
    "code": "internal_error",
    "errorId": "c0a6d8b3-0c14-4a7b-8f7b-2c6a67c5c5a7"
}
```

`500 Database permission error`

```json
{
    "message": "Database permission error.",
    "code": "db_permission_denied",
    "hint": "Check DB user privileges or tenancy database manager."
}
```

## 12) Example flow (ringkas)

1. Global login:

-   `POST /api/auth/login` -> simpan `centralAccessToken`.

2. Pilih tenant:

-   `POST /api/auth/select-tenant` -> simpan `tenantAccessToken`.

3. Upload draft (tenant admin):

-   `POST /{tenant}/api/documents/drafts` -> simpan `documentId`.

4. Tentukan urutan signer (tenant admin):

-   `POST /{tenant}/api/documents/{documentId}/signers`.

5. Sign oleh signer aktif:

-   `POST /{tenant}/api/documents/{documentId}/sign` (Bearer tenant token).

6. Share QR:

-   `verificationUrl` dari response sign.

7. Verify:

-   `POST /{tenant}/api/verify` (upload PDF)
-   atau akses `GET /{tenant}/api/verify/{chainId}/v{version}`
-   jika ingin cross-check file dengan QR: `POST /{tenant}/api/verify/{chainId}/v{version}` + upload PDF

## 13) Multi-signer note

Workflow berurutan:

-   Signer pertama mendapat status `active`.
-   Signer berikutnya `queued` hingga signer sebelumnya selesai.
-   Setelah semua selesai, dokumen berstatus `completed`.

Hash selalu dihitung dari PDF hasil render (stamped), bukan file asli.
