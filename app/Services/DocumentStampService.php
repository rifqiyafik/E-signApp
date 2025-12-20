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

        if (!$certificate || !$privateKey) {
            return;
        }

        $passphrase = $signature['privateKeyPassphrase'] ?? '';
        $info = $signature['info'] ?? [];

        $pdf->setSignature($certificate, $privateKey, $passphrase, '', 2, $info);
    }

    private function applyStamp(Fpdi $pdf, array $pageSize, string $verificationUrl, array $context): void
    {
        $margin = 10.0;
        $qrSize = min(30.0, max(18.0, $pageSize['width'] * 0.18));
        $x = $pageSize['width'] - $qrSize - $margin;
        $y = $pageSize['height'] - $qrSize - $margin;

        $style = [
            'border' => 0,
            'padding' => 1,
            'fgcolor' => [0, 0, 0],
            'bgcolor' => [255, 255, 255],
        ];

        $pdf->write2DBarcode($verificationUrl, 'QRCODE,H', $x, $y, $qrSize, $qrSize, $style, 'N');

        $lines = ['Verify: ' . $verificationUrl];
        if (!empty($context['signed_by'])) {
            $lines[] = 'Signed by: ' . $context['signed_by'];
        }
        if (!empty($context['signed_at'])) {
            $lines[] = 'Signed at: ' . $context['signed_at'];
        }

        $text = implode("\n", $lines);
        $textWidth = max(40.0, $x - $margin - 5.0);
        $textHeight = $qrSize;

        $pdf->SetFont('helvetica', '', 8);
        $pdf->SetXY($margin, $y);
        $pdf->MultiCell($textWidth, $textHeight, $text, 0, 'L', false, 1, '', '', true, 0, false, true, $textHeight, 'T', true);
    }
}
