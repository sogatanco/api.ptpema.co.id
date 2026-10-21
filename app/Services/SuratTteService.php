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
    public static function verifyUrl(string $noDocument, string $type = 'sign'): string
    {
        return rtrim(env('FRONTEND_URL', 'https://sys.pema.co.id'), '/') . '/verification/' . $noDocument . '?type=' . $type;
    }

    public static function code(string $signature, string $noDocument): string
    {
        $hash = strtoupper(substr(hash_hmac('sha256', $signature . '|' . $noDocument, config('app.key')), 0, 16));

        return implode('-', str_split($hash, 4));
    }

    /**
     * @return array{code: string, name: ?string, position: ?string, signed_at: mixed}|null
     */
    public static function find(string $noDocument, string $type = 'sign'): ?array
    {
        try {
            $step = ListVerif::where('id_doc', $noDocument)->where('type', $type)->where('status', 'approve')->first();
            if (!$step) {
                return null;
            }
            $signature = VerifStep::where('id_doc', $noDocument)
                ->where('type', $type)
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

    /**
     * SHA-256 of the original letter: the stored content fields plus the
     * attachment file. Computed on demand, nothing is stored.
     */
    public static function contentHash(string $noDocument): ?string
    {
        try {
            $s = \App\Models\Adm\Surat::where('no_document', $noDocument)->first();
            if (!$s) {
                return null;
            }
            $attachment = '';
            if ($s->file_lampiran && is_file(public_path('adm/' . $s->file_lampiran))) {
                $attachment = hash_file('sha256', public_path('adm/' . $s->file_lampiran));
            }
            $payload = json_encode([
                'nomor_surat' => $s->nomor_surat,
                'no_document' => $s->no_document,
                'kepada' => $s->kepada,
                'perihal' => $s->perihal,
                'j_lampiran' => (int) $s->j_lampiran,
                'jenis_lampiran' => $s->jenis_lampiran,
                'isi_surat' => $s->isi_surat,
                'tembusans' => (string) $s->tembusans,
                'sign_by' => $s->sign_by,
                'bahasa' => $s->bahasa,
                'lampiran_sha256' => $attachment,
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            return hash('sha256', $payload);
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    /**
     * SHA-256 of the original SPPD: the stored SPPD fields, its destinations
     * and each invitation file. Computed on demand, nothing is stored.
     */
    public static function sppdContentHash(string $noDocument): ?string
    {
        try {
            $s = \App\Models\Sppd\Sppd::where('nomor_dokumen', $noDocument)->first();
            if (!$s) {
                return null;
            }
            // Fixed field lists: only the content set at submission, so status flags changing later don't alter the hash
            $sppdFields = ['nomor_sppd', 'nomor_dokumen', 'employe_id', 'nama', 'jabatan', 'golongan_rate', 'narasi_st', 'submitted_by', 'ketetapan'];
            $tujuanFields = ['jenis_sppd', 'dasar', 'file_undangan', 'klasifikasi', 'sumber', 'rkap', 'p_tiket', 'p_um', 'p_tl', 'p_us', 'p_hotel', 'kategori', 'detail_tujuan', 'tugas', 'waktu_berangkat', 'waktu_kembali', 'moda', 'bbm', 'share_with'];
            $pick = fn (array $row, array $keys) => array_intersect_key($row, array_flip($keys));
            $tujuan = \App\Models\Sppd\TujuanSppd::where('id_sppd', $s->id)->orderBy('id')->get()->map(function ($t) use ($pick, $tujuanFields) {
                $row = $pick($t->toArray(), $tujuanFields);
                $file = $t->file_undangan;
                $row['file_undangan_sha256'] = ($file && $file !== '-' && \Illuminate\Support\Facades\Storage::disk('public_sppd')->exists($file))
                    ? hash('sha256', \Illuminate\Support\Facades\Storage::disk('public_sppd')->get($file))
                    : '';

                return $row;
            })->all();

            $payload = json_encode(
                SignatureService::normalizeJson(['sppd' => $pick($s->toArray(), $sppdFields), 'tujuan' => $tujuan]),
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            );

            return hash('sha256', $payload);
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    private static function chainOf(string $prev, ?string $contentHash, $step): string
    {
        return hash('sha256', implode('|', [
            $prev,
            (string) $contentHash,
            $step->id_employe,
            $step->type,
            $step->step,
            $step->signature,
        ]));
    }

    /**
     * Chain hash per approval: SHA-256 over the document content hash, the
     * previous step's chain hash and this step's signature. The last step's
     * hash therefore covers every paraf and the signature. Only the new
     * nullable column is written, and only on the approved step itself.
     */
    public static function recordChain($verif, ?string $contentHash): void
    {
        try {
            if (!self::hasChainColumn() || !$verif->signature || $verif->status !== 'approve') {
                return;
            }
            $prev = (string) VerifStep::where('id_doc', $verif->id_doc)
                ->where('status', 'approve')
                ->whereNotNull('chain_sha256')
                ->where('step', '<', $verif->step)
                ->orderByDesc('step')
                ->value('chain_sha256');

            VerifStep::where('id', $verif->id)->update(['chain_sha256' => self::chainOf($prev, $contentHash, $verif)]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * @return array{hash: string, valid: bool}|null latest chain hash and whether it still matches the current content
     */
    public static function chain(string $noDocument, ?string $contentHash): ?array
    {
        try {
            if (!self::hasChainColumn()) {
                return null;
            }
            $steps = VerifStep::where('id_doc', $noDocument)
                ->where('status', 'approve')
                ->whereNotNull('chain_sha256')
                ->orderBy('step')
                ->get();
            if ($steps->isEmpty()) {
                return null;
            }
            $prev = '';
            $valid = true;
            foreach ($steps as $s) {
                $expected = self::chainOf($prev, $contentHash, $s);
                $valid = $valid && hash_equals($expected, (string) $s->chain_sha256);
                $prev = (string) $s->chain_sha256;
            }

            return ['hash' => $prev, 'valid' => $valid];
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    private static function hasChainColumn(): bool
    {
        return \Illuminate\Support\Facades\Cache::remember('verif_steps_has_chain_sha256', 300, fn () => \Illuminate\Support\Facades\Schema::connection('esign')->hasColumn('verif_steps', 'chain_sha256'));
    }
    private static function storedPdfPath(string $noDocument, string $type): string
    {
        return 'tte-pdf/' . preg_replace('/[^A-Za-z0-9_-]/', '_', $noDocument . '-' . $type) . '.pdf';
    }

    private static function hasChain(string $noDocument, string $type): bool
    {
        return self::hasChainColumn()
            && VerifStep::where('id_doc', $noDocument)->where('type', $type)->where('status', 'approve')->whereNotNull('chain_sha256')->exists();
    }

    /**
     * Sign once, then always serve the same file so its SHA-256 never changes.
     * Only documents with a recorded chain hash (new approvals) are stored;
     * older documents are signed on the fly exactly as before.
     */
    public static function issueSignedPdf(string $pdfBytes, string $noDocument, array $tte, string $type = 'sign', string $title = 'Surat'): string
    {
        $disk = \Illuminate\Support\Facades\Storage::disk('local');
        $path = self::storedPdfPath($noDocument, $type);
        $store = false;
        try {
            $store = self::hasChain($noDocument, $type);
            if ($store && $disk->exists($path)) {
                return $disk->get($path);
            }
        } catch (\Throwable $e) {
            report($e);
        }

        $signed = self::signPdf($pdfBytes, $noDocument, $tte, $type, $title);
        if ($store) {
            try {
                $disk->put($path, $signed);

                return $disk->get($path);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return $signed;
    }

    public static function storedPdfHash(string $noDocument, string $type): ?string
    {
        try {
            $disk = \Illuminate\Support\Facades\Storage::disk('local');
            $path = self::storedPdfPath($noDocument, $type);

            return $disk->exists($path) ? hash('sha256', $disk->get($path)) : null;
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    public static function codeFor(string $noDocument, string $type = 'sign'): ?string
    {
        return self::find($noDocument, $type)['code'] ?? null;
    }

    /**
     * Embed a cryptographic signature (certificate carries signer name and
     * position) into the given PDF and return the signed bytes.
     */
    public static function signPdf(string $pdfBytes, string $noDocument, array $tte, string $type = 'sign', string $title = 'Surat'): string
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
            $pdf->SetTitle($title . ' ' . $noDocument);
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
                    'ContactInfo' => self::verifyUrl($noDocument, $type),
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
