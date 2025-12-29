<?php

namespace App\Services;

use setasign\Fpdi\Tcpdf\Fpdi;

class DocumentStampService
{
    public function stamp(
        string $inputPath,
        string $outputPath,
        string $verificationUrl,
        array $context = [],
        ?array $signature = null
    ): void {
        $pdf = new Fpdi('P', 'mm');
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(0, 0, 0);
        $pdf->SetAutoPageBreak(false, 0);

        $tempFiles = [];
        if ($signature) {
            $tempFiles = $this->applySignature($pdf, $signature);
        }

        try {
            $pageCount = $pdf->setSourceFile($inputPath);

            for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                $templateId = $pdf->importPage($pageNo);
                $size = $pdf->getTemplateSize($templateId);
                $orientation = $size['width'] > $size['height'] ? 'L' : 'P';

                $pdf->AddPage($orientation, [$size['width'], $size['height']]);
                $pdf->useTemplate($templateId);

                if ($pageNo === $pageCount) {
                    $this->applyStamp($pdf, $size, $verificationUrl, $context);
                }
            }

            $pdf->Output($outputPath, 'F');
        } finally {
            $this->cleanupTempFiles($tempFiles);
        }
    }

    private function applySignature(Fpdi $pdf, array $signature): array
    {
        $certificate = $signature['certificate'] ?? null;
        $privateKey = $signature['privateKey'] ?? null;
        $extraCerts = $signature['caCertificate'] ?? '';

        if (!$certificate || !$privateKey) {
            return [];
        }

        $passphrase = $signature['privateKeyPassphrase'] ?? '';
        $info = $signature['info'] ?? [];

        $tempFiles = [];
        $extraPath = null;
        if ($extraCerts) {
            $extraPath = $this->writeTempPem($extraCerts, 'extra_');
            if ($extraPath) {
                $tempFiles[] = $extraPath;
            } else {
                $this->cleanupTempFiles($tempFiles);
                throw new \RuntimeException('Failed to prepare signing certificate.');
            }
        }

        $keyPassphrase = is_string($passphrase) ? $passphrase : '';
        $unencryptedKey = $this->exportUnencryptedPrivateKey($privateKey, $keyPassphrase);
        if ($unencryptedKey) {
            $privateKey = $unencryptedKey;
            $keyPassphrase = '';
        }

        $pdf->setSignature(
            $certificate,
            $privateKey,
            $keyPassphrase,
            $extraPath ?: '',
            2,
            $info
        );

        return $tempFiles;
    }

    private function applyStamp(Fpdi $pdf, array $pageSize, string $verificationUrl, array $context): void
    {
        $margin = 15.0;
        $qrSize = 25.0;
        $boxPadding = 2.0;

        // Use simpler layout: QR Code + Name below
        // Determine position (default bottom right for now, slightly adjusted)
        $boxWidth = $qrSize + ($boxPadding * 2);
        $boxHeight = $qrSize + 10; // Space for name

        $boxX = $pageSize['width'] - $margin - $boxWidth;
        $boxY = $pageSize['height'] - $margin - $boxHeight;

        // Simplify QR Data to just URL for ease of scanning
        $qrData = $verificationUrl;

        // QR Code style
        $style = [
            'border' => 0,
            'padding' => 0,
            'fgcolor' => [0, 0, 0], // Black
            'bgcolor' => [255, 255, 255], // White
        ];

        // Draw QR code
        $qrX = $boxX + $boxPadding;
        $qrY = $boxY + $boxPadding;
        $pdf->write2DBarcode($qrData, 'QRCODE,M', $qrX, $qrY, $qrSize, $qrSize, $style, 'N');

        // Draw Name
        $signerName = $context['signed_by'] ?? 'Unknown';

        // Truncate if very long
        $displayName = mb_strlen($signerName) > 20 ? mb_substr($signerName, 0, 19) . '...' : $signerName;

        $pdf->SetFont('helvetica', 'B', 8); // Bold, size 8
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetXY($boxX, $qrY + $qrSize + 1); // Below QR
        $pdf->Cell($boxWidth, 4, $displayName, 0, 1, 'C'); // Centered text

        // Optional: "Valid" text or checkmark if needed, but user image didn't show it explicitly for the name part. 
        // The user image had "Pihak Kedua" above. 
        // Since we don't know the role, we skip "Pihak X".
        // We just verify the name is there.
    }

    /**
     * Build simple QR data (URL only) to ensure "scan -> view website" works on all devices.
     */
    private function buildUnifiedQRData(array $context, string $verificationUrl): string
    {
        return $verificationUrl;
    }

    private function formatCompactTimestamp(string $timestamp): string
    {
        try {
            $dt = new \DateTime($timestamp);
            return $dt->format('d/m/Y H:i');
        } catch (\Exception $e) {
            return $timestamp;
        }
    }

    private function writeTempPem(string $contents, string $prefix): ?string
    {
        $path = $this->writeTempFile(storage_path('app/tmp'), $prefix, $contents);
        if ($path) {
            return $path;
        }

        return $this->writeTempFile(sys_get_temp_dir(), $prefix, $contents);
    }

    private function exportUnencryptedPrivateKey(string $privateKey, string $passphrase): ?string
    {
        $keyResource = $passphrase !== ''
            ? openssl_pkey_get_private($privateKey, $passphrase)
            : openssl_pkey_get_private($privateKey);

        if ($keyResource === false) {
            return null;
        }

        $exported = openssl_pkey_export($keyResource, $privateKeyPem, null);
        if (!$exported) {
            return null;
        }

        return $privateKeyPem;
    }

    private function writeTempFile(string $dir, string $prefix, string $contents): ?string
    {
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            return null;
        }

        $path = tempnam($dir, $prefix);
        if ($path === false) {
            return null;
        }

        $written = @file_put_contents($path, $contents);
        if ($written === false) {
            @unlink($path);
            return null;
        }

        return $path;
    }

    private function cleanupTempFiles(array $files): void
    {
        foreach ($files as $file) {
            if (is_string($file) && $file !== '' && file_exists($file)) {
                @unlink($file);
            }
        }
    }
}
