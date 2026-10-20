<?php

namespace App\Services;

use App\Models\Docusign\DocusignDocument;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use setasign\Fpdi\Fpdi;

class DocusignPdfService
{
    public static function verifyUrl(DocusignDocument $doc): string
    {
        return rtrim(env('FRONTEND_URL', 'https://sys.pema.co.id'), '/') . '/docusign/verify/' . $doc->uuid;
    }

    private static function latin1(string $s): string
    {
        return mb_convert_encoding($s, 'ISO-8859-1', 'UTF-8');
    }

    private static function drawQr(Fpdi $pdf, string $data, float $x, float $y, float $size): void
    {
        $qr = new QRCode(new QROptions(['outputBase64' => false]));
        $qr->addByteSegment($data);
        $matrix = $qr->getQRMatrix();
        $count = $matrix->getSize();
        $cell = $size / $count;

        $pdf->SetFillColor(0, 0, 0);
        for ($r = 0; $r < $count; $r++) {
            for ($c = 0; $c < $count; $c++) {
                if ($matrix->check($c, $r)) {
                    $pdf->Rect($x + $c * $cell, $y + $r * $cell, $cell + 0.02, $cell + 0.02, 'F');
                }
            }
        }
    }

    private static function drawFitText(Fpdi $pdf, string $text, float $x, float $y, float $width, float $height, float $fontSize, bool $bold = false): void
    {
        $text = self::latin1($text);
        $style = $bold ? 'B' : '';
        $pdf->SetFont('Helvetica', $style, $fontSize);
        while ($pdf->GetStringWidth($text) > $width && $fontSize > 3.5) {
            $fontSize -= 0.25;
            $pdf->SetFont('Helvetica', $style, $fontSize);
        }
        while ($pdf->GetStringWidth($text) > $width && strlen($text) > 4) {
            $text = rtrim(substr($text, 0, -1));
        }
        if ($pdf->GetStringWidth($text) > $width && strlen($text) > 3) {
            $text = substr($text, 0, 3);
        }

        $pdf->SetXY($x, $y);
        $pdf->Cell($width, $height, $text, 0, 0, 'L');
    }

    /**
     * Stamp: [QR] | "Ditandatangani secara elektronik oleh:" / JABATAN (bold) / Nama (bold, bottom)
     */
    private static function drawStamp(Fpdi $pdf, string $url, $signer, float $x, float $y, float $w, float $h): void
    {
        $pdf->SetFillColor(255, 255, 255);
        $pdf->SetDrawColor(190, 190, 190);
        $pdf->SetLineWidth(0.25);
        $pdf->Rect($x, $y, $w, $h, 'DF');

        $pad = min($w, $h) * 0.06;
        $qrSize = $h - 2 * $pad;
        self::drawQr($pdf, $url, $x + $pad, $y + $pad, $qrSize);

        $tx = $x + $pad + $qrSize + $pad;
        $tw = max(1, $x + $w - $pad - $tx);
        $lineHeight = ($h - 2 * $pad) / 3;
        $fs = max(3.5, min(8.5, $h * 0.24));
        $pdf->SetTextColor(0, 0, 0);
        self::drawFitText($pdf, 'Ditandatangani secara elektronik oleh:', $tx, $y + $pad, $tw, $lineHeight, $fs);
        self::drawFitText($pdf, mb_strtoupper((string) $signer->position), $tx, $y + $pad + $lineHeight, $tw, $lineHeight, $fs, true);
        self::drawFitText($pdf, (string) $signer->name, $tx, $y + $pad + 2 * $lineHeight, $tw, $lineHeight, $fs, true);
    }

    /**
     * Build the output PDF from the original file, stamping every field
     * of signers who already signed.
     */
    public static function render(DocusignDocument $doc, string $sourcePath, string $targetPath): void
    {
        $doc->loadMissing(['signers', 'fields']);
        $signers = $doc->signers->keyBy('id');
        $url = self::verifyUrl($doc);

        $pdf = new Fpdi();
        $pdf->SetAutoPageBreak(false);
        $pageCount = $pdf->setSourceFile($sourcePath);

        for ($i = 1; $i <= $pageCount; $i++) {
            $tpl = $pdf->importPage($i);
            $size = $pdf->getTemplateSize($tpl);
            $w = $size['width'];
            $h = $size['height'];
            $pdf->AddPage($w > $h ? 'L' : 'P', [$w, $h]);
            $pdf->useTemplate($tpl);

            foreach ($doc->fields->where('page', $i) as $f) {
                $signer = $signers->get($f->signer_id);
                if (!$signer || $signer->status !== 'signed') {
                    continue;
                }
                self::drawStamp($pdf, $url, $signer, $f->x * $w, $f->y * $h, $f->w * $w, $f->h * $h);
            }

            $pageQrSize = min(18, min($w, $h) * 0.07);
            $pageQrX = $w - $pageQrSize - 5;
            $pageQrY = 5;
            $pdf->SetFillColor(255, 255, 255);
            $pdf->Rect($pageQrX - 1, $pageQrY - 1, $pageQrSize + 2, $pageQrSize + 2, 'F');
            self::drawQr($pdf, $url, $pageQrX, $pageQrY, $pageQrSize);
        }

        $pdf->Output('F', $targetPath);
    }
}
