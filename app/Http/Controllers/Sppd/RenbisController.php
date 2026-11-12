<?php

namespace App\Http\Controllers\Sppd;

use App\Http\Controllers\Controller;
use App\Http\Resources\PostResource;
use App\Models\Employe;
use App\Models\Sppd\Renbis;
use App\Models\Sppd\TujuanSppd;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Atur renbis (RKAP PEMA) per tahun. Hanya PUK (pimpinan unit kerja) divisi Keuangan:
 * orang yang tercatat di kolom PUK pada tabel divisions untuk divisi yang namanya memuat "Keuangan".
 */
class RenbisController extends Controller
{
    private function boleh(): bool
    {
        return DB::table('divisions')
            ->where('PUK', Employe::employeId())
            ->where('organization_name', 'like', '%Keuangan%')
            ->exists();
    }

    private function ditolak()
    {
        return response()->json(['success' => false, 'message' => 'Menu ini hanya untuk PUK divisi Keuangan.'], 403);
    }

    private function gagal(string $pesan, int $kode = 422)
    {
        return response()->json(['success' => false, 'message' => $pesan], $kode);
    }

    private function aturan(Request $request): array
    {
        return $request->validate([
            'renbis' => 'required|string|max:255',
            'tahun' => 'required|integer|between:2000,2100',
            'rkap' => 'required|numeric|min:0|max:999999999999999',
        ], [
            'renbis.required' => 'Nama renbis wajib diisi.',
            'tahun.required' => 'Tahun wajib diisi.',
            'rkap.required' => 'Nilai RKAP wajib diisi.',
            'rkap.min' => 'Nilai RKAP tidak boleh negatif.',
        ]);
    }

    // dipakai frontend untuk menampilkan atau menyembunyikan menu
    public function akses()
    {
        return new PostResource(true, 'akses renbis', ['akses' => $this->boleh()]);
    }

    // daftar renbis satu tahun (default tahun berjalan) beserta pilihan tahun yang tersedia
    public function index(Request $request)
    {
        if (!$this->boleh()) {
            return $this->ditolak();
        }
        $tahun = (int) ($request->tahun ?: date('Y'));
        $tahunTersedia = Renbis::query()->distinct()->orderByDesc('tahun')->pluck('tahun')->map(fn ($t) => (int) $t)->all();
        if (!in_array((int) date('Y'), $tahunTersedia, true)) {
            $tahunTersedia[] = (int) date('Y');
            rsort($tahunTersedia);
        }

        $rows = Renbis::where('tahun', $tahun)->orderBy('renbis')->get();
        // jumlah pemakaian per renbis (SPPD), supaya kelihatan mana yang tidak bisa dihapus
        $dipakai = TujuanSppd::whereIn('rkap', $rows->pluck('id'))->selectRaw('rkap, count(*) as jml')->groupBy('rkap')->pluck('jml', 'rkap');
        $data = $rows->map(fn ($r) => [
            'id' => $r->id,
            'renbis' => $r->renbis,
            'tahun' => (int) $r->tahun,
            'rkap' => (int) $r->rkap,
            'dipakai' => (int) ($dipakai[$r->id] ?? 0),
        ]);

        return new PostResource(true, 'data renbis', ['tahun' => $tahun, 'tahun_tersedia' => $tahunTersedia, 'data' => $data]);
    }

    public function store(Request $request)
    {
        if (!$this->boleh()) {
            return $this->ditolak();
        }
        $d = $this->aturan($request);
        if (Renbis::where('tahun', $d['tahun'])->whereRaw('LOWER(renbis) = ?', [mb_strtolower(trim($d['renbis']))])->exists()) {
            return $this->gagal('Renbis dengan nama itu sudah ada di tahun ' . $d['tahun'] . '.');
        }
        $r = Renbis::create(['renbis' => trim($d['renbis']), 'tahun' => $d['tahun'], 'rkap' => $d['rkap']]);

        return new PostResource(true, 'Renbis ditambahkan', ['id' => $r->id]);
    }

    public function update(Request $request, $id)
    {
        if (!$this->boleh()) {
            return $this->ditolak();
        }
        $r = Renbis::find($id);
        if (!$r) {
            return $this->gagal('Renbis tidak ditemukan.', 404);
        }
        $d = $this->aturan($request);
        if (Renbis::where('id', '!=', $r->id)->where('tahun', $d['tahun'])->whereRaw('LOWER(renbis) = ?', [mb_strtolower(trim($d['renbis']))])->exists()) {
            return $this->gagal('Renbis dengan nama itu sudah ada di tahun ' . $d['tahun'] . '.');
        }
        $r->update(['renbis' => trim($d['renbis']), 'tahun' => $d['tahun'], 'rkap' => $d['rkap']]);

        return new PostResource(true, 'Renbis diperbarui', []);
    }

    public function destroy($id)
    {
        if (!$this->boleh()) {
            return $this->ditolak();
        }
        $r = Renbis::find($id);
        if (!$r) {
            return $this->gagal('Renbis tidak ditemukan.', 404);
        }
        // renbis yang sudah dipakai SPPD atau booking rapat tidak boleh dihapus: datanya akan kehilangan acuan
        $sppd = TujuanSppd::where('rkap', $r->id)->count();
        $rapat = DB::table('meetings')->where('renbis_id', $r->id)->count();
        if ($sppd > 0 || $rapat > 0) {
            return $this->gagal("Renbis ini sudah dipakai ($sppd tujuan SPPD, $rapat booking rapat) sehingga tidak bisa dihapus. Ubah namanya bila perlu.");
        }
        $r->delete();

        return new PostResource(true, 'Renbis dihapus', []);
    }
}
