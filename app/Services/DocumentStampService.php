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
    ): void
    {
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
        $margin = 10.0;
        $gap = 6.0;
        $qrSize = min(30.0, max(18.0, $pageSize['width'] * 0.18));
        $availableWidth = $pageSize['width'] - ($margin * 2);
        $columns = (int) floor(($availableWidth + $gap) / ($qrSize + $gap));
        if ($columns < 1) {
            $columns = 1;
        }
        if ($columns > 2) {
            $columns = 2;
        }
        if ($columns > 1) {
            $spreadGap = ($availableWidth - ($columns * $qrSize)) / ($columns - 1);
            if ($spreadGap > $gap) {
                $gap = $spreadGap;
            }
        }
        $signerIndex = (int) ($context['signer_index'] ?? 1);
        if ($signerIndex < 1) {
            $signerIndex = 1;
        }
        $slotIndex = $signerIndex - 1;
        $row = intdiv($slotIndex, $columns);
        $colFromRight = $slotIndex % $columns;
        $x = $pageSize['width'] - $margin - $qrSize - ($colFromRight * ($qrSize + $gap));
        $y = $pageSize['height'] - $margin - $qrSize - ($row * ($qrSize + $gap));

        $style = [
            'border' => 0,
            'padding' => 1,
            'fgcolor' => [0, 0, 0],
            'bgcolor' => [255, 255, 255],
        ];

        $pdf->write2DBarcode($verificationUrl, 'QRCODE,H', $x, $y, $qrSize, $qrSize, $style, 'N');
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
