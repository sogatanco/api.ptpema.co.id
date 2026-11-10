<?php

namespace App\Http\Controllers\Adm;

use App\Http\Controllers\Controller;
use App\Http\Resources\PostResource;
use App\Models\Adm\PenomoranSurat;
use App\Models\Adm\Surat;
use App\Models\Adm\SuratBackdate;
use App\Models\Employe;
use App\Models\Structure;
use App\Services\SuratBackdateService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

/**
 * Surat bertanggal mundur: AdminAdm mengajukan, PICAdm memilih nomor dari saran sistem (lihat SuratBackdateService).
 * Surat baru dibuat (dan masuk alur paraf lewat trigger) saat PICAdm menyetujui; sebelum itu tidak ada nomor yang terpakai.
 */
class SuratBackdateController extends Controller
{
    private function pic(): bool
    {
        return in_array('PICAdm', auth()->user()->roles ?? [], true);
    }

    private function gagal(string $pesan, int $kode = 422)
    {
        return response()->json(['success' => false, 'message' => $pesan], $kode);
    }

    private function nomorLengkap(string $nomor, string $kode, Carbon $d): string
    {
        return $nomor . '/' . $kode . '/' . (new SuratController())->getRomawi($d->month) . '/' . $d->year;
    }

    public function ajukan(Request $request)
    {
        try {
            $d = SuratBackdateService::validasiTanggal((string) $request->tanggal);
        } catch (InvalidArgumentException $e) {
            return $this->gagal($e->getMessage());
        }

        $req = SuratBackdate::create([
            'requested_by' => Employe::employeId(),
            'tanggal' => $d->toDateString(),
            'payload' => json_encode($request->only(['kepada', 'perihal', 'lampiran', 'jenislampiran', 'isiSurat', 'tembusans', 'divisi', 'ttdBy', 'bhs']), JSON_UNESCAPED_UNICODE),
            'status' => 'menunggu',
        ]);

        if ($request->lampiran + 0 > 0) {
            $file = base64_decode(str_replace('data:application/pdf;base64,', '', (string) $request->fileLampiran), true);
            $nama = 'lampiran/backdate/' . $req->id . '.pdf';
            if ($file !== false && Storage::disk('public_adm')->put($nama, $file)) {
                $req->file_lampiran = $nama;
                $req->save();
            }
        }

        return new PostResource(true, 'Permintaan backdate dikirim ke PICAdm', ['id' => $req->id]);
    }

    // PICAdm melihat semua permintaan; AdminAdm hanya miliknya sendiri
    public function daftar()
    {
        $q = SuratBackdate::query()->latest('id')->limit(100);
        if (!$this->pic()) {
            $q->where('requested_by', Employe::employeId());
        }
        $rows = $q->get();
        $names = Structure::whereIn('employe_id', $rows->pluck('requested_by')->unique())->pluck('first_name', 'employe_id');

        $data = $rows->map(function ($r) use ($names) {
            $p = json_decode($r->payload, true) ?: [];

            return [
                'id' => $r->id,
                'status' => $r->status,
                'tanggal' => substr((string) $r->getRawOriginal('tanggal'), 0, 10),
                'pemohon' => $names[$r->requested_by] ?? $r->requested_by,
                'perihal' => $p['perihal'] ?? '',
                'kepada' => $p['kepada'] ?? '',
                'nomor_surat' => $r->nomor_surat,
                'alasan_tolak' => $r->alasan_tolak,
                'created_at' => $r->created_at,
            ];
        });

        return new PostResource(true, 'permintaan backdate', $data);
    }

    public function saran(Request $request, $id)
    {
        $req = SuratBackdate::findOrFail($id);
        try {
            $d = SuratBackdateService::validasiTanggal((string) ($request->tanggal ?: substr((string) $req->getRawOriginal('tanggal'), 0, 10)));
            $saran = SuratBackdateService::saran($d);
        } catch (InvalidArgumentException $e) {
            return $this->gagal($e->getMessage());
        }
        $kode = PenomoranSurat::where('type', 'main')->first()->kode;
        foreach ($saran as &$s) {
            $s['nomor_surat'] = $this->nomorLengkap($s['nomor'], $kode, $d);
        }

        return new PostResource(true, 'saran nomor', $saran);
    }

    public function setujui(Request $request, $id)
    {
        try {
            return DB::connection('adm')->transaction(function () use ($request, $id) {
                $pen = PenomoranSurat::where('type', 'main')->lockForUpdate()->first();
                $req = SuratBackdate::lockForUpdate()->findOrFail($id);
                if ($req->status !== 'menunggu') {
                    throw new InvalidArgumentException('Permintaan ini sudah diproses.');
                }
                $d = SuratBackdateService::validasiTanggal((string) ($request->tanggal ?: substr((string) $req->getRawOriginal('tanggal'), 0, 10)));
                // nomor harus salah satu saran terbaru untuk tanggal itu (mencegah nomor sembarang atau nomor yang baru saja terpakai)
                if (!in_array((string) $request->nomor, array_column(SuratBackdateService::saran($d), 'nomor'), true)) {
                    throw new InvalidArgumentException('Nomor tidak lagi tersedia; muat ulang saran nomor.');
                }
                $p = json_decode($req->payload, true) ?: [];
                $sebelum = $pen->last_number;

                $surat = new Surat();
                if ($req->file_lampiran && Storage::disk('public_adm')->exists($req->file_lampiran)) {
                    $akhir = 'lampiran/' . $d->year . '/' . $request->nomor . '.pdf';
                    if (Storage::disk('public_adm')->put($akhir, Storage::disk('public_adm')->get($req->file_lampiran))) {
                        $surat->file_lampiran = $akhir;
                        Storage::disk('public_adm')->delete($req->file_lampiran);
                    }
                }
                $surat->nomor_surat = $this->nomorLengkap((string) $request->nomor, $pen->kode, $d);
                $surat->no_document = unique_random('documents', 'doc_id', 40);
                $surat->kepada = $p['kepada'] ?? null;
                $surat->perihal = $p['perihal'] ?? null;
                $surat->j_lampiran = $p['lampiran'] ?? 0;
                $surat->jenis_lampiran = $p['jenislampiran'] ?? null;
                $surat->isi_surat = $p['isiSurat'] ?? null;
                $surat->tembusans = implode(',', $p['tembusans'] ?? []);
                $surat->id_divisi = $p['divisi'] ?? null;
                // pemilik surat tetap admin pemohon: surat muncul di daftarnya dan ia yang bisa memperbaiki (Update)
                $surat->submitted_by = $req->requested_by;
                $surat->submitted_current_position = Structure::where('employe_id', $req->requested_by)->first('position_name')->position_name;
                $surat->sign_by = $p['ttdBy'] ?? null;
                $surat->bahasa = $p['bhs'] ?? 'id';
                $surat->created_at = $d->copy()->setTimeFrom(now());
                $surat->save();
                // trigger update_nomor2 menambah last_number pada setiap surat baru; surat backdate tidak boleh memakai nomor hari ini
                PenomoranSurat::where('type', 'main')->update(['last_number' => $sebelum]);

                $req->update([
                    'status' => 'selesai',
                    'tanggal' => $d->toDateString(),
                    'nomor_surat' => $surat->nomor_surat,
                    'surat_id' => $surat->id,
                    'processed_by' => Employe::employeId(),
                ]);

                return new PostResource(true, 'Surat dibuat dengan nomor ' . $surat->nomor_surat, ['nomor_surat' => $surat->nomor_surat]);
            });
        } catch (InvalidArgumentException $e) {
            return $this->gagal($e->getMessage());
        }
    }

    public function tolak(Request $request, $id)
    {
        $req = SuratBackdate::findOrFail($id);
        if ($req->status !== 'menunggu') {
            return $this->gagal('Permintaan ini sudah diproses.');
        }
        $req->update(['status' => 'ditolak', 'alasan_tolak' => (string) $request->alasan, 'processed_by' => Employe::employeId()]);
        if ($req->file_lampiran) {
            Storage::disk('public_adm')->delete($req->file_lampiran);
        }

        return new PostResource(true, 'Permintaan ditolak', []);
    }
}
