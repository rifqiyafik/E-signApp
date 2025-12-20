# E-Signer API Documentation (Multi-tenant)

Dokumentasi ini menjelaskan alur end-to-end untuk API multi-tenant:
mulai dari provisioning tenant, autentikasi, signing dokumen,
download versi, hingga verifikasi dokumen.

## 1) Overview

- Tenancy: path-based. Semua route tenant berada di `/{tenant}/api`.
- Tenant identifier: slug terlebih dulu, lalu ID (fallback).
- Auth: Laravel Passport personal access token per-tenant.
- Data: tenant/users/certificates/documents disimpan di central DB.
- Tenant DB: menyimpan `users`, OAuth tables, dan data tenant-specific.
- Signing pipeline: input PDF -> stamp QR + text -> sign (X.509) -> hash -> simpan versi baru.

## 2) Terminologi

- `tenant`: slug atau tenant ID (ULID) di path.
- `documentId`: ID dokumen.
- `chainId`: ID rantai dokumen (berubah saat versi baru).
- `versionNumber`: versi dokumen (mulai dari 1).

## 3) Base URL dan tenant parameter

Base URL format:
- `http://13.229.151.205/{tenant}/api`

Contoh:
- `http://13.229.151.205/nusanett/api` (slug)
- `http://13.229.151.205/01KCTVRDZ7F51PJHM5C70PK00W/api` (tenant ID)

Behavior:
- Tenant tidak ditemukan -> `404`.
- Tenant DB belum ada -> `500`.

## 4) Auth dan headers

Protected endpoints wajib menyertakan:
- `Authorization: Bearer {accessToken}`

Content type:
- JSON: `Content-Type: application/json`
- Upload PDF: `Content-Type: multipart/form-data`

Idempotency (sign):
- Header: `Idempotency-Key: {string}`
- Atau body `idempotencyKey` (opsional).

## 5) Local setup (dev)

### 5.1) Quick start (recommended)

1) Konfigurasi `.env` (DB central).
2) Jalankan migrasi central:

```powershell
php artisan migrate
```

3) Jalankan server:

```powershell
php artisan serve --host=127.0.0.1 --port=8000
```

4) Buat tenant + user pertama via endpoint central:

```
POST /api/tenants/register
```

Endpoint ini otomatis:
- Membuat tenant.
- Membuat user central + membership.
- Membuat tenant user di tenant DB.
- Memastikan passport keys ada.
- Membuat personal access client jika belum ada.
- Mengembalikan accessToken.

### 5.2) Manual provisioning (CLI)

1) Migrasi central:

```powershell
php artisan migrate
```

2) Buat tenant via tinker:

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

3) Migrasi tenant:

```powershell
php artisan tenants:migrate --tenants=demo
```

4) Buat passport keys + personal access client di tenant DB:

```powershell
php artisan tenants:run "passport:install" --tenants=demo --option=force=1 --option=no-interaction=1
```

5) (Opsional) Buat tenant user:

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

Central API:
- `POST /api/tenants/register`

Tenant public:
- `GET /{tenant}/api/public/info`
- `POST /{tenant}/api/auth/register`
- `POST /{tenant}/api/auth/login`
- `POST /{tenant}/api/verify`
- `GET /{tenant}/api/verify/{chainId}/v{version}`

Tenant protected:
- `GET /{tenant}/api/auth/me`
- `POST /{tenant}/api/documents/sign`
- `GET /{tenant}/api/documents/{documentId}`
- `GET /{tenant}/api/documents/{documentId}/versions`
- `GET /{tenant}/api/documents/{documentId}/versions/latest:download`
- `GET /{tenant}/api/documents/{documentId}/versions/v{version}:download`

## 7) Central API

### POST /api/tenants/register

Buat tenant baru sekaligus user pertama (testing/dev).

Headers:
- `Content-Type: application/json`

Body:
- `tenantName` (string, required)
- `tenantSlug` (string, optional, auto-generate jika kosong)
- `name` (string, required)
- `email` (string, required)
- `password` (string, required, min 8)
- `password_confirmation` (string, required)
- `role` (string, optional, default: `owner`)

Response:
- `accessToken`
- `tenantId`
- `tenantSlug`
- `userId`

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
- Endpoint ini untuk testing/dev. Untuk production sebaiknya pakai flow invitation/approval.

## 8) Tenant Public API

### GET /public/info

Response:
- `tenant` -> `id`, `name`, `slug`

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
- `/{tenant}/api/auth/register`

Body:
- `name` (string, required)
- `email` (string, required)
- `password` (string, required, min 8)
- `password_confirmation` (string, required)

Response:
- `accessToken`
- `tenantId`
- `userId`

Notes:
- Register membuat keypair + X.509 certificate via OpenSSL.
- Private key disimpan encrypted.

### POST /auth/login

Path:
- `/{tenant}/api/auth/login`

Body:
- `email` (string, required)
- `password` (string, required)

Response:
- `accessToken`
- `tenantId`
- `userId`

### POST /verify

Path:
- `/{tenant}/api/verify`

Headers:
- `Content-Type: multipart/form-data`

Body:
- `file` (PDF, required)

Response (valid):
- `valid: true`
- payload sama seperti sign (lihat section 9)
- `signatureValid: true | false | null`

Response (invalid):
- `valid: false`
- `reason: "hash_not_found"`
- `signedPdfSha256`

### GET /verify/{chainId}/v{version}

Path:
- `/{tenant}/api/verify/{chainId}/v{version}`

Response:
- `valid: true`
- payload sama seperti sign
- `signatureValid: true | false | null`

Endpoint ini adalah `verificationUrl` yang ditanam ke QR.

## 9) Tenant Protected API

### GET /auth/me

Path:
- `/{tenant}/api/auth/me`

Response:
- `profile` -> `userId`, `name`, `email`
- `tenant` -> `id`, `name`, `slug`
- `membership` -> `role`, `isOwner`, `joinedAt`

### POST /documents/sign

Path:
- `/{tenant}/api/documents/sign`

Headers:
- `Authorization: Bearer {accessToken}`
- `Content-Type: multipart/form-data`
- Optional: `Idempotency-Key: {string}`

Body:
- `file` (PDF, required)
- `consent` (boolean, required, must be true)
- `idempotencyKey` (string, optional)

Behavior:
- Menerima PDF yang sudah pernah ditandatangani.
- Membuat versi baru dan menambah signer baru.
- QR + text ditanam di halaman terakhir.

Response:
- `documentId`
- `chainId`
- `versionNumber`
- `verificationUrl`
- `signedPdfDownloadUrl`
- `signedPdfSha256`
- `signature` (lihat object di bawah)
- `signers[]` (lihat object di bawah)

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
- Gunakan `idempotencyKey` atau `Idempotency-Key` untuk mencegah duplicate versi.

### GET /documents/{documentId}

Path:
- `/{tenant}/api/documents/{documentId}`

Response:
- `documentId`, `chainId`
- `latestVersion` -> `versionNumber`, `signedPdfDownloadUrl`, `signedPdfSha256`, `signedAt`
- `signers[]`

### GET /documents/{documentId}/versions

Path:
- `/{tenant}/api/documents/{documentId}/versions`

Response:
- `documentId`, `chainId`
- `versions[]` -> `versionNumber`, `signedPdfSha256`, `signedPdfDownloadUrl`, `signedAt`

### GET /documents/{documentId}/versions/latest:download

Path:
- `/{tenant}/api/documents/{documentId}/versions/latest:download`

Response:
- File PDF (versi terbaru)

### GET /documents/{documentId}/versions/v{version}:download

Path:
- `/{tenant}/api/documents/{documentId}/versions/v{version}:download`

Response:
- File PDF (versi tertentu)

## 10) Response object reference

Signature object:
- `algorithm`
- `certificateFingerprint`
- `certificateSubject`
- `certificateSerial`

Signer object:
- `index` (urutan signer)
- `tenantId`
- `userId`
- `name`
- `email`
- `role`
- `signedAt` (ISO string)
- `certificate` -> `serial`, `issuedBy`, `validFrom`, `validTo`

Notes:
- Field signature dapat bernilai `null` untuk versi lama yang belum menyimpan metadata.

## 11) Error responses (umum)

Laravel default error format:
- `422 Unprocessable Entity` -> `{ "message": "...", "errors": { "field": ["..."] } }`
- `401 Unauthorized` -> `{ "message": "Unauthenticated." }`
- `404 Not Found` -> `{ "message": "Not Found." }`
- `409 Conflict` -> `{ "message": "Email already registered. Please login." }`
- `500 Internal Server Error` -> `{ "message": "Server Error" }`

## 12) Example flow (ringkas)

1) Central register tenant:
- `POST /api/tenants/register` -> simpan `tenantSlug` + `accessToken`.

2) Sign document:
- `POST /{tenant}/api/documents/sign` (Bearer token, multipart PDF)

3) Share QR:
- `verificationUrl` dari response sign.

4) Verify:
- `POST /{tenant}/api/verify` (upload PDF)
- atau akses `GET /{tenant}/api/verify/{chainId}/v{version}`

## 13) Multi-signer note

Endpoint sign menerima PDF yang sudah ditandatangani dan menambah signer baru:
- `signers[]` bertambah.
- `versionNumber` naik.
- `verificationUrl` baru untuk versi terbaru.

Hash selalu dihitung dari PDF hasil render (stamped), bukan file asli.
