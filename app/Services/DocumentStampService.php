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

        if ($signature) {
            $this->applySignature($pdf, $signature);
        }

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
    }

    private function applySignature(Fpdi $pdf, array $signature): void
    {
        $certificate = $signature['certificate'] ?? null;
        $privateKey = $signature['privateKey'] ?? null;
        $extraCerts = $signature['caCertificate'] ?? '';

        if (!$certificate || !$privateKey) {
            return;
        }

        $passphrase = $signature['privateKeyPassphrase'] ?? '';
        $info = $signature['info'] ?? [];

        $pdf->setSignature($certificate, $privateKey, $passphrase, $extraCerts ?: '', 2, $info);
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

}
