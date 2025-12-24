# E-Signer API Documentation (Multi-tenant)

Dokumentasi lengkap untuk API E-Signer multi-tenant: mulai dari provisioning tenant, autentikasi, workflow tanda tangan digital, hingga verifikasi dokumen.

---

## Daftar Isi

1. [Overview](#1-overview)
2. [Arsitektur & Flow Diagram](#2-arsitektur--flow-diagram)  
3. [Terminologi](#3-terminologi)
4. [Base URL & Headers](#4-base-url--headers)
5. [Quick Start](#5-quick-start)
6. [Central API - Autentikasi](#6-central-api---autentikasi)
7. [Central API - Superadmin](#7-central-api---superadmin)
8. [Tenant API - Public](#8-tenant-api---public)
9. [Tenant API - Autentikasi](#9-tenant-api---autentikasi)
10. [Tenant API - PKI & Sertifikat](#10-tenant-api---pki--sertifikat)
11. [Tenant API - Document Workflow](#11-tenant-api---document-workflow)
12. [Tenant API - Admin User Management](#12-tenant-api---admin-user-management)
13. [Verifikasi Dokumen](#13-verifikasi-dokumen)
14. [Response Objects Reference](#14-response-objects-reference)
15. [Error Responses](#15-error-responses)

---

## 1) Overview

### Teknologi
- **Framework**: Laravel 11 + Stancl Tenancy
- **Auth**: Laravel Passport (OAuth2)
- **PKI**: Root CA internal + TSA internal
- **Database**: Central DB + Tenant DB per-tenant

### Fitur Utama
| Fitur | Deskripsi |
|-------|-----------|
| Multi-Tenancy | Path-based (`/{tenant}/api`) |
| Global Login | 1 email → multiple tenants |
| Workflow Signing | Draft → Need Signature → Waiting → Completed |
| Digital Signature | X.509 certificate + TSA timestamp |
| Verifikasi Publik | Tanpa login, by QR atau file upload |

---

## 2) Arsitektur & Flow Diagram

### 2.1) User & Role Structure

```
┌─────────────────────────────────────────────────────────────┐
│                     SUPERADMIN (Global)                      │
│  • Membuat tenant baru                                       │
│  • Mendaftarkan user ke tenant                               │
│  • Mengelola tenant (edit, suspend, delete)                  │
└─────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────┐
│                     TENANT                                   │
│  ┌─────────────────────┐    ┌─────────────────────┐         │
│  │   Tenant Admin      │    │   Regular User      │         │
│  │   (super_admin)     │    │   (user)            │         │
│  │                     │    │                     │         │
│  │   • Upload draft    │    │   • View inbox      │         │
│  │   • Assign signers  │    │   • Sign documents  │         │
│  │   • Cancel document │    │                     │         │
│  │   • Manage users    │    │                     │         │
│  └─────────────────────┘    └─────────────────────┘         │
└─────────────────────────────────────────────────────────────┘
```

### 2.2) Login Flow

```
┌───────┐          ┌─────────────┐          ┌─────────────┐
│ User  │          │ Central API │          │ Tenant API  │
└───┬───┘          └──────┬──────┘          └──────┬──────┘
    │                     │                        │
    │ 1. POST /api/auth/login                      │
    │ ────────────────────►                        │
    │     {email, password}                        │
    │                     │                        │
    │ ◄────────────────────                        │
    │ {centralToken, tenants[]}                    │
    │                     │                        │
    │ 2. POST /api/auth/select-tenant              │
    │ ────────────────────►                        │
    │     {tenant: "demo"}                         │
    │                     │                        │
    │ ◄────────────────────                        │
    │ {tenantToken}       │                        │
    │                     │                        │
    │ 3. GET /{tenant}/api/auth/me ─────────────────────────►
    │                     │                        │
    │ ◄─────────────────────────────────────────────
    │ {profile, tenant, membership}                │
    │                     │                        │
```

### 2.3) Document Workflow Flow

```
┌─────────────────────────────────────────────────────────────────┐
│                    DOCUMENT WORKFLOW                             │
└─────────────────────────────────────────────────────────────────┘

┌─────────────┐     ┌─────────────┐     ┌─────────────┐     ┌─────────────┐
│   DRAFT     │     │ NEED_SIGN   │     │  WAITING    │     │ COMPLETED   │
│             │────►│             │────►│             │────►│             │
│ Upload PDF  │     │ Signer #1   │     │ Signer #2   │     │ All signed  │
│             │     │ must sign   │     │ must sign   │     │             │
└─────────────┘     └─────────────┘     └─────────────┘     └─────────────┘
      │                   │                   │                   │
      │                   │                   │                   │
      ▼                   ▼                   ▼                   ▼
POST /drafts        POST /signers       POST /sign          GET /inbox
                                        (by each            (completed)
                                         signer)

Status Flow:
─────────────────────────────────────────────────────────────────────────────
draft ──► need_signature ──► waiting ──► waiting ──► ... ──► completed
           (after assign)   (after 1st  (after 2nd        (after last
                             signer)     signer)            signer)

                    ┌──► canceled (by admin)
                    │
                    └──► expired (by system, if expiresAt passed)
```

### 2.4) Signing Pipeline

```
┌─────────┐    ┌─────────┐    ┌─────────┐    ┌─────────┐    ┌─────────┐
│ Draft   │───►│ Stamp   │───►│  Sign   │───►│  TSA    │───►│  Store  │
│ PDF     │    │ QR Code │    │ (X.509) │    │Timestamp│    │ Version │
└─────────┘    └─────────┘    └─────────┘    └─────────┘    └─────────┘
                    │              │              │              │
                    ▼              ▼              ▼              ▼
               QR berisi     Certificate    Timestamp       LTV Snapshot
             verificationUrl   + Chain      Response        + Hash
```

---

## 3) Terminologi

| Term | Deskripsi |
|------|-----------|
| `tenant` | Slug atau ID tenant (ULID) di path URL |
| `documentId` | ID unik dokumen |
| `chainId` | ID rantai dokumen (untuk verifikasi) |
| `versionNumber` | Nomor versi dokumen (mulai dari 1) |
| `centralAccessToken` | Token untuk Central API |
| `tenantAccessToken` | Token untuk Tenant API |
| `signer_index` | Urutan tanda tangan (1, 2, 3, ...) |

### Document Status
| Status | Deskripsi |
|--------|-----------|
| `draft` | Dokumen baru diupload, belum ada urutan signer |
| `need_signature` | Siap ditandatangani oleh signer pertama |
| `waiting` | Menunggu signer berikutnya |
| `completed` | Semua urutan selesai |
| `canceled` | Dibatalkan oleh admin |
| `expired` | Melewati batas waktu |

### Signer Status
| Status | Deskripsi |
|--------|-----------|
| `queued` | Menunggu giliran |
| `active` | Giliran untuk menandatangani |
| `signed` | Sudah menandatangani |
| `canceled` | Dokumen dibatalkan |

---

## 4) Base URL & Headers

### Base URL

```
Central API : http://127.0.0.1:8000/api
Tenant API  : http://127.0.0.1:8000/{tenant}/api
```

Contoh tenant identifier:
- Slug: `http://127.0.0.1:8000/demo/api`
- ID: `http://127.0.0.1:8000/01KCTVRDZ7F51PJHM5C70PK00W/api`

### Required Headers

**Central API (Protected)**
```http
Authorization: Bearer {centralAccessToken}
Content-Type: application/json
```

**Tenant API (Protected)**
```http
Authorization: Bearer {tenantAccessToken}
Content-Type: application/json
```

**File Upload**
```http
Authorization: Bearer {token}
Content-Type: multipart/form-data
```

---

## 5) Quick Start

### Step 1: Buat Superadmin

Jalankan seeder untuk membuat superadmin:

```bash
php artisan db:seed --class=SuperAdminSeeder
```

Atau dengan custom credentials di `.env`:

```env
SUPERADMIN_EMAIL=admin@example.com
SUPERADMIN_PASSWORD=secret123
SUPERADMIN_NAME=Superadmin
```

Lalu jalankan:

```bash
php artisan db:seed --class=SuperAdminSeeder
```

**Default credentials (jika tidak diset di .env):**
- Email: `admin@example.com`
- Password: `secret123`

### Step 2: Login Global

```bash
curl -X POST http://127.0.0.1:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "admin@example.com",
    "password": "secret123"
  }'
```

Response:
```json
{
  "accessToken": "eyJ0eXAi...",
  "user": {
    "userId": "01JFXXX...",
    "name": "Superadmin",
    "email": "admin@example.com",
    "isSuperadmin": true
  },
  "tenants": []
}
```

### Step 3: Buat Tenant + User Pertama

```bash
curl -X POST http://127.0.0.1:8000/api/tenants/register \
  -H "Authorization: Bearer {centralAccessToken}" \
  -H "Content-Type: application/json" \
  -d '{
    "tenantName": "PT Demo Company",
    "tenantSlug": "demo",
    "name": "Admin Demo",
    "email": "admin@demo.com",
    "password": "secret123",
    "password_confirmation": "secret123",
    "role": "super_admin"
  }'
```

Response:
```json
{
  "accessToken": "eyJ0eXAi...",
  "tenantId": "01KCTVRDZ7F51PJHM5C70PK00W",
  "tenantSlug": "demo",
  "userId": "01KCTVRDNTV8QZDACE56SX1HXG"
}
```

### Step 4: Gunakan Tenant API

```bash
curl -X GET http://127.0.0.1:8000/demo/api/auth/me \
  -H "Authorization: Bearer {tenantAccessToken}"
```

---

## 6) Central API - Autentikasi

### POST /api/auth/login

Login global dengan email dan password.

**Request:**
```http
POST /api/auth/login
Content-Type: application/json

{
  "email": "user@example.com",
  "password": "secret123"
}
```

**Response (200):**
```json
{
  "accessToken": "eyJ0eXAi...",
  "user": {
    "userId": "01JFXXX...",
    "name": "User Name",
    "email": "user@example.com",
    "isSuperadmin": false
  },
  "tenants": [
    {
      "id": "01KCTVRDZ7F51PJHM5C70PK00W",
      "name": "PT Demo Company",
      "slug": "demo",
      "role": "super_admin",
      "isOwner": true
    },
    {
      "id": "01KCTVRDZ7F51PJHM5C70PK00X",
      "name": "PT Another Company",
      "slug": "another",
      "role": "user",
      "isOwner": false
    }
  ]
}
```

---

### GET /api/auth/me

Mendapatkan info user yang sedang login.

**Request:**
```http
GET /api/auth/me
Authorization: Bearer {centralAccessToken}
```

**Response (200):**
```json
{
  "user": {
    "userId": "01JFXXX...",
    "name": "User Name",
    "email": "user@example.com",
    "isSuperadmin": false
  },
  "tenants": [...]
}
```

---

### POST /api/auth/select-tenant

Pilih tenant dan dapatkan tenant token.

**Request:**
```http
POST /api/auth/select-tenant
Authorization: Bearer {centralAccessToken}
Content-Type: application/json

{
  "tenant": "demo"
}
```

**Response (200):**
```json
{
  "accessToken": "eyJ0eXAi...",
  "tenant": {
    "id": "01KCTVRDZ7F51PJHM5C70PK00W",
    "name": "PT Demo Company",
    "slug": "demo",
    "role": "super_admin",
    "isOwner": true
  }
}
```

---

### POST /api/auth/logout

Logout dan revoke token.

**Request:**
```http
POST /api/auth/logout
Authorization: Bearer {centralAccessToken}
```

**Response (200):**
```json
{
  "message": "Logged out successfully."
}
```

---

## 7) Central API - Superadmin

> ⚠️ Semua endpoint ini memerlukan `is_superadmin = true` pada user.

### POST /api/tenants/register

Buat tenant baru beserta user pertama.

**Request:**
```http
POST /api/tenants/register
Authorization: Bearer {centralAccessToken}
Content-Type: application/json

{
  "tenantName": "PT Demo Company",
  "tenantSlug": "demo",
  "name": "Admin Demo",
  "email": "admin@demo.com",
  "password": "secret123",
  "password_confirmation": "secret123",
  "role": "super_admin"
}
```

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| tenantName | string | ✅ | Nama tenant |
| tenantSlug | string | ❌ | Slug (auto-generate jika kosong) |
| name | string | ✅ | Nama user pertama |
| email | string | ✅ | Email user pertama |
| password | string | ✅ | Password (min 8 char) |
| password_confirmation | string | ✅ | Konfirmasi password |
| role | string | ❌ | Role di tenant (default: super_admin) |

**Response (201):**
```json
{
  "accessToken": "eyJ0eXAi...",
  "tenantId": "01KCTVRDZ7F51PJHM5C70PK00W",
  "tenantSlug": "demo",
  "userId": "01KCTVRDNTV8QZDACE56SX1HXG"
}
```

**Error Responses:**
- `409 Conflict` - `email_already_registered` / `tenant_name_exists` / `tenant_slug_exists`

---

### GET /api/superadmin/tenants

List semua tenant.

**Request:**
```http
GET /api/superadmin/tenants
Authorization: Bearer {centralAccessToken}
```

**Query Parameters:**
| Param | Type | Description |
|-------|------|-------------|
| status | string | Filter by status (active/suspended/deleted) |
| search | string | Search by name/slug/code |

**Response (200):**
```json
{
  "tenants": [
    {
      "id": "01KCTVRDZ7F51PJHM5C70PK00W",
      "name": "PT Demo Company",
      "slug": "demo",
      "code": "ABC123XYZ",
      "plan": "free",
      "status": "active",
      "suspendedAt": null,
      "suspendedByUserId": null,
      "createdAt": "2025-12-24T10:00:00+07:00"
    }
  ]
}
```

---

### PATCH /api/superadmin/tenants/{tenant}

Update tenant.

**Request:**
```http
PATCH /api/superadmin/tenants/demo
Authorization: Bearer {centralAccessToken}
Content-Type: application/json

{
  "name": "PT Demo Company Updated",
  "status": "suspended"
}
```

| Field | Type | Description |
|-------|------|-------------|
| name | string | Nama baru |
| slug | string | Slug baru |
| plan | string | Plan (free/pro/enterprise) |
| status | string | Status (active/suspended/deleted) |

---

### DELETE /api/superadmin/tenants/{tenant}

Soft delete tenant (status = deleted).

**Request:**
```http
DELETE /api/superadmin/tenants/demo
Authorization: Bearer {centralAccessToken}
```

---

### POST /api/superadmin/tenants/{tenant}/users

Buat user baru dan tambahkan ke tenant.

**Request:**
```http
POST /api/superadmin/tenants/demo/users
Authorization: Bearer {centralAccessToken}
Content-Type: application/json

{
  "name": "New User",
  "email": "newuser@example.com",
  "password": "secret123",
  "password_confirmation": "secret123",
  "role": "user"
}
```

---

### POST /api/superadmin/tenants/{tenant}/users/assign

Assign user yang sudah ada ke tenant.

**Request:**
```http
POST /api/superadmin/tenants/demo/users/assign
Authorization: Bearer {centralAccessToken}
Content-Type: application/json

{
  "user": "existinguser@example.com",
  "role": "user"
}
```

---

## 8) Tenant API - Public

> Endpoint ini tidak memerlukan autentikasi.

### GET /{tenant}/api/public/info

Mendapatkan info tenant.

**Request:**
```http
GET /demo/api/public/info
```

**Response (200):**
```json
{
  "tenant": {
    "id": "01KCTVRDZ7F51PJHM5C70PK00W",
    "name": "PT Demo Company",
    "slug": "demo"
  }
}
```

---

## 9) Tenant API - Autentikasi

### POST /{tenant}/api/auth/register

Register user baru di tenant (jika diizinkan).

**Request:**
```http
POST /demo/api/auth/register
Content-Type: application/json

{
  "name": "New User",
  "email": "newuser@demo.com",
  "password": "secret123",
  "password_confirmation": "secret123"
}
```

**Response (201):**
```json
{
  "accessToken": "eyJ0eXAi...",
  "tenantId": "01KCTVRDZ7F51PJHM5C70PK00W",
  "userId": "01KCTVRDNTV8QZDACE56SX1HXG"
}
```

> 📝 Register otomatis membuat keypair + X.509 certificate untuk user.

---

### POST /{tenant}/api/auth/login

Login langsung ke tenant.

**Request:**
```http
POST /demo/api/auth/login
Content-Type: application/json

{
  "email": "user@demo.com",
  "password": "secret123"
}
```

**Response (200):**
```json
{
  "accessToken": "eyJ0eXAi...",
  "tenantId": "01KCTVRDZ7F51PJHM5C70PK00W",
  "userId": "01KCTVRDNTV8QZDACE56SX1HXG"
}
```

---

### GET /{tenant}/api/auth/me

Info user dalam konteks tenant.

**Request:**
```http
GET /demo/api/auth/me
Authorization: Bearer {tenantAccessToken}
```

**Response (200):**
```json
{
  "profile": {
    "userId": "01KCTVRDNTV8QZDACE56SX1HXG",
    "name": "User Demo",
    "email": "user@demo.com"
  },
  "tenant": {
    "id": "01KCTVRDZ7F51PJHM5C70PK00W",
    "name": "PT Demo Company",
    "slug": "demo"
  },
  "membership": {
    "role": "super_admin",
    "isOwner": true,
    "joinedAt": "2025-12-24T10:00:00+07:00"
  }
}
```

---

## 10) Tenant API - PKI & Sertifikat

### GET /{tenant}/api/pki/root-ca

Mendapatkan Root CA certificate (public).

**Request:**
```http
GET /demo/api/pki/root-ca
```

**Response (200):**
```json
{
  "certificate": "-----BEGIN CERTIFICATE-----\n...\n-----END CERTIFICATE-----",
  "fingerprint": "A1B2C3D4...",
  "subject": "CN=E-Signer Root CA, O=E-Signer, C=ID",
  "validFrom": "2025-01-01T00:00:00+00:00",
  "validTo": "2035-01-01T00:00:00+00:00"
}
```

---

### GET /{tenant}/api/pki/certificates/me

Mendapatkan sertifikat user yang sedang login.

**Request:**
```http
GET /demo/api/pki/certificates/me
Authorization: Bearer {tenantAccessToken}
```

**Response (200):**
```json
{
  "certificatePem": "-----BEGIN CERTIFICATE-----\n...\n-----END CERTIFICATE-----",
  "fingerprint": "2C8C3B4B...",
  "serial": "1F9A2B3C",
  "subject": "CN=User Demo, emailAddress=user@demo.com",
  "issuer": "CN=E-Signer Root CA, O=E-Signer, C=ID",
  "validFrom": "2025-12-24T00:00:00+00:00",
  "validTo": "2027-12-24T00:00:00+00:00",
  "revokedAt": null,
  "revokedReason": null
}
```

---

### POST /{tenant}/api/pki/certificates/me/enroll

Membuat sertifikat baru (jika belum ada atau sudah revoked).

**Request:**
```http
POST /demo/api/pki/certificates/me/enroll
Authorization: Bearer {tenantAccessToken}
```

---

### POST /{tenant}/api/pki/certificates/me/renew

Rotasi keypair dan sertifikat baru.

**Request:**
```http
POST /demo/api/pki/certificates/me/renew
Authorization: Bearer {tenantAccessToken}
```

---

### POST /{tenant}/api/pki/certificates/me/revoke

Revoke sertifikat.

**Request:**
```http
POST /demo/api/pki/certificates/me/revoke
Authorization: Bearer {tenantAccessToken}
Content-Type: application/json

{
  "reason": "Key compromised"
}
```

---

## 11) Tenant API - Document Workflow

### 11.1) Workflow Overview

```
┌────────────────────────────────────────────────────────────────────────┐
│                        SIGNING WORKFLOW                                 │
├────────────────────────────────────────────────────────────────────────┤
│                                                                         │
│  STEP 1: Upload Draft                                                   │
│  POST /documents/drafts                                                 │
│  → Status: draft                                                        │
│                                                                         │
│  STEP 2: Assign Signers (dengan urutan)                                 │
│  POST /documents/{id}/signers                                           │
│  → Status: need_signature                                               │
│  → Signer #1: active                                                    │
│  → Signer #2, #3, ...: queued                                           │
│                                                                         │
│  STEP 3: Sign by Each Signer (sesuai urutan)                            │
│  POST /documents/{id}/sign                                              │
│  → After Signer #1: Status waiting, Signer #2 becomes active            │
│  → After Signer #2: Status waiting, Signer #3 becomes active            │
│  → After Last Signer: Status completed                                  │
│                                                                         │
│  OPTIONAL: Cancel Document                                              │
│  POST /documents/{id}/cancel                                            │
│  → Status: canceled                                                     │
│                                                                         │
└────────────────────────────────────────────────────────────────────────┘
```

---

### POST /{tenant}/api/documents/drafts

Upload dokumen draft.

**Request:**
```http
POST /demo/api/documents/drafts
Authorization: Bearer {tenantAccessToken}
Content-Type: multipart/form-data

file: [PDF file]
```

**Response (201):**
```json
{
  "document": {
    "documentId": "01JGXXX...",
    "chainId": "01JGYYY...",
    "status": "draft",
    "draftSha256": "a1b2c3d4e5f6...",
    "draftUploadedAt": "2025-12-24T10:30:00+07:00"
  }
}
```

---

### POST /{tenant}/api/documents/{documentId}/signers

Tentukan urutan tanda tangan.

**Request:**
```http
POST /demo/api/documents/01JGXXX.../signers
Authorization: Bearer {tenantAccessToken}
Content-Type: application/json

{
  "signers": [
    {
      "user": "direktur@demo.com",
      "role": "Direktur"
    },
    {
      "user": "manager@demo.com",
      "role": "Manager"
    },
    {
      "user": "staff@demo.com",
      "role": "Staff"
    }
  ],
  "expiresAt": "2025-12-31T23:59:59+07:00"
}
```

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| signers | array | ✅ | Array of signer objects |
| signers[].user | string | ✅ | Email atau global_id user |
| signers[].role | string | ❌ | Label role (Direktur, Manager, dll) |
| expiresAt | datetime | ❌ | Batas waktu signing |

**Response (200):**
```json
{
  "document": {
    "documentId": "01JGXXX...",
    "chainId": "01JGYYY...",
    "status": "need_signature",
    "currentSignerIndex": 1,
    "expiresAt": "2025-12-31T23:59:59+07:00"
  },
  "signers": [
    {
      "index": 1,
      "userId": "01JFAAA...",
      "name": "Direktur Demo",
      "email": "direktur@demo.com",
      "role": "Direktur",
      "status": "active"
    },
    {
      "index": 2,
      "userId": "01JFBBB...",
      "name": "Manager Demo",
      "email": "manager@demo.com",
      "role": "Manager",
      "status": "queued"
    },
    {
      "index": 3,
      "userId": "01JFCCC...",
      "name": "Staff Demo",
      "email": "staff@demo.com",
      "role": "Staff",
      "status": "queued"
    }
  ]
}
```

> 📝 Setelah assign signers:
> - Signer #1 (index 1) mendapat status `active`
> - Signer #2, #3, ... mendapat status `queued`
> - Notifikasi dikirim ke signer #1

---

### POST /{tenant}/api/documents/{documentId}/sign

Tanda tangani dokumen (oleh signer yang giliran aktif).

**Request:**
```http
POST /demo/api/documents/01JGXXX.../sign
Authorization: Bearer {tenantAccessToken}
Content-Type: application/json

{
  "consent": true,
  "idempotencyKey": "unique-key-123"
}
```

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| consent | boolean | ✅ | Harus `true` |
| idempotencyKey | string | ❌ | Prevent duplicate signing |

**Response (200):**
```json
{
  "documentId": "01JGXXX...",
  "chainId": "01JGYYY...",
  "versionNumber": 1,
  "verificationUrl": "http://127.0.0.1:8000/demo/api/verify/01JGYYY.../v1",
  "signedPdfDownloadUrl": "http://127.0.0.1:8000/demo/api/documents/01JGXXX.../versions/v1:download",
  "signedPdfSha256": "ad8f6b6d4a...",
  "documentStatus": "waiting",
  "currentSignerIndex": 2,
  "signature": {
    "algorithm": "sha256WithRSAEncryption",
    "certificateFingerprint": "2C8C3B4B...",
    "certificateSubject": "CN=Direktur Demo, emailAddress=direktur@demo.com",
    "certificateSerial": "1F9A2B3C"
  },
  "tsa": {
    "signedAt": "2025-12-24T10:35:00+07:00",
    "fingerprint": "7D9C8E...",
    "algorithm": "sha256WithRSAEncryption"
  },
  "signers": [
    {
      "index": 1,
      "name": "Direktur Demo",
      "email": "direktur@demo.com",
      "role": "Direktur",
      "status": "signed",
      "signedAt": "2025-12-24T10:35:00+07:00"
    },
    {
      "index": 2,
      "name": "Manager Demo",
      "email": "manager@demo.com",
      "role": "Manager",
      "status": "active"
    }
  ]
}
```

> 📝 Setelah sign:
> - Signer yang baru sign: `status = signed`
> - Signer berikutnya: `status = active`
> - Jika signer terakhir: `documentStatus = completed`

---

### GET /{tenant}/api/documents/inbox

Mendapatkan daftar dokumen untuk user.

**Request:**
```http
GET /demo/api/documents/inbox
Authorization: Bearer {tenantAccessToken}
```

**Response (200):**
```json
{
  "needSignature": [
    {
      "documentId": "01JGXXX...",
      "chainId": "01JGYYY...",
      "title": "Kontrak Kerjasama.pdf",
      "status": "need_signature",
      "currentSignerIndex": 1,
      "yourSignerIndex": 1,
      "expiresAt": "2025-12-31T23:59:59+07:00",
      "createdAt": "2025-12-24T10:00:00+07:00"
    }
  ],
  "waiting": [
    {
      "documentId": "01JGAAA...",
      "chainId": "01JGBBB...",
      "title": "Proposal Project.pdf",
      "status": "waiting",
      "currentSignerIndex": 2,
      "yourSignerIndex": 3,
      "expiresAt": "2025-12-31T23:59:59+07:00"
    }
  ],
  "completed": [
    {
      "documentId": "01JGCCC...",
      "chainId": "01JGDDD...",
      "title": "MOU Completed.pdf",
      "status": "completed",
      "completedAt": "2025-12-20T15:00:00+07:00"
    }
  ]
}
```

| Category | Description |
|----------|-------------|
| `needSignature` | Dokumen yang perlu Anda tandatangani SEKARANG |
| `waiting` | Dokumen yang menunggu signer lain |
| `completed` | Dokumen yang sudah selesai semua |

---

### POST /{tenant}/api/documents/{documentId}/cancel

Batalkan dokumen (oleh tenant admin).

**Request:**
```http
POST /demo/api/documents/01JGXXX.../cancel
Authorization: Bearer {tenantAccessToken}
```

**Response (200):**
```json
{
  "document": {
    "documentId": "01JGXXX...",
    "status": "canceled",
    "canceledAt": "2025-12-24T11:00:00+07:00"
  }
}
```

---

### GET /{tenant}/api/documents/{documentId}

Detail dokumen.

**Request:**
```http
GET /demo/api/documents/01JGXXX...
Authorization: Bearer {tenantAccessToken}
```

**Response (200):**
```json
{
  "documentId": "01JGXXX...",
  "chainId": "01JGYYY...",
  "status": "completed",
  "latestVersion": {
    "versionNumber": 3,
    "signedPdfDownloadUrl": "http://127.0.0.1:8000/demo/api/documents/01JGXXX.../versions/v3:download",
    "signedPdfSha256": "xyz123...",
    "signedAt": "2025-12-24T12:00:00+07:00"
  },
  "signers": [...]
}
```

---

### GET /{tenant}/api/documents/{documentId}/versions

List semua versi dokumen.

**Request:**
```http
GET /demo/api/documents/01JGXXX.../versions
Authorization: Bearer {tenantAccessToken}
```

**Response (200):**
```json
{
  "documentId": "01JGXXX...",
  "chainId": "01JGYYY...",
  "versions": [
    {
      "versionNumber": 1,
      "signedPdfSha256": "abc123...",
      "signedPdfDownloadUrl": "http://127.0.0.1:8000/demo/api/documents/01JGXXX.../versions/v1:download",
      "signedAt": "2025-12-24T10:35:00+07:00",
      "signedBy": {
        "name": "Direktur Demo",
        "email": "direktur@demo.com"
      }
    },
    {
      "versionNumber": 2,
      "signedPdfSha256": "def456...",
      "signedPdfDownloadUrl": "http://127.0.0.1:8000/demo/api/documents/01JGXXX.../versions/v2:download",
      "signedAt": "2025-12-24T11:00:00+07:00",
      "signedBy": {
        "name": "Manager Demo",
        "email": "manager@demo.com"
      }
    }
  ]
}
```

---

### GET /{tenant}/api/documents/{documentId}/versions/v{version}:download

Download versi tertentu.

**Request:**
```http
GET /demo/api/documents/01JGXXX.../versions/v1:download
Authorization: Bearer {tenantAccessToken}
```

**Response:** File PDF

---

### GET /{tenant}/api/documents/{documentId}/versions/latest:download

Download versi terbaru.

**Request:**
```http
GET /demo/api/documents/01JGXXX.../versions/latest:download
Authorization: Bearer {tenantAccessToken}
```

**Response:** File PDF

---

## 12) Tenant API - Admin User Management

> ⚠️ Endpoint ini hanya bisa diakses oleh user dengan role `super_admin` di tenant.

### POST /{tenant}/api/admin/users

Buat user baru dan tambahkan ke tenant.

**Request:**
```http
POST /demo/api/admin/users
Authorization: Bearer {tenantAccessToken}
Content-Type: application/json

{
  "name": "New User",
  "email": "newuser@demo.com",
  "password": "secret123",
  "password_confirmation": "secret123",
  "role": "user"
}
```

**Response (201):**
```json
{
  "user": {
    "userId": "01JFNNN...",
    "name": "New User",
    "email": "newuser@demo.com",
    "role": "user"
  }
}
```

---

### POST /{tenant}/api/admin/users/assign

Assign user yang sudah ada ke tenant ini.

**Request:**
```http
POST /demo/api/admin/users/assign
Authorization: Bearer {tenantAccessToken}
Content-Type: application/json

{
  "user": "existinguser@example.com",
  "role": "user"
}
```

**Response (200):**
```json
{
  "user": {
    "userId": "01JFEEE...",
    "name": "Existing User",
    "email": "existinguser@example.com",
    "role": "user"
  }
}
```

---

## 13) Verifikasi Dokumen

> Semua endpoint verifikasi tidak memerlukan login (public).

### POST /{tenant}/api/verify

Verifikasi dokumen dengan upload file.

**Request:**
```http
POST /demo/api/verify
Content-Type: multipart/form-data

file: [Signed PDF file]
```

**Response (200) - Valid:**
```json
{
  "valid": true,
  "documentId": "01JGXXX...",
  "chainId": "01JGYYY...",
  "versionNumber": 1,
  "signedPdfSha256": "abc123...",
  "signatureValid": true,
  "certificateStatus": "valid",
  "rootCaFingerprint": "A1B2C3...",
  "certificateRevokedAt": null,
  "certificateRevokedReason": null,
  "tsaStatus": "valid",
  "tsaSignedAt": "2025-12-24T10:35:00+07:00",
  "tsaFingerprint": "7D9C8E...",
  "ltvStatus": "ready",
  "signers": [...]
}
```

**Response (200) - Invalid:**
```json
{
  "valid": false,
  "reason": "hash_not_found",
  "signedPdfSha256": "xyz789..."
}
```

| reason | Deskripsi |
|--------|-----------|
| `hash_not_found` | Dokumen tidak ditemukan di sistem |
| `hash_mismatch` | Hash tidak cocok dengan versi yang tersimpan |

---

### GET /{tenant}/api/verify/{chainId}/v{version}

Verifikasi berdasarkan chain ID dan versi (URL dari QR Code).

**Request:**
```http
GET /demo/api/verify/01JGYYY.../v1
```

**Response (200):**
```json
{
  "valid": true,
  "chainId": "01JGYYY...",
  "versionNumber": 1,
  "signedPdfSha256": "abc123...",
  "signatureValid": true,
  "certificateStatus": "valid",
  ...
}
```

> 📝 URL ini adalah `verificationUrl` yang ditanam di QR Code pada dokumen.

---

### POST /{tenant}/api/verify/{chainId}/v{version}

Verifikasi file dengan cross-check terhadap versi tertentu.

**Request:**
```http
POST /demo/api/verify/01JGYYY.../v1
Content-Type: multipart/form-data

file: [PDF file to verify]
```

**Response (200) - Match:**
```json
{
  "valid": true,
  "chainId": "01JGYYY...",
  "versionNumber": 1,
  ...
}
```

**Response (200) - Mismatch:**
```json
{
  "valid": false,
  "reason": "hash_mismatch",
  "signedPdfSha256": "uploaded_file_hash...",
  "expectedSignedPdfSha256": "expected_hash_from_server..."
}
```

---

## 14) Response Objects Reference

### Signature Object
```json
{
  "algorithm": "sha256WithRSAEncryption",
  "certificateFingerprint": "2C8C3B4B...",
  "certificateSubject": "CN=User Name, emailAddress=user@example.com",
  "certificateSerial": "1F9A2B3C"
}
```

### TSA Object
```json
{
  "signedAt": "2025-12-24T10:35:00+07:00",
  "fingerprint": "7D9C8E...",
  "algorithm": "sha256WithRSAEncryption"
}
```

### LTV Object
```json
{
  "enabled": true,
  "generatedAt": "2025-12-24T10:35:00+07:00",
  "rootCaFingerprint": "A1B2C3...",
  "tsaFingerprint": "7D9C8E..."
}
```

### Signer Object
```json
{
  "index": 1,
  "tenantId": "demo",
  "userId": "01JFAAA...",
  "name": "User Name",
  "email": "user@example.com",
  "role": "Direktur",
  "status": "signed",
  "assignedAt": "2025-12-24T10:00:00+07:00",
  "assignedByUserId": "01JFBBB...",
  "signedAt": "2025-12-24T10:35:00+07:00",
  "certificate": {
    "serial": "1F9A2B3C",
    "issuedBy": "CN=E-Signer Root CA",
    "validFrom": "2025-01-01",
    "validTo": "2027-01-01"
  }
}
```

### Certificate Status Values
| Status | Description |
|--------|-------------|
| `valid` | Sertifikat valid dan aktif |
| `expired` | Sertifikat sudah kadaluarsa |
| `revoked` | Sertifikat sudah direvoke |
| `untrusted` | Tidak bisa di-trust (chain rusak) |
| `not_yet_valid` | Sertifikat belum berlaku |
| `missing` | Tidak ada sertifikat |

---

## 15) Error Responses

### Standard Error Format
```json
{
  "message": "Error message",
  "code": "error_code",
  "errors": {
    "field": ["Field-specific error message"]
  }
}
```

### Common HTTP Status Codes

| Code | Description | Example |
|------|-------------|---------|
| 400 | Bad Request | Invalid input format |
| 401 | Unauthorized | Token tidak valid / expired |
| 403 | Forbidden | Tidak punya akses (bukan giliran sign) |
| 404 | Not Found | Resource tidak ditemukan |
| 409 | Conflict | Email sudah terdaftar |
| 422 | Unprocessable Entity | Validation failed |
| 500 | Server Error | Internal error |

### Example Error Responses

**401 Unauthorized:**
```json
{
  "message": "Unauthenticated.",
  "code": "unauthenticated"
}
```

**403 Forbidden (Not Your Turn):**
```json
{
  "message": "Bukan giliran Anda untuk menandatangani dokumen ini.",
  "code": "not_your_turn"
}
```

**409 Conflict:**
```json
{
  "message": "Email sudah terdaftar. Silakan login.",
  "code": "email_already_registered",
  "errors": {
    "email": ["Email sudah terdaftar. Silakan login."]
  }
}
```

**422 Validation Error:**
```json
{
  "message": "The email field is required.",
  "code": "validation_failed",
  "errors": {
    "email": ["Email wajib diisi."]
  }
}
```

---

## Quick Reference Card

### Authentication Flow
```
1. POST /api/auth/login          → Get centralToken + tenants[]
2. POST /api/auth/select-tenant  → Get tenantToken
3. Use tenantToken for all tenant API calls
```

### Document Signing Flow
```
1. POST /documents/drafts              → Upload PDF, get documentId
2. POST /documents/{id}/signers        → Assign signers with order
3. POST /documents/{id}/sign           → Each signer signs in order
4. GET  /documents/inbox               → Check document status
```

### Verification Flow
```
Option A: POST /verify                       → Upload file to verify
Option B: GET  /verify/{chainId}/v{version}  → Use QR code URL
Option C: POST /verify/{chainId}/v{version}  → Upload + cross-check
```
