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

    public function getOpenSslConfigPath(): ?string
    {
        return $this->resolveOpenSslConfig();
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
        $opensslConfig = $this->resolveOpenSslConfig();
        $privateKeyConfig = [
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'private_key_bits' => 2048,
        ];

        if ($opensslConfig) {
            $privateKeyConfig['config'] = $opensslConfig;
        }

        $privateKey = openssl_pkey_new($privateKeyConfig);

        if ($privateKey === false) {
            throw new \RuntimeException('Failed to generate private key.' . $this->getOpenSslError());
        }

        $passphrase = Str::random(32);

        $exportOptions = [];
        if ($opensslConfig) {
            $exportOptions['config'] = $opensslConfig;
        }

        $exported = $exportOptions
            ? openssl_pkey_export($privateKey, $privateKeyPem, $passphrase, $exportOptions)
            : openssl_pkey_export($privateKey, $privateKeyPem, $passphrase);

        if (!$exported) {
            // Fallback: export without passphrase if OpenSSL config is problematic.
            $passphrase = null;
            $exported = $exportOptions
                ? openssl_pkey_export($privateKey, $privateKeyPem, null, $exportOptions)
                : openssl_pkey_export($privateKey, $privateKeyPem);
        }

        if (!$exported) {
            $configInfo = $opensslConfig ? ' (config: ' . $opensslConfig . ')' : '';
            throw new \RuntimeException('Failed to export private key.' . $this->getOpenSslError() . $configInfo);
        }

        $dn = [
            'commonName' => $user->name,
            'emailAddress' => $user->email,
            'organizationName' => 'E-Signer',
            'countryName' => 'ID',
        ];

        $csrOptions = ['digest_alg' => 'sha256'];
        if ($opensslConfig) {
            $csrOptions['config'] = $opensslConfig;
        }

        $csr = openssl_csr_new($dn, $privateKey, $csrOptions);

        if ($csr === false) {
            throw new \RuntimeException('Failed to generate CSR.' . $this->getOpenSslError());
        }

        $certOptions = ['digest_alg' => 'sha256'];
        if ($opensslConfig) {
            $certOptions['config'] = $opensslConfig;
        }

        $certificate = openssl_csr_sign($csr, null, $privateKey, 365, $certOptions);

        if ($certificate === false) {
            throw new \RuntimeException('Failed to generate X.509 certificate.' . $this->getOpenSslError());
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
            'private_key_passphrase_encrypted' => $passphrase ? Crypt::encryptString($passphrase) : null,
            'key_algorithm' => 'RSA',
            'signature_algorithm' => $certificateInfo['signatureTypeLN'] ?? 'sha256WithRSAEncryption',
        ]);
    }


    private function resolveOpenSslConfig(): ?string
    {
        $envPath = env('OPENSSL_CONF');
        if (is_string($envPath) && $envPath !== '' && file_exists($envPath)) {
            $this->exportOpenSslConfigEnv($envPath);
            return $envPath;
        }

        $locations = openssl_get_cert_locations();
        $defaultConf = $locations['default_conf_file'] ?? null;
        if (is_string($defaultConf) && $defaultConf !== '' && file_exists($defaultConf)) {
            $this->exportOpenSslConfigEnv($defaultConf);
            return $defaultConf;
        }

        $defaultConfDir = $locations['default_conf_dir'] ?? null;
        if (is_string($defaultConfDir) && $defaultConfDir !== '') {
            $candidate = rtrim($defaultConfDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'openssl.cnf';
            if (file_exists($candidate)) {
                $this->exportOpenSslConfigEnv($candidate);
                return $candidate;
            }
        }

        $candidates = [
            base_path('openssl.cnf'),
            base_path('extras/ssl/openssl.cnf'),
            base_path('ssl/openssl.cnf'),
        ];

        foreach ($candidates as $candidate) {
            if (file_exists($candidate)) {
                $this->exportOpenSslConfigEnv($candidate);
                return $candidate;
            }
        }

        $fallback = storage_path('app/openssl.cnf');
        if (!file_exists($fallback) || !str_contains((string) @file_get_contents($fallback), 'openssl_conf')) {
            $dir = dirname($fallback);
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            @file_put_contents($fallback, $this->defaultOpenSslConfig());
        }

        if (file_exists($fallback)) {
            $this->exportOpenSslConfigEnv($fallback);
            return $fallback;
        }

        return null;
    }

    private function defaultOpenSslConfig(): string
    {
        return <<<CONF
openssl_conf = openssl_init

[ openssl_init ]
providers = provider_sect

[ provider_sect ]
default = default_sect

[ default_sect ]
activate = 1

[ req ]
default_bits       = 2048
default_md         = sha256
prompt             = no
distinguished_name = req_distinguished_name

[ req_distinguished_name ]
CN = E-Signer
CONF;
    }

    private function exportOpenSslConfigEnv(string $path): void
    {
        putenv('OPENSSL_CONF=' . $path);
    }

    private function getOpenSslError(): string
    {
        $errors = [];

        while ($error = openssl_error_string()) {
            $errors[] = $error;
        }

        return $errors ? ' OpenSSL error: ' . implode(' | ', $errors) : '';
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
