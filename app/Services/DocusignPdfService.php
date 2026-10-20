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

        $pdf->SetFillColor(255, 255, 255);
        $pdf->Rect($x - 1, $y - 1, $size + 2, $size + 2, 'F');
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
     * Build the output PDF from the original file: QR code in the bottom-right corner
     * of every page plus a stamp inside each field of signers who already signed.
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
                $fx = $f->x * $w;
                $fy = $f->y * $h;
                $fw = $f->w * $w;
                $fh = $f->h * $h;
                $pdf->SetDrawColor(25, 118, 210);
                $pdf->SetFillColor(240, 247, 255);
                $pdf->Rect($fx, $fy, $fw, $fh, 'DF');
                $pdf->SetTextColor(25, 118, 210);
                $fs = max(5, min(8, $fh * 0.9));
                $pdf->SetFont('Helvetica', 'B', $fs);
                $pdf->SetXY($fx + 1, $fy + 1);
                $pdf->Cell($fw - 2, $fs * 0.45, self::latin1('Ditandatangani secara elektronik'), 0, 2);
                $pdf->SetFont('Helvetica', '', $fs);
                $pdf->Cell($fw - 2, $fs * 0.45, self::latin1(mb_strimwidth($signer->name, 0, 40, '...')), 0, 2);
                $pdf->Cell($fw - 2, $fs * 0.45, self::latin1(optional($signer->signed_at)->format('d-m-Y H:i') . ' WIB'), 0, 2);
            }

            $qrSize = 18;
            self::drawQr($pdf, $url, $w - $qrSize - 5, $h - $qrSize - 9, $qrSize);
            $pdf->SetTextColor(80, 80, 80);
            $pdf->SetFont('Helvetica', '', 5);
            $pdf->SetXY($w - $qrSize - 15, $h - 7);
            $pdf->Cell($qrSize + 10, 3, self::latin1('Scan untuk verifikasi | ' . substr($doc->uuid, 0, 8)), 0, 0, 'R');
        }

        $pdf->Output('F', $targetPath);
    }
}
