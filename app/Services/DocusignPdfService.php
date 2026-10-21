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

        // TCPDF::Text() takes the top of the text cell, not the baseline.
        $top = $bottomAligned
            ? $y + $height - count($lines) * $lineHeight
            : $y;
        foreach ($lines as $index => $line) {
            $pdf->Text($x, $top + $index * $lineHeight, $line);
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
        $bottomMargin = 0.8;
        $textHeight = max(0, $h - 2 * $pad - $bottomMargin);
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
        $pdf->Text($tx, $y + $h - $pad - $bottomMargin - $codeLineHeight, $codeText);
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
        $pdf->setCellPaddings(0, 0, 0, 0);
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

        $signedSigners = $doc->signers->where('status', 'signed')->values();
        $leaf = self::issueSignerCertificate($signedSigners->all(), $certificatePath, $privateKeyPath);

        try {
            $names = $signedSigners->map(fn ($s) => trim($s->name . ($s->position ? ' (' . $s->position . ')' : '')))->implode('; ');
            $pdf->setSignature(
                self::fileUri($leaf['certificate']),
                self::fileUri($leaf['key']),
                '',
                $certificatePath,
                2,
                [
                    'Name' => $signedSigners->pluck('name')->implode(', ') ?: (string) config('docusign.signing_name'),
                    'Location' => 'Indonesia',
                    'Reason' => 'Ditandatangani secara elektronik oleh ' . ($names ?: 'PEMA E-Sign'),
                    'ContactInfo' => self::verifyUrl($doc),
                ]
            );
            $pdf->setSignatureAppearance(0, 0, 0, 0, $pageCount, 'PEMA E-Sign');
            $pdf->Output($targetPath, 'F');
        } finally {
            @unlink($leaf['certificate']);
            @unlink($leaf['key']);
        }
    }

    /**
     * Issue a short-lived certificate carrying the signer name and position,
     * signed by the PEMA E-Sign certificate, so they show in the PDF reader.
     *
     * @param DocusignSigner[] $signers
     * @return array{certificate: string, key: string}
     */
    public static function issueSignerCertificate(array $signers, string $caCertificatePath, string $caKeyPath): array
    {
        $names = implode(', ', array_map(fn ($s) => (string) $s->name, $signers));
        $positions = implode(', ', array_filter(array_map(fn ($s) => (string) $s->position, $signers)));
        $dn = [
            'countryName' => 'ID',
            'organizationName' => (string) config('docusign.signing_organization'),
            'commonName' => mb_substr($names ?: (string) config('docusign.signing_name'), 0, 64),
        ];
        if ($positions !== '') {
            $dn['organizationalUnitName'] = mb_substr($positions, 0, 64);
        }

        $config = ['digest_alg' => 'sha256', 'private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA];
        $key = openssl_pkey_new($config);
        $csr = $key ? openssl_csr_new($dn, $key, $config) : false;
        $ca = file_get_contents($caCertificatePath);
        $caKey = openssl_pkey_get_private(file_get_contents($caKeyPath));
        $cert = $csr && $caKey ? openssl_csr_sign($csr, $ca, $caKey, 3650, $config, random_int(1, PHP_INT_MAX)) : false;
        if (!$cert || !openssl_x509_export($cert, $certPem) || !openssl_pkey_export($key, $keyPem)) {
            throw new RuntimeException('Failed to issue DocuSign signer certificate: ' . openssl_error_string());
        }

        $certFile = tempnam(sys_get_temp_dir(), 'dsc');
        $keyFile = tempnam(sys_get_temp_dir(), 'dsk');
        file_put_contents($certFile, $certPem);
        file_put_contents($keyFile, $keyPem);

        return ['certificate' => $certFile, 'key' => $keyFile];
    }

    public static function fileUri(string $path): string
    {
        $path = str_replace('\\', '/', $path);

        return 'file://' . $path;
    }
}
