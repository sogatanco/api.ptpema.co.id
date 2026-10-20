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

    /**
     * Stamp: [QR] | "Ditandatangani secara elektronik oleh:" / JABATAN (bold) / Nama (bold, bottom)
     */
    private static function drawStamp(Fpdi $pdf, string $url, $signer, float $x, float $y, float $w, float $h): void
    {
        $pdf->SetFillColor(255, 255, 255);
        $pdf->SetDrawColor(190, 190, 190);
        $pdf->SetLineWidth(0.25);
        $pdf->Rect($x, $y, $w, $h, 'DF');

        $pad = min($w, $h) * 0.08;
        $qrSize = $h - 2 * $pad;
        self::drawQr($pdf, $url, $x + $pad, $y + $pad, $qrSize);

        $tx = $x + $pad + $qrSize + $pad * 1.5;
        $tw = $x + $w - $pad - $tx;
        $fs = max(4.5, min(9, $h * 0.27));
        $lh = $fs * 0.3528 * 1.2;

        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('Helvetica', '', $fs);
        $pdf->SetXY($tx, $y + $pad);
        $pdf->Cell($tw, $lh, self::latin1('Ditandatangani secara elektronik oleh:'), 0, 2);

        $pdf->SetFont('Helvetica', 'B', $fs);
        $pdf->SetX($tx);
        $position = mb_strtoupper(mb_strimwidth((string) $signer->position, 0, 80, '...'));
        $pdf->MultiCell($tw, $lh, self::latin1($position), 0, 'L');

        $pdf->SetXY($tx, $y + $h - $pad - $lh);
        $pdf->Cell($tw, $lh, self::latin1(mb_strimwidth($signer->name, 0, 60, '...')), 0, 0);
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
        }

        $pdf->Output('F', $targetPath);
    }
}
