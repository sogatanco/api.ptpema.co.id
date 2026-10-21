<?php

namespace App\Services;

use App\Models\Docusign\DocusignDocument;
use App\Models\Docusign\DocusignSigner;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use RuntimeException;
use setasign\Fpdi\Tcpdf\Fpdi;

class DocusignPdfService
{
    public static function verifyUrl(DocusignDocument $doc): string
    {
        return rtrim(env('FRONTEND_URL', 'https://sys.pema.co.id'), '/') . '/docusign/verify/' . $doc->uuid;
    }

    public static function signatureCode(DocusignDocument $doc, DocusignSigner $signer): string
    {
        $hash = strtoupper(substr(hash_hmac('sha256', $signer->token . '|' . $doc->uuid . '|' . $signer->signed_at, config('app.key')), 0, 16));

        return implode('-', str_split($hash, 4));
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

    private static function wrapText(Fpdi $pdf, string $text, float $width): array
    {
        $text = self::latin1($text);
        $lines = [];
        $line = '';
        foreach (preg_split('/\s+/', trim($text)) ?: [] as $word) {
            if ($line !== '' && $pdf->GetStringWidth($line . ' ' . $word) > $width) {
                $lines[] = $line;
                $line = '';
            }
            if ($pdf->GetStringWidth($word) <= $width) {
                $line = $line === '' ? $word : $line . ' ' . $word;
                continue;
            }
            for ($i = 0, $length = strlen($word); $i < $length; $i++) {
                if ($line !== '' && $pdf->GetStringWidth($line . $word[$i]) > $width) {
                    $lines[] = $line;
                    $line = '';
                }
                $line .= $word[$i];
            }
        }
        if ($line !== '') {
            $lines[] = $line;
        }

        return $lines ?: [''];
    }

    private static function drawTextLines(Fpdi $pdf, array $lines, float $x, float $y, float $width, float $height, float $fontSize, bool $bold = false, bool $bottomAligned = false): void
    {
        $style = $bold ? 'B' : '';
        $pdf->SetFont('Helvetica', $style, $fontSize);
        $lineHeight = $fontSize * 0.3528 * 1.15;
        $maxLines = max(1, (int) floor($height / $lineHeight));
        $wasTruncated = count($lines) > $maxLines;
        $lines = array_slice($lines, 0, $maxLines);

        if ($wasTruncated) {
            $lastLine = array_pop($lines);
            while ($lastLine !== '' && $pdf->GetStringWidth($lastLine . '...') > $width) {
                $lastLine = substr($lastLine, 0, -1);
            }
            $lines[] = rtrim($lastLine) . '...';
        }

        $fontHeight = $fontSize * 0.3528;
        $baseline = $bottomAligned
            ? $y + $height - 0.5 - (count($lines) - 1) * $lineHeight
            : $y + $fontHeight;
        foreach ($lines as $index => $line) {
            $pdf->Text($x, $baseline + $index * $lineHeight, $line);
        }
    }

    /**
     * Stamp: QR, signer identity, and a selectable TTE verification code.
     */
    private static function drawStamp(Fpdi $pdf, string $url, DocusignSigner $signer, string $code, float $x, float $y, float $w, float $h): void
    {
        $pdf->SetFillColor(255, 255, 255);
        $pdf->SetDrawColor(190, 190, 190);
        $pdf->SetLineWidth(0.25);
        $pdf->Rect($x, $y, $w, $h, 'DF');

        $pad = min($w, $h) * 0.06;
        $qrSize = min($h - 2 * $pad, ($w - 3 * $pad) * 0.34);
        self::drawQr($pdf, $url, $x + $pad, $y + $pad, $qrSize);

        $tx = $x + $pad + $qrSize + $pad;
        $tw = max(0, $x + $w - $pad - $tx);
        $fs = 8.415;
        $textHeight = max(0, $h - 2 * $pad);
        $lineHeight = $fs * 0.3528 * 1.15;
        $codeText = self::latin1('Kode TTE: ' . $code);
        $codeFontSize = min(6.2, max(1, ($textHeight - 0.5) / (0.3528 * 1.15)));
        $pdf->SetFont('Helvetica', '', $codeFontSize);
        while ($codeFontSize > 1 && $pdf->GetStringWidth($codeText) > $tw) {
            $codeFontSize -= 0.2;
            $pdf->SetFont('Helvetica', '', $codeFontSize);
        }
        $codeLineHeight = $codeFontSize * 0.3528 * 1.15;
        $codeGap = 0.4;
        $bodyHeight = max(0, $textHeight - $codeLineHeight - $codeGap);
        $availableLines = max(1, (int) floor($bodyHeight / $lineHeight));
        $pdf->SetFont('Helvetica', '', $fs);
        $heading = self::wrapText($pdf, 'Ditandatangani secara elektronik oleh:', $tw);
        $pdf->SetFont('Helvetica', 'B', $fs);
        $position = self::wrapText($pdf, mb_strtoupper((string) $signer->position), $tw);
        $name = self::wrapText($pdf, (string) $signer->name, $tw);
        $headingLimit = $availableLines >= 3 ? min(count($heading), $availableLines - 2) : 0;
        $positionLimit = min(count($position), max(0, $availableLines - $headingLimit - 1));
        $nameLimit = max(1, $availableLines - $headingLimit - $positionLimit);
        $headingLines = array_slice($heading, 0, $headingLimit);
        $positionLines = array_slice($position, 0, $positionLimit);
        $nameLines = array_slice($name, 0, $nameLimit);
        $headingHeight = count($headingLines) * $lineHeight;
        $positionHeight = count($positionLines) * $lineHeight;
        $nameHeight = count($nameLines) * $lineHeight;
        $pdf->SetTextColor(0, 0, 0);
        if ($headingLines) {
            self::drawTextLines($pdf, $headingLines, $tx, $y + $pad, $tw, $headingHeight, $fs);
        }
        if ($positionLines) {
            self::drawTextLines($pdf, $positionLines, $tx, $y + $pad + $headingHeight, $tw, $positionHeight, $fs, true);
        }
        $nameY = $y + $pad + $bodyHeight - $nameHeight;
        self::drawTextLines($pdf, $nameLines, $tx, $nameY, $tw, $nameHeight, $fs, true, true);
        $pdf->SetFont('Helvetica', '', $codeFontSize);
        $pdf->Text($tx, $y + $h - $pad - 0.5, $codeText);
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
        $certificatePath = config('docusign.signing_certificate');
        $privateKeyPath = config('docusign.signing_private_key');

        if (!is_file($certificatePath) || !is_readable($certificatePath)) {
            throw new RuntimeException('DocuSign signing certificate is missing or unreadable. Run php artisan docusign:generate-signing-certificate.');
        }
        if (!is_file($privateKeyPath) || !is_readable($privateKeyPath)) {
            throw new RuntimeException('DocuSign signing private key is missing or unreadable. Run php artisan docusign:generate-signing-certificate.');
        }

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
                self::drawStamp(
                    $pdf,
                    $url,
                    $signer,
                    self::signatureCode($doc, $signer),
                    $f->x * $w,
                    $f->y * $h,
                    $f->w * $w,
                    $f->h * $h
                );
            }

            $pageQrSize = min(18, min($w, $h) * 0.07);
            $pageQrX = $w - $pageQrSize - 5;
            $pageQrY = 5;
            $pdf->SetFillColor(255, 255, 255);
            $pdf->Rect($pageQrX - 1, $pageQrY - 1, $pageQrSize + 2, $pageQrSize + 2, 'F');
            self::drawQr($pdf, $url, $pageQrX, $pageQrY, $pageQrSize);
            $captionWidth = 26;
            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetFont('Helvetica', '', 4);
            $pdf->SetXY($pageQrX + ($pageQrSize - $captionWidth) / 2, $pageQrY + $pageQrSize + 0.5);
            $pdf->Cell($captionWidth, 2.5, 'Signed by Pema E-Sign', 0, 0, 'C');
        }

        $pdf->setSignature(
            self::fileUri($certificatePath),
            self::fileUri($privateKeyPath),
            '',
            '',
            2,
            [
                'Name' => (string) config('docusign.signing_name'),
                'Location' => 'Indonesia',
                'Reason' => 'Electronic signature by PEMA E-Sign',
                'ContactInfo' => self::verifyUrl($doc),
            ]
        );
        $pdf->setSignatureAppearance(0, 0, 0, 0, $pageCount, 'PEMA E-Sign');
        $pdf->Output($targetPath, 'F');
    }

    private static function fileUri(string $path): string
    {
        $path = str_replace('\\', '/', $path);

        return 'file://' . $path;
    }
}
