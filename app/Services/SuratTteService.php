<?php

namespace App\Services;

use App\Models\ESign\VerifStep;
use App\Models\Verify\ListVerif;
use RuntimeException;
use setasign\Fpdi\Tcpdf\Fpdi;

/**
 * TTE for adm letters, built on the existing esign tables: the code is derived
 * from verif_steps.signature (the signer's RSA signature), so no new table or
 * column is needed. Older approvals without a signature get no TTE.
 */
class SuratTteService
{
    public static function verifyUrl(string $noDocument): string
    {
        return rtrim(env('FRONTEND_URL', 'https://sys.pema.co.id'), '/') . '/verification/' . $noDocument . '?type=sign';
    }

    public static function code(string $signature, string $noDocument): string
    {
        $hash = strtoupper(substr(hash_hmac('sha256', $signature . '|' . $noDocument, config('app.key')), 0, 16));

        return implode('-', str_split($hash, 4));
    }

    /**
     * @return array{code: string, name: ?string, position: ?string, signed_at: mixed}|null
     */
    public static function find(string $noDocument): ?array
    {
        try {
            $step = ListVerif::where('id_doc', $noDocument)->where('type', 'sign')->where('status', 'approve')->first();
            if (!$step) {
                return null;
            }
            $signature = VerifStep::where('id_doc', $noDocument)
                ->where('type', 'sign')
                ->where('status', 'approve')
                ->where('id_employe', $step->id_employe)
                ->value('signature');
            if (!$signature) {
                return null;
            }

            $paraf = ListVerif::where('id_doc', $noDocument)
                ->where('type', 'paraf')
                ->where('status', 'approve')
                ->orderBy('step')
                ->get()
                ->map(fn ($p) => ['name' => (string) $p->employe_name, 'position' => (string) $p->id_current_position])
                ->all();

            return [
                'code' => self::code($signature, $noDocument),
                'name' => $step->employe_name,
                'position' => $step->id_current_position,
                'signed_at' => $step->updated_at,
                'paraf' => $paraf,
            ];
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    public static function codeFor(string $noDocument): ?string
    {
        return self::find($noDocument)['code'] ?? null;
    }

    /**
     * Embed a cryptographic signature (certificate carries signer name and
     * position) into the given PDF and return the signed bytes.
     */
    public static function signPdf(string $pdfBytes, string $noDocument, array $tte): string
    {
        $certificatePath = config('docusign.signing_certificate');
        $privateKeyPath = config('docusign.signing_private_key');
        if (!is_file($certificatePath) || !is_readable($certificatePath) || !is_file($privateKeyPath) || !is_readable($privateKeyPath)) {
            throw new RuntimeException('Signing certificate is missing or unreadable. Run php artisan docusign:generate-signing-certificate.');
        }

        $source = tempnam(sys_get_temp_dir(), 'tte');
        $target = tempnam(sys_get_temp_dir(), 'tte');
        $signer = (object) ['name' => $tte['name'], 'position' => $tte['position']];
        $leaf = DocusignPdfService::issueSignerCertificate([$signer], $certificatePath, $privateKeyPath);
        $paraf = $tte['paraf'] ?? [];
        $parafText = implode('; ', array_map(fn ($p) => trim($p['name'] . ($p['position'] ? ' (' . $p['position'] . ')' : '')), $paraf));
        try {
            file_put_contents($source, $pdfBytes);

            $pdf = new Fpdi();
            $pdf->SetAutoPageBreak(false);
            $pdf->SetTitle('Surat ' . $noDocument);
            $pdf->SetAuthor((string) $tte['name']);
            $pdf->SetSubject(($parafText !== '' ? 'Paraf: ' . $parafText . '. ' : '') . 'Ditandatangani: ' . trim($tte['name'] . ($tte['position'] ? ' (' . $tte['position'] . ')' : '')));
            $pdf->SetKeywords('Kode TTE ' . $tte['code']);
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);
            $pageCount = $pdf->setSourceFile($source);
            for ($i = 1; $i <= $pageCount; $i++) {
                $tpl = $pdf->importPage($i);
                $size = $pdf->getTemplateSize($tpl);
                $pdf->AddPage($size['width'] > $size['height'] ? 'L' : 'P', [$size['width'], $size['height']]);
                $pdf->useTemplate($tpl);
            }

            $pdf->setSignature(
                DocusignPdfService::fileUri($leaf['certificate']),
                DocusignPdfService::fileUri($leaf['key']),
                '',
                $certificatePath,
                2,
                [
                    'Name' => (string) $tte['name'],
                    'Location' => 'Indonesia',
                    'Reason' => ($parafText !== '' ? 'Paraf: ' . $parafText . '. ' : '') . 'Ditandatangani secara elektronik oleh ' . trim($tte['name'] . ($tte['position'] ? ' (' . $tte['position'] . ')' : '')) . ' - Kode TTE ' . $tte['code'],
                    'ContactInfo' => self::verifyUrl($noDocument),
                ]
            );
            $pdf->setSignatureAppearance(0, 0, 0, 0, $pageCount, 'PEMA E-Sign');
            $pdf->Output($target, 'F');

            return (string) file_get_contents($target);
        } finally {
            @unlink($source);
            @unlink($target);
            @unlink($leaf['certificate']);
            @unlink($leaf['key']);
        }
    }
}
