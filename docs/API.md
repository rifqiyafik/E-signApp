# E-Signer API Documentation (Multi-tenant)

This document describes the end-to-end flow for the multi-tenant API:
from provisioning a tenant to signing, downloading, and verifying documents.

## 1) Overview

- Tenancy strategy: path-based. All tenant API routes are under `/{tenant}/api`.
- Tenant identifier: slug first, then ID as fallback.
- Auth: Laravel Passport personal access tokens (per-tenant).
- Documents: stored centrally in `documents`, `document_versions`, `document_signers`.
- Signing pipeline: input PDF -> render with QR + text -> apply X.509 signature -> hash output -> save as new version.

## 2) Base URL and tenant parameter

Base URL format:
- `http://127.0.0.1:8000/{tenant}/api`

Examples:
- `http://127.0.0.1:8000/demo/api` (slug)
- `http://127.0.0.1:8000/01KCTVRDZ7F51PJHM5C70PK00W/api` (tenant ID)

Behavior:
- If the tenant is not found, API returns `404` with JSON message.
- If the tenant database is missing, API returns `500` with JSON message.

## 3) Local setup (dev)

1) Configure `.env` for the central database.

2) Run central migrations:

```powershell
php artisan migrate
```

3) Create a tenant record (example via tinker).
Note: tenant database name is `TENANT_DB_PREFIX + tenantId` from `config/tenancy.php`.

```powershell
php artisan tinker
```

```php
use App\Models\Tenant;

$tenant = Tenant::create([
    'id' => 'demo',
    'name' => 'Demo Tenant',
    'code' => Tenant::generateCode(),
    'slug' => 'demo',
]);
```

4) Run tenant migrations:

```powershell
php artisan tenants:migrate --tenants=demo
```

If your MySQL user cannot create databases automatically, create the tenant DB first,
then re-run the command above.

5) Generate Passport keys and create a personal access client per tenant:

```powershell
php artisan tenants:run "passport:keys" --tenants=demo --option=force=1 --option=no-interaction=1
php artisan tenants:run "passport:client" --tenants=demo --option=personal=1 --option=name="Tenant Personal Access" --option=no-interaction=1
```

6) Create a tenant user (example via tenant DB context):

```powershell
php artisan tinker
```

```php
use App\Models\Tenant\User as TenantUser;

$user = TenantUser::create([
    'global_id' => 'u-001',
    'name' => 'Test User',
    'email' => 'test@example.com',
    'password' => bcrypt('secret123'),
]);
```

7) Run the server:

```powershell
php artisan serve --host=127.0.0.1 --port=8000
```

## 4) Authentication

### POST /auth/register

Path:
- `/{tenant}/api/auth/register`

Headers:
- `Content-Type: application/json`

Body (JSON):
- `name` (string, required)
- `email` (string, required)
- `password` (string, required, min 8)
- `password_confirmation` (string, required)
- `deviceName` (string, optional, max 100)

Response:
- `accessToken` (string)
- `tenantId` (string)
- `userId` (string)

Notes:
- Register generates a user keypair (private/public) and X.509 certificate using OpenSSL.
- Private key is stored encrypted at rest.

Example request:

```json
{
  "name": "Test User",
  "email": "test@example.com",
  "password": "secret123",
  "password_confirmation": "secret123",
  "deviceName": "postman"
}
```

Example response:

```json
{
  "accessToken": "eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9...",
  "tenantId": "demo",
  "userId": "u-001"
}
```

### POST /auth/login

Path:
- `/{tenant}/api/auth/login`

Headers:
- `Content-Type: application/json`

Body (JSON):
- `email` (string, required)
- `password` (string, required)
- `deviceName` (string, optional, max 100) -> token label (example: "postman")

Response:
- `accessToken` (string)
- `tenantId` (string)
- `userId` (string)

Example request:

```json
{
  "email": "test@example.com",
  "password": "secret123",
  "deviceName": "postman"
}
```

Example response:

```json
{
  "accessToken": "eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9...",
  "tenantId": "demo",
  "userId": "u-001"
}
```

### GET /auth/me

Path:
- `/{tenant}/api/auth/me`

Headers:
- `Authorization: Bearer {accessToken}`

Response:
- `profile` -> `userId`, `name`, `email`
- `tenant` -> `id`, `name`, `slug`
- `membership` -> `role`, `isOwner`, `joinedAt` (nullable)

## 5) Sign documents (core)

### POST /documents/sign

Path:
- `/{tenant}/api/documents/sign`

Headers:
- `Authorization: Bearer {accessToken}`
- `Content-Type: multipart/form-data`
- Optional: `Idempotency-Key: {string}`

Body (multipart/form-data):
- `file` (PDF, required)
- `consent` (boolean, required, must be true)
- `idempotencyKey` (string, optional, can be sent as header)

Behavior:
- Accepts PDFs that were signed before.
- A new version is created and a signer entry is appended.
- QR and verification text are stamped on the last page.

Response (minimal):
- `documentId`
- `chainId`
- `versionNumber`
- `verificationUrl`
- `signedPdfDownloadUrl`
- `signedPdfSha256`
- `signature` -> `algorithm`, `certificateFingerprint`, `certificateSubject`, `certificateSerial`
- `signers[]` -> `index`, `tenantId`, `userId`, `name`, `email`, `role`, `signedAt`, `certificate{serial,issuedBy,validFrom,validTo}`

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
      "signedAt": "2025-12-20T08:52:44+00:00"
    }
  ]
}
```

Idempotency:
- Provide `idempotencyKey` or `Idempotency-Key` to avoid duplicate versions on retries.

## 6) Versions and download

### GET /documents/{documentId}

Path:
- `/{tenant}/api/documents/{documentId}`

Headers:
- `Authorization: Bearer {accessToken}`

Response:
- `documentId`, `chainId`
- `latestVersion` -> `versionNumber`, `signedPdfDownloadUrl`, `signedPdfSha256`, `signedAt`
- `signers[]`

### GET /documents/{documentId}/versions

Path:
- `/{tenant}/api/documents/{documentId}/versions`

Headers:
- `Authorization: Bearer {accessToken}`

Response:
- `documentId`, `chainId`
- `versions[]` -> `versionNumber`, `signedPdfSha256`, `signedPdfDownloadUrl`, `signedAt`

### GET /documents/{documentId}/versions/latest:download

Path:
- `/{tenant}/api/documents/{documentId}/versions/latest:download`

Headers:
- `Authorization: Bearer {accessToken}`

Response:
- PDF file download (latest version)

### GET /documents/{documentId}/versions/v{version}:download

Path:
- `/{tenant}/api/documents/{documentId}/versions/v{version}:download`

Headers:
- `Authorization: Bearer {accessToken}`

Response:
- PDF file download (specific version)

## 7) Verify documents (public)

### POST /verify

Path:
- `/{tenant}/api/verify`

Headers:
- `Content-Type: multipart/form-data`

Body:
- `file` (PDF, required)

Response (valid):
- `valid: true`
- plus the same payload as `documents/sign`
- `signatureValid` -> `true | false | null`

Response (invalid):
- `valid: false`
- `reason: "hash_not_found"`
- `signedPdfSha256`

Example response (valid):

```json
{
  "valid": true,
  "signatureValid": true,
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
        "issuedBy": "CN=Test User, emailAddress=test@example.com",
        "validFrom": "2025-01-01",
        "validTo": "2027-01-01"
      }
    }
  ]
}
```

### GET /verify/{chainId}/v{version}

Path:
- `/{tenant}/api/verify/{chainId}/v{version}`

Response:
- `valid: true`
- payload fields (same as sign)
- `signatureValid` -> `true | false | null`

This endpoint is the `verificationUrl` embedded into the QR code.

Signature validation notes:
- `signatureValid: true` means the detached signature matches the signed PDF and the public key.
- `signatureValid: false` means the signature does not match the PDF.
- `signatureValid: null` means signature metadata is missing or certificate not found.

## 8) Common errors

- `401 Unauthorized` -> missing or invalid bearer token.
- `404 Not Found` -> tenant not found, document not found, or missing version.
- `422 Unprocessable Entity` -> validation error (JSON body).
- `500 Internal Server Error` -> tenant database not initialized.

## 9) Frontend and mobile usage

Minimum client flow:
1) `POST /auth/register` (first time) or `POST /auth/login` -> save `accessToken`.
2) Send `Authorization: Bearer {accessToken}` on all protected requests.
3) `POST /documents/sign` -> get `verificationUrl` and `signedPdfDownloadUrl`.
4) Use `verificationUrl` to show QR or verify via `/verify`.

Notes:
- `deviceName` is only a label for the token. It does not affect auth.
- The signed PDF hash is calculated from the rendered, stamped PDF, not the input file.
