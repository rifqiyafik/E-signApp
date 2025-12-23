<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;

class TsaService
{
    private const CERT_PATH = 'pki/tsa_cert.pem';
    private const KEY_PATH = 'pki/tsa_key.pem';

    public function getTsa(): array
    {
        $disk = Storage::disk('central');

        if (!$disk->exists(self::CERT_PATH) || !$disk->exists(self::KEY_PATH)) {
            $this->generateTsa($disk);
        }

        $certificate = $disk->get(self::CERT_PATH);
        $encryptedKey = $disk->get(self::KEY_PATH);
        $privateKey = Crypt::decryptString($encryptedKey);

        $info = openssl_x509_parse($certificate) ?: [];
        $validFrom = isset($info['validFrom_time_t'])
            ? Carbon::createFromTimestamp($info['validFrom_time_t'])
            : null;
        $validTo = isset($info['validTo_time_t'])
            ? Carbon::createFromTimestamp($info['validTo_time_t'])
            : null;

        return [
            'certificate' => $certificate,
            'privateKey' => $privateKey,
            'fingerprint' => hash('sha256', $certificate),
            'subject' => $this->formatCertificateName($info['subject'] ?? null),
            'validFrom' => $validFrom,
            'validTo' => $validTo,
        ];
    }

    public function issue(string $documentHash): array
    {
        $tsa = $this->getTsa();
        $signedAt = now()->toIso8601String();
        $payload = $documentHash . '|' . $signedAt;

        $signatureRaw = null;
        $privateKeyResource = openssl_pkey_get_private($tsa['privateKey']);

        if ($privateKeyResource === false || !openssl_sign($payload, $signatureRaw, $privateKeyResource, OPENSSL_ALGO_SHA256)) {
            throw new \RuntimeException('Failed to issue TSA token.' . $this->getOpenSslError());
        }

        return [
            'hash' => $documentHash,
            'signedAt' => $signedAt,
            'signature' => base64_encode($signatureRaw),
            'algorithm' => 'sha256WithRSAEncryption',
            'tsaFingerprint' => $tsa['fingerprint'],
        ];
    }

    public function verifyToken(array $token, string $expectedHash, ?string $tsaCertificate = null): array
    {
        if (empty($token['hash']) || empty($token['signedAt']) || empty($token['signature'])) {
            return [
                'status' => 'invalid',
                'reason' => 'missing_fields',
                'tsaFingerprint' => $tsaCertificate ? hash('sha256', $tsaCertificate) : ($token['tsaFingerprint'] ?? null),
            ];
        }

        if ($token['hash'] !== $expectedHash) {
            return [
                'status' => 'invalid',
                'reason' => 'hash_mismatch',
                'tsaFingerprint' => $tsaCertificate ? hash('sha256', $tsaCertificate) : ($token['tsaFingerprint'] ?? null),
            ];
        }

        $tsa = $tsaCertificate ? [
            'certificate' => $tsaCertificate,
            'fingerprint' => hash('sha256', $tsaCertificate),
        ] : $this->getTsa();
        $payload = $token['hash'] . '|' . $token['signedAt'];
        $signatureRaw = base64_decode($token['signature'], true);
        if ($signatureRaw === false) {
            return [
                'status' => 'invalid',
                'reason' => 'bad_signature',
                'tsaFingerprint' => $tsa['fingerprint'] ?? null,
            ];
        }

        $publicKey = openssl_pkey_get_public($tsa['certificate'] ?? '');
        if ($publicKey === false) {
            return [
                'status' => 'invalid',
                'reason' => 'tsa_key_missing',
                'tsaFingerprint' => $tsa['fingerprint'] ?? null,
            ];
        }

        $verifyResult = openssl_verify($payload, $signatureRaw, $publicKey, OPENSSL_ALGO_SHA256);
        if ($verifyResult !== 1) {
            return [
                'status' => 'invalid',
                'reason' => 'bad_signature',
                'tsaFingerprint' => $tsa['fingerprint'] ?? null,
            ];
        }

        return [
            'status' => 'valid',
            'reason' => null,
            'signedAt' => $token['signedAt'],
            'tsaFingerprint' => $tsa['fingerprint'] ?? null,
        ];
    }

    private function generateTsa($disk): void
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
            throw new \RuntimeException('Failed to generate TSA key.' . $this->getOpenSslError());
        }

        $exportOptions = [];
        if ($opensslConfig) {
            $exportOptions['config'] = $opensslConfig;
        }

        $exported = $exportOptions
            ? openssl_pkey_export($privateKey, $privateKeyPem, null, $exportOptions)
            : openssl_pkey_export($privateKey, $privateKeyPem);

        if (!$exported) {
            throw new \RuntimeException('Failed to export TSA key.' . $this->getOpenSslError());
        }

        $dn = [
            'commonName' => 'E-Signer TSA',
            'organizationName' => 'E-Signer',
            'countryName' => 'ID',
        ];

        $csrOptions = ['digest_alg' => 'sha256'];
        if ($opensslConfig) {
            $csrOptions['config'] = $opensslConfig;
        }

        $csr = openssl_csr_new($dn, $privateKey, $csrOptions);

        if ($csr === false) {
            throw new \RuntimeException('Failed to generate TSA CSR.' . $this->getOpenSslError());
        }

        $rootCa = app(RootCaService::class)->getRootCa();
        $caCert = $rootCa['certificate'] ?? null;
        $caKeyPem = $rootCa['privateKey'] ?? null;
        $caKey = $caKeyPem ? openssl_pkey_get_private($caKeyPem) : null;

        if ($caKey === false) {
            throw new \RuntimeException('Failed to load Root CA key.' . $this->getOpenSslError());
        }

        $certOptions = ['digest_alg' => 'sha256'];
        if ($opensslConfig) {
            $certOptions['config'] = $opensslConfig;
        }

        $certificate = $caCert && $caKey
            ? openssl_csr_sign($csr, $caCert, $caKey, 3650, $certOptions)
            : openssl_csr_sign($csr, null, $privateKey, 3650, $certOptions);

        if ($certificate === false) {
            throw new \RuntimeException('Failed to generate TSA certificate.' . $this->getOpenSslError());
        }

        if (!openssl_x509_export($certificate, $certificatePem)) {
            throw new \RuntimeException('Failed to export TSA certificate.');
        }

        $disk->makeDirectory('pki');
        $disk->put(self::CERT_PATH, $certificatePem);
        $disk->put(self::KEY_PATH, Crypt::encryptString($privateKeyPem));
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
CN = E-Signer TSA
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
