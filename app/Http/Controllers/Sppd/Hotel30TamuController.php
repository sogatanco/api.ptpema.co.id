<?php

namespace App\Http\Controllers\Sppd;

use App\Http\Controllers\Controller;
use App\Models\Employe;
use App\Models\ESign\Log;
use App\Models\ESign\VerifStep;
use App\Models\Sppd\TigaPuluh;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Persetujuan klaim hotel 30% oleh pelaksana SPPD yang bukan karyawan (employe_id '-'): tanpa akun,
 * lewat tautan email berisi token sekali pakai, atau dicatat manual (tanda tangan basah) oleh pengaju.
 */
class Hotel30TamuController extends Controller
{
    private const BERLAKU_HARI = 14;

    public static function adalahTamu($employeId): bool
    {
        $v = trim((string) $employeId);

        return $v === '' || $v === '-';
    }

    private function frontendUrl(): string
    {
        return rtrim(env('FRONTEND_URL', 'https://sys.pema.co.id'), '/');
    }

    private function sppdDariTujuan(int $idTujuan)
    {
        $sppd = DB::connection('mysql4')->table('tujuan_sppds as t')
            ->join('sppds as s', 's.id', '=', 't.id_sppd')
            ->where('t.id', $idTujuan)
            ->first(['s.nomor_dokumen', 's.nomor_sppd', 's.employe_id', 's.nama', 's.jabatan', 't.detail_tujuan']);
        abort_if(!$sppd, 404, 'Tujuan SPPD tidak ditemukan.');

        return $sppd;
    }

    private function langkahPemohon($sppd): ?VerifStep
    {
        return VerifStep::where('id_doc', $sppd->nomor_dokumen)->where('type', 'pemohon')
            ->where('id_employe', $sppd->employe_id)->whereNull('status')->orderBy('id')->first();
    }

    private function catatLog(string $doc, string $nama, ?string $jabatan, string $aktivitas): void
    {
        try {
            $l = new Log();
            $l->employe_id = '-';
            $l->id_current_position = trim($nama . ($jabatan ? ' (' . $jabatan . ')' : ''));
            $l->id_document = $doc;
            $l->activity = $aktivitas;
            $l->save();
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /** Membuat token baru (token lama untuk langkah yang sama dibatalkan) dan mengirim emailnya. */
    public function buatDanKirim(int $idTujuan, string $email): array
    {
        $sppd = $this->sppdDariTujuan($idTujuan);
        $langkah = $this->langkahPemohon($sppd);
        if (!$langkah) {
            return [false, 'Langkah persetujuan pelaksana tidak ditemukan atau sudah selesai.'];
        }
        $db = DB::connection('esign');
        $db->table('hotel30_tokens')->where('id_step', $langkah->id)->whereNull('dipakai_at')->delete();
        $plain = Str::random(64);
        $db->table('hotel30_tokens')->insert([
            'id_doc' => $sppd->nomor_dokumen,
            'id_step' => $langkah->id,
            'id_tujuan' => $idTujuan,
            'nama' => $sppd->nama,
            'jabatan' => $sppd->jabatan,
            'email' => $email,
            'token_hash' => hash('sha256', $plain),
            'expires_at' => now()->addDays(self::BERLAKU_HARI),
            'dibuat_oleh' => (string) Employe::employeId(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $link = $this->frontendUrl() . '/sppd/hotel30/' . $plain;
        $nama = e($sppd->nama);
        $nomor = e($sppd->nomor_sppd ?: $sppd->nomor_dokumen);
        $tujuan = e($sppd->detail_tujuan);
        $html = "<p>Yth. {$nama},</p>"
            . "<p>Anda diminta menyetujui pengajuan klaim biaya hotel 30% untuk perjalanan dinas <b>{$nomor}</b> ({$tujuan}).</p>"
            . "<p><a href=\"{$link}\" style=\"background:#1976d2;color:#fff;padding:10px 20px;text-decoration:none;border-radius:4px\">Tinjau &amp; Setujui</a></p>"
            . "<p>Jika tombol tidak berfungsi, salin tautan ini: {$link}</p>"
            . '<p>Tautan berlaku ' . self::BERLAKU_HARI . ' hari, bersifat pribadi, dan hanya dapat dipakai sekali.</p>';
        try {
            Mail::html($html, function ($m) use ($email, $sppd) {
                $m->to($email, $sppd->nama)->subject('Persetujuan Klaim Hotel 30%: ' . ($sppd->nomor_sppd ?: $sppd->nomor_dokumen));
            });
            $db->table('hotel30_tokens')->where('token_hash', hash('sha256', $plain))->update(['dikirim_at' => now()]);
        } catch (\Throwable $e) {
            report($e);

            return [false, 'Permintaan dibuat tetapi email gagal dikirim. Coba kirim ulang.'];
        }
        $this->catatLog($sppd->nomor_dokumen, $sppd->nama, $sppd->jabatan, 'Permintaan persetujuan klaim hotel 30% dikirim ke email pelaksana');

        return [true, 'Permintaan persetujuan dikirim ke ' . $email];
    }

    private function cekPengaju(int $idTujuan): void
    {
        $tp = TigaPuluh::where('id_tujuan', $idTujuan)->orderByDesc('id')->first();
        abort_if(!$tp, 404, 'Klaim 30% belum diajukan.');
        abort_if((string) $tp->submitted_by !== (string) Employe::employeId(), 403, 'Hanya pengaju klaim yang boleh melakukan ini.');
    }

    // ---------- Pengaju (login) ----------

    /** Info pelaksana untuk form pengajuan 30%: apakah perlu persetujuan lewat email. */
    public function info($idTujuan)
    {
        $sppd = $this->sppdDariTujuan((int) $idTujuan);
        $tamu = self::adalahTamu($sppd->employe_id);
        $token = $tamu ? DB::connection('esign')->table('hotel30_tokens')->where('id_tujuan', $idTujuan)->orderByDesc('id')->first() : null;

        return response()->json(['status' => true, 'data' => [
            'tamu' => $tamu,
            'nama' => $sppd->nama,
            'jabatan' => $sppd->jabatan,
            'email' => $token->email ?? null,
            'dikirim_at' => $token->dikirim_at ?? null,
            'dipakai_at' => $token->dipakai_at ?? null,
            'hasil' => $token->hasil ?? null,
        ]], 200);
    }

    public function kirim(Request $request, $idTujuan)
    {
        $request->validate(['email' => 'required|email|max:150']);
        $this->cekPengaju((int) $idTujuan);
        [$ok, $pesan] = $this->buatDanKirim((int) $idTujuan, $request->input('email'));

        return response()->json(['status' => $ok, 'message' => $pesan], $ok ? 200 : 422);
    }

    /** Pelaksana menandatangani manual (basah): pengaju mencatatnya sebagai disetujui. */
    public function manual(Request $request, $idTujuan)
    {
        $this->cekPengaju((int) $idTujuan);
        $sppd = $this->sppdDariTujuan((int) $idTujuan);
        $langkah = $this->langkahPemohon($sppd);
        if (!$langkah) {
            return response()->json(['status' => false, 'message' => 'Langkah pelaksana sudah selesai.'], 422);
        }
        DB::connection('esign')->transaction(function () use ($langkah, $sppd) {
            $langkah->status = 'approve';
            $langkah->ket = 'Ditandatangani manual oleh pelaksana, dicatat oleh ' . Employe::employeId();
            $langkah->save();
            DB::connection('esign')->table('hotel30_tokens')->where('id_step', $langkah->id)->whereNull('dipakai_at')->delete();
            $this->catatLog($sppd->nomor_dokumen, $sppd->nama, $sppd->jabatan, 'Klaim hotel 30% disetujui pelaksana (tanda tangan manual)');
        });

        return response()->json(['status' => true, 'message' => 'Tercatat disetujui secara manual.'], 200);
    }

    // ---------- Publik (token) ----------

    private function cariToken(string $plain, bool $kunci = false)
    {
        $q = DB::connection('esign')->table('hotel30_tokens')->where('token_hash', hash('sha256', $plain));
        if ($kunci) {
            $q->lockForUpdate();
        }

        return $q->first();
    }

    public function publikInfo($token)
    {
        $t = $this->cariToken($token);
        if (!$t) {
            return response()->json(['status' => false, 'message' => 'Tautan tidak valid.'], 404);
        }
        $sppd = DB::connection('mysql4')->table('tujuan_sppds as t')->join('sppds as s', 's.id', '=', 't.id_sppd')->where('t.id', $t->id_tujuan)
            ->first(['s.nomor_sppd', 's.nomor_dokumen', 't.detail_tujuan']);
        $tp = TigaPuluh::where('id_tujuan', $t->id_tujuan)->orderByDesc('id')->first();

        return response()->json(['status' => true, 'data' => [
            'nama' => $t->nama,
            'jabatan' => $t->jabatan,
            'nomor' => $sppd->nomor_sppd ?? $t->id_doc,
            'tujuan' => $sppd->detail_tujuan ?? null,
            'mulai' => $tp->mulai ?? null,
            'selesai' => $tp->selesai ?? null,
            'sudah_dipakai' => $t->dipakai_at !== null,
            'hasil' => $t->hasil,
            'kedaluwarsa' => $t->dipakai_at === null && now()->gt($t->expires_at),
        ]], 200);
    }

    public function publikJawab(Request $request, $token)
    {
        $request->validate(['hasil' => 'required|in:setuju,tolak', 'catatan' => 'nullable|string|max:200']);
        $out = DB::connection('esign')->transaction(function () use ($request, $token) {
            $t = $this->cariToken($token, true);
            if (!$t) {
                return [404, 'Tautan tidak valid.'];
            }
            if ($t->dipakai_at !== null) {
                return [409, 'Tautan ini sudah dipakai.'];
            }
            if (now()->gt($t->expires_at)) {
                return [410, 'Tautan sudah kedaluwarsa. Minta pengaju mengirim ulang.'];
            }
            $langkah = VerifStep::where('id', $t->id_step)->lockForUpdate()->first();
            if (!$langkah || $langkah->status !== null) {
                return [409, 'Persetujuan ini sudah diproses.'];
            }
            $setuju = $request->input('hasil') === 'setuju';
            $langkah->status = $setuju ? 'approve' : 'rejected';
            $langkah->ket = substr(trim(($setuju ? 'Disetujui' : 'Ditolak') . ' oleh ' . $t->nama . ' melalui tautan email. ' . $request->input('catatan')), 0, 250);
            $langkah->save();
            DB::connection('esign')->table('hotel30_tokens')->where('id', $t->id)->update([
                'dipakai_at' => now(), 'hasil' => $setuju ? 'setuju' : 'tolak', 'ip' => $request->ip(), 'updated_at' => now(),
            ]);
            $this->catatLog($t->id_doc, $t->nama, $t->jabatan, $setuju ? 'Klaim hotel 30% disetujui pelaksana (tautan email)' : 'Klaim hotel 30% ditolak pelaksana (tautan email)');

            return [200, $setuju ? 'Terima kasih, persetujuan Anda tercatat.' : 'Penolakan Anda tercatat.'];
        });

        return response()->json(['status' => $out[0] === 200, 'message' => $out[1]], $out[0]);
    }
}
