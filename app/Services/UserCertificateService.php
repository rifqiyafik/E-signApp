<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserCertificate;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

class UserCertificateService
{
    public function ensureForUser(User $user): UserCertificate
    {
        $existing = UserCertificate::where('global_user_id', $user->global_id)->first();

        if ($existing) {
            return $existing;
        }

        return $this->generateForUser($user);
    }

    public function getSigningCredentials(User $user): array
    {
        $certificate = $this->ensureForUser($user);

        $privateKey = Crypt::decryptString($certificate->private_key_encrypted);
        $passphrase = $certificate->private_key_passphrase_encrypted
            ? Crypt::decryptString($certificate->private_key_passphrase_encrypted)
            : null;

        return [
            'certificate' => $certificate->certificate,
            'privateKey' => $privateKey,
            'privateKeyPassphrase' => $passphrase,
            'publicKey' => $certificate->public_key,
            'certificateFingerprint' => $certificate->certificate_fingerprint,
            'certificateSubject' => $certificate->certificate_subject,
            'certificateSerial' => $certificate->certificate_serial,
            'signatureAlgorithm' => $certificate->signature_algorithm,
        ];
    }

    private function generateForUser(User $user): UserCertificate
    {
        $privateKeyConfig = [
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'private_key_bits' => 2048,
        ];

        $privateKey = openssl_pkey_new($privateKeyConfig);

        if ($privateKey === false) {
            throw new \RuntimeException('Failed to generate private key.');
        }

        $passphrase = Str::random(32);

        if (!openssl_pkey_export($privateKey, $privateKeyPem, $passphrase)) {
            throw new \RuntimeException('Failed to export private key.');
        }

        $dn = [
            'commonName' => $user->name,
            'emailAddress' => $user->email,
            'organizationName' => 'E-Signer',
            'countryName' => 'ID',
        ];

        $csr = openssl_csr_new($dn, $privateKey, ['digest_alg' => 'sha256']);

        if ($csr === false) {
            throw new \RuntimeException('Failed to generate CSR.');
        }

        $certificate = openssl_csr_sign($csr, null, $privateKey, 365, ['digest_alg' => 'sha256']);

        if ($certificate === false) {
            throw new \RuntimeException('Failed to generate X.509 certificate.');
        }

        if (!openssl_x509_export($certificate, $certificatePem)) {
            throw new \RuntimeException('Failed to export X.509 certificate.');
        }

        $publicKeyDetails = openssl_pkey_get_details($privateKey);
        $publicKeyPem = $publicKeyDetails['key'] ?? null;

        if (!$publicKeyPem) {
            throw new \RuntimeException('Failed to extract public key.');
        }

        $certificateInfo = openssl_x509_parse($certificate) ?: [];

        $validFrom = isset($certificateInfo['validFrom_time_t'])
            ? Carbon::createFromTimestamp($certificateInfo['validFrom_time_t'])
            : null;
        $validTo = isset($certificateInfo['validTo_time_t'])
            ? Carbon::createFromTimestamp($certificateInfo['validTo_time_t'])
            : null;

        return UserCertificate::create([
            'global_user_id' => $user->global_id,
            'public_key' => $publicKeyPem,
            'certificate' => $certificatePem,
            'certificate_fingerprint' => hash('sha256', $certificatePem),
            'certificate_serial' => $certificateInfo['serialNumberHex'] ?? null,
            'certificate_subject' => $this->formatCertificateName($certificateInfo['subject'] ?? null),
            'certificate_issuer' => $this->formatCertificateName($certificateInfo['issuer'] ?? null),
            'valid_from' => $validFrom,
            'valid_to' => $validTo,
            'private_key_encrypted' => Crypt::encryptString($privateKeyPem),
            'private_key_passphrase_encrypted' => Crypt::encryptString($passphrase),
            'key_algorithm' => 'RSA',
            'signature_algorithm' => $certificateInfo['signatureTypeLN'] ?? 'sha256WithRSAEncryption',
        ]);
    }

    private function formatCertificateName(?array $name): ?string
    {
        if (!$name) {
            return null;
        }

        $parts = [];
        foreach ($name as $key => $value) {
            $parts[] = $key . '=' . $value;
        }

        return implode(', ', $parts);
    }
}
