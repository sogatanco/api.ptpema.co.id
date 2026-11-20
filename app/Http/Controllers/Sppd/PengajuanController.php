<?php

namespace App\Http\Controllers\Sppd;

use App\Http\Controllers\Controller;
use App\Http\Resources\PostResource;
use App\Models\Sppd\Ekstend;
use App\Models\Sppd\EkstendView;
use App\Models\Sppd\TigaPuluh;
use Illuminate\Http\Request;
use App\Models\Sppd\Sppd;
use App\Models\Sppd\SppdVoid;
use App\Services\Notifikasi\Peristiwa;
use App\Services\SuratLogService;
use App\Models\Sppd\TujuanSppd;
use App\Models\Employe;
use App\Services\CryptoService;
use App\Services\SignatureService;
use App\Services\SuratTteService;
use App\Models\ESign\VerifStep;
use App\Models\Sppd\ApprovedSppd;
use App\Models\Sppd\CheklistDoc;
use App\Models\Sppd\CheklistDokRealisasi;
use App\Models\Sppd\Dashboard;
use App\Models\Sppd\GroupByKar;
use App\Models\Sppd\HitunganBiaya;
use App\Models\Sppd\PenomoranSppd;
use App\Models\Sppd\KetetapanSppd;
use App\Models\Sppd\ListApproval;
use Illuminate\Support\Facades\Storage;
use App\Models\Sppd\ListSppdRingan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Models\Structure;
use App\Models\Sppd\UnderReview;
use App\Models\Sppd\LogPengajuan;
use App\Models\Sppd\Proses;
use App\Models\Sppd\ProsesTiket;
use App\Models\Sppd\ProsesTermin;
use App\Services\Sppd\UangMuka;
use App\Services\Sppd\EkstendProses;
use App\Models\Sppd\Realisasi;
use App\Models\Sppd\RealisasiBiaya;
use App\Models\Sppd\RealisasiRkap;
use App\Models\Sppd\RealisasiTujuan;
use DateTime;
use Illuminate\Cache\RateLimiting\Limit;
use PhpParser\Node\Expr\Cast\Object_;

class PengajuanController extends Controller
{

    public function store(Request $request)
    {
        $sppd = new Sppd();
        $sppd->nomor_sppd = sprintf("%02d", ((PenomoranSppd::find($request->nomor)->last_number) + 1))  . '/PEMA/ST-' . PenomoranSppd::find($request->nomor)->kode . '/' . $this->getRomawi(date('m')) . '/' . date('Y');
        $sppd->nomor_dokumen = unique_random('documents', 'doc_id', 40);
        $sppd->employe_id = $request->employe_id;
        $sppd->nama = $request->name;
        $sppd->jabatan = $request->jabatann;
        $sppd->golongan_rate = $request->rate;
        $sppd->narasi_st = $request->narasi;
        $sppd->submitted_by = Employe::employeId();
        $sppd->ketetapan = KetetapanSppd::where('status', 'active')->first()->id;
        $tujuans = $request->tujuan_sppd;

        if ($sppd->save()) {

            for ($i = 0; $i < count($tujuans); $i++) {
                if ($tujuans[$i]['file_undangan'] !== '-') {
                    $file = base64_decode(str_replace('data:application/pdf;base64,', '', $tujuans[$i]['file_undangan']), true);
                    $fileName = 'undangan/' . date('Y') . '/' . $sppd->id . '/undangan-' . ($i + 1) . '.pdf';
                    if (Storage::disk('public_sppd')->put($fileName, $file)) {
                        $file_undangan = $fileName;
                    }
                } else {
                    $file_undangan = '-';
                }
                TujuanSppd::insert([
                    'id_sppd' => $sppd->id,
                    'jenis_sppd' => $tujuans[$i]['jenis_sppd'],
                    'dasar' => $tujuans[$i]['dasar_sppd'],
                    'file_undangan' => $file_undangan,
                    'klasifikasi' => $tujuans[$i]['klasifikasi'],
                    'sumber' => $tujuans[$i]['sumber_biaya'],
                    'rkap' => $tujuans[$i]['renbis'],
                    'p_tiket' => $tujuans[$i]['p_tiket'],
                    'p_um' => $tujuans[$i]['p_um'],
                    'p_tl' => $tujuans[$i]['p_tl'],
                    'p_us' => $tujuans[$i]['p_us'],
                    'p_hotel' => $tujuans[$i]['p_hotel'],
                    'kategori' => $tujuans[$i]['kategori_sppd'],
                    'detail_tujuan' => $tujuans[$i]['detail_tujuan'],
                    'tugas' => $tujuans[$i]['tugas_sppd'],
                    'waktu_berangkat' => date('Y-m-d H:i:s', strtotime($tujuans[$i]['waktu_berangkat'])),
                    'waktu_kembali' =>  date('Y-m-d H:i:s', strtotime($tujuans[$i]['waktu_kembali'])),
                    'moda' => $tujuans[$i]['moda'],
                    'bbm' => $tujuans[$i]['ubbm'],
                    'share_with' => $tujuans[$i]['shareWith'],
                    ...$this->kolomPerluTiket($tujuans[$i]),

                ]);
            }
            return new PostResource(true, 'success', []);
        }
    }

    // SPPD void tidak lagi masuk antrean review dan proses; di daftar lain tetap tampil dengan penanda dan alasannya
    private function tandaiVoid($data, array $void)
    {
        return collect($data)->map(function ($d) use ($void) {
            $id = (int) (is_array($d) ? ($d['id'] ?? 0) : $d->id);
            $alasan = $void[$id] ?? null;
            if (is_array($d)) {
                return $d + ['void' => $alasan !== null, 'alasan_void' => $alasan];
            }
            $d->void = $alasan !== null;
            $d->alasan_void = $alasan;

            return $d;
        })->values();
    }

    function getSubmitted(Request $request)
    {
        $void = SppdVoid::peta();
        $voidIds = array_map('intval', array_keys($void));
        if ($request->ref == 'mine') {
            $data = ListSppdRingan::where('employe_id', Employe::employeId())->orderBy('id', 'DESC')->get();
        } elseif ($request->ref == 'divisi') {
            $data = $this->sppdDivisi();
        } elseif ($request->ref == 'review') {
            $data = UnderReview::where('current_reviewer', Employe::employeId())->whereNotIn('id', $voidIds)->orderBy('id', 'DESC')->get();
        } elseif ($request->ref == 'approved_by') {
            $data = ApprovedSppd::where('approval_id', Employe::employeId())->get();
        } elseif ($request->ref == 'by_umum') {
            // sudah di-approve (signed), belum ada realisasi sama sekali, belum diproses UmumSppd; terbaru di atas
            // hanya SPPD yang punya tujuan perlu tiket; SPPD tanpa tiket langsung ke KeuanganSppd
            $data = $this->perluTiketQuery(
                ListSppdRingan::where('current_status', 'signed')->where('by_umum', 0)->whereNull('realisasi_status')->whereNotIn('id', $voidIds),
                true
            )->orderByDesc('id')->get();
            // Kontak WhatsApp pelaksana dan admin pengaju, agar UmumSppd bisa langsung konfirmasi sebelum memproses tiket
            $hp = $data->isEmpty() ? collect() : Structure::whereIn('employe_id', $data->pluck('employe_id')->merge($data->pluck('submitted_by'))->filter()->unique()->values())
                ->pluck('phone_number', 'employe_id');
            $jabatan = $data->isEmpty() ? collect() : Structure::whereIn('employe_id', $data->pluck('employe_id')->merge($data->pluck('submitted_by'))->filter()->unique()->values())
                ->pluck('position_name', 'employe_id');
            foreach ($data as $d) {
                $d->type_proses = 'pemesanan_tiket';
                $d->id_unique = rand(1, 100) * $d->id;
                $d->pelaksana_hp = $this->kontakWa($hp->get($d->employe_id));
                $d->pengaju_hp = $this->kontakWa($hp->get($d->submitted_by));
                $d->pengaju_jabatan = $jabatan->get($d->submitted_by);
            }
            // extend (tambah hari / tujuan baru) yang sudah disetujui dan perlu tiket: satu baris per SPPD, diproses terpisah dari SPPD awalnya
            $data = $data->concat($this->barisEkstend('umum', $voidIds))->sortByDesc('id')->values();
        } elseif ($request->ref == 'by_umum_selesai') {
            // SPPD yang sudah diproses UmumSppd, terbaru di atas; tiket tetap bisa diubah/ditambah walau KeuanganSppd sudah memproses (tiket pulang sering diupload belakangan)
            if (!in_array('UmumSppd', (array) auth()->user()->roles, true)) {
                return response()->json(['message' => 'Unauthorized!'], 401);
            }
            $data = ListSppdRingan::where('current_status', 'signed')->where('by_umum', 1)->whereNotIn('id', $voidIds)->orderByDesc('id')->get();
            $ids = $data->pluck('id')->all();
            $sumber = $data->isEmpty() ? collect() : Proses::whereIn('id_sppd', $ids)->where('as', 'umum')->orderBy('id')->pluck('sumber_tiket', 'id_sppd');
            $tiket = $data->isEmpty() ? collect() : ProsesTiket::whereIn('id_sppd', $ids)->selectRaw('id_sppd, count(*) as jumlah, sum(harga) as total')->groupBy('id_sppd')->get()->keyBy('id_sppd');
            $hp = $data->isEmpty() ? collect() : Structure::whereIn('employe_id', $data->pluck('employe_id')->merge($data->pluck('submitted_by'))->filter()->unique()->values())->pluck('phone_number', 'employe_id');
            $jabatan = $data->isEmpty() ? collect() : Structure::whereIn('employe_id', $data->pluck('employe_id')->merge($data->pluck('submitted_by'))->filter()->unique()->values())->pluck('position_name', 'employe_id');
            foreach ($data as $d) {
                $d->type_proses = 'pemesanan_tiket';
                $d->id_unique = $d->id;
                $d->sumber_tiket = $sumber->get($d->id);
                $d->jumlah_tiket = (int) ($tiket->get($d->id)->jumlah ?? 0);
                $d->total_tiket = (float) ($tiket->get($d->id)->total ?? 0);
                $d->sudah_keuangan = $this->keuanganSudahMemproses((int) $d->id, $d);
                $d->pelaksana_hp = $this->kontakWa($hp->get($d->employe_id));
                $d->pengaju_hp = $this->kontakWa($hp->get($d->submitted_by));
                $d->pengaju_jabatan = $jabatan->get($d->submitted_by);
            }
        } elseif ($request->ref == 'by_keuangan_selesai') {
            // SPPD yang sudah diproses KeuanganSppd (uang muka selesai / realisasi dibayar), terbaru di atas
            if (!in_array('KeuanganSppd', (array) auth()->user()->roles, true)) {
                return response()->json(['message' => 'Unauthorized!'], 401);
            }
            $um = ListSppdRingan::where('uangmuka', 1)->where('current_status', 'signed')->whereNotIn('id', $voidIds)->get();
            $rl = ListSppdRingan::where('by_keuangan', 1)->where('realisasi_status', 'verified')->whereNotIn('id', $voidIds)->get();
            $tglUm = $um->isEmpty() ? collect() : Proses::whereIn('id_sppd', $um->pluck('id'))->where('as', 'uangmuka')->orderBy('id')->pluck('created_at', 'id_sppd');
            $tglRl = $rl->isEmpty() ? collect() : Proses::whereIn('id_sppd', $rl->pluck('id'))->where('as', 'realisasi')->orderBy('id')->pluck('created_at', 'id_sppd');
            $data = collect();
            $aktualUm = ($um->isEmpty() || !$this->adaKolomAktual()) ? collect() : ProsesTermin::whereIn('id_sppd', $um->pluck('id'))->where('ditunda_aktual', 1)->orderBy('termin')->get()->groupBy('id_sppd');
            foreach ($um as $d) {
                $d->type_proses = 'uangmuka';
                $d->id_unique = $d->id . '-um';
                $d->tgl_proses = (string) ($tglUm->get($d->id) ?? '');
                $d->uangmuka_aktual = $aktualUm->has($d->id);
                $d->termin_aktual = $aktualUm->has($d->id) ? $aktualUm->get($d->id)->pluck('termin')->unique()->values()->all() : [];
                $d->catatan_aktual = $aktualUm->has($d->id) ? ($aktualUm->get($d->id)->pluck('catatan')->filter()->unique()->implode('; ') ?: null) : null;
                $data->push($d);
            }
            foreach ($rl as $d) {
                $d->type_proses = 'realisasi';
                $d->id_unique = $d->id . '-rl';
                $d->tgl_proses = (string) ($tglRl->get($d->id) ?? '');
                $data->push($d);
            }
            $data = $data->sortByDesc('id')->values();
        } elseif ($request->ref == 'by_keuangan') {
            // SPPD baru (dibuat sejak config sppd.proses_paralel_mulai): divisi keuangan dan divisi umum bekerja bersamaan, uang muka bisa
            // diproses begitu SPPD disetujui tanpa menunggu tiket. SPPD lama tetap: setelah divisi umum selesai (by_umum = 1) atau tanpa tiket.
            $siapKeuangan = fn ($q) => $q->where('created_at', '>=', config('sppd.proses_paralel_mulai'))
                ->orWhere('by_umum', 1)->orWhere(fn ($w) => $this->perluTiketQuery($w, false));
            $data1 = ListSppdRingan::where('uangmuka', 0)->where($siapKeuangan)->where('current_status', 'signed')->whereNotIn('id', $voidIds)->get();

            $data1 = $data1->map(function ($d1) {
                $d1->type_proses = 'uangmuka';
                $d1array = $d1->toArray();
                $d1array = ['id_unique' => (rand(1, 100) * $d1->id)] + $d1array;
                return $d1array;
            });


            $data2 = ListSppdRingan::where('realisasi_status', 'verified')->where($siapKeuangan)->where('by_keuangan', 0)->whereNotIn('id', $voidIds)->get();
            $data2 = $data2->map(function ($d2) {
                $d2->type_proses = 'realisasi';
                $d2array = $d2->toArray();
                $d2array = ['id_unique' => (rand(1, 100) * $d2->id)] + $d2array;
                return $d2array;
            });
            $tempCollection = collect([$data1, $data2]);
            $data = $tempCollection->flatten(1)
                ->concat($this->barisEkstend('keuangan', $voidIds)->map(fn ($d) => ['id_unique' => $d->id_unique] + $d->toArray()))
                ->sortByDesc('id')->values(); // terbaru di atas
            $data = $this->tandaiProgresTermin($data);
        } else {
            $data = $this->sppdSayaAjukan();
        }

        return new PostResource(true, $request->ref, $this->tandaiEkstend($this->tandaiVoid($data, $void)));
    }

    /** jumlah extend yang pernah diajukan per SPPD (jumlah_ekstend), agar daftar bisa menandai SPPD yang sudah extend */
    private function tandaiEkstend($data)
    {
        $data = collect($data);
        $ids = $data->map(fn ($d) => (int) (is_array($d) ? ($d['id'] ?? 0) : $d->id))->filter()->unique()->values();
        $peta = $ids->isEmpty() ? collect() : DB::connection('mysql4')->table('ekstends')
            ->join('tujuan_sppds', 'tujuan_sppds.id', '=', 'ekstends.id_tujuan')
            ->whereIn('tujuan_sppds.id_sppd', $ids)
            ->when(EkstendProses::adaNoUrut(), fn ($q) => $q->whereNull('ekstends.dibatalkan_at'))
            ->selectRaw('tujuan_sppds.id_sppd as id_sppd, count(*) as jumlah')
            ->groupBy('tujuan_sppds.id_sppd')->pluck('jumlah', 'id_sppd');

        return $data->map(function ($d) use ($peta) {
            $id = (int) (is_array($d) ? ($d['id'] ?? 0) : $d->id);
            $n = (int) ($peta[$id] ?? 0);
            if (is_array($d)) {
                return $d + ['jumlah_ekstend' => $n];
            }
            $d->jumlah_ekstend = $n;

            return $d;
        })->values();
    }

    // ---------- Perhitungan uang muka: aturannya ada di App\Services\Sppd\UangMuka ----------

    private function rincianUangMuka($t, float $tiket): array
    {
        return \App\Services\Sppd\UangMuka::rincian($t, $tiket);
    }

    // ---------- "Perlu tiket" per tujuan: SPPD tanpa tiket tidak lewat UmumSppd, langsung ke KeuanganSppd ----------

    /** kolom tujuan_sppds.perlu_tiket (skrip db/2026-11-18/tujuan_perlu_tiket.sql); bila belum ada, semua SPPD dianggap perlu tiket (alur lama) */
    private function adaKolomPerluTiket(): bool
    {
        return \Illuminate\Support\Facades\Cache::remember('tujuan_sppds_has_perlu_tiket', 300, fn () => Schema::connection('mysql4')->hasColumn('tujuan_sppds', 'perlu_tiket'));
    }

    /** isian perlu_tiket untuk disimpan ke tujuan_sppds (kosong bila kolomnya belum ada); tidak dikirim = perlu tiket */
    private function kolomPerluTiket(array $tujuan): array
    {
        if (!$this->adaKolomPerluTiket()) {
            return [];
        }

        return ['perlu_tiket' => isset($tujuan['perlu_tiket']) ? ((int) filter_var($tujuan['perlu_tiket'], FILTER_VALIDATE_BOOLEAN) ? 1 : 0) : 1];
    }

    /** batasi query list_sppd_ringan: SPPD yang punya minimal satu tujuan perlu tiket ($perlu true) atau yang tidak punya sama sekali ($perlu false) */
    private function perluTiketQuery($q, bool $perlu)
    {
        if (!$this->adaKolomPerluTiket()) {
            return $perlu ? $q : $q->whereRaw('1 = 0');
        }

        return $q->{$perlu ? 'whereExists' : 'whereNotExists'}(fn ($s) => $s->select(DB::raw(1))->from('tujuan_sppds')
            ->whereColumn('tujuan_sppds.id_sppd', 'list_sppd_ringan.id')->where('tujuan_sppds.perlu_tiket', 1));
    }

    /**
     * Baris daftar PERLU DIPROSES untuk extend yang sudah disetujui, satu baris per SPPD (mode_ekstend = true).
     * 'umum'    : perlu tiket dan belum diproses divisi umum.
     * 'keuangan': belum selesai uang mukanya (tidak menunggu divisi umum; keduanya bisa berjalan bersamaan).
     */
    private function barisEkstend(string $untuk, array $voidIds)
    {
        if (!EkstendProses::aktif()) {
            return collect();
        }
        $daftar = EkstendProses::disetujui()->filter(function ($e) use ($untuk) {
            return $untuk === 'umum'
                ? (int) $e->perlu_tiket === 1 && (int) $e->by_umum === 0
                : (int) $e->uangmuka === 0;
        });
        if ($daftar->isEmpty()) {
            return collect();
        }
        $peta = [];
        foreach ($daftar as $e) {
            $idSppd = EkstendProses::idSppd($e);
            if ($idSppd) {
                $peta[$idSppd][] = $e;
            }
        }
        $rows = ListSppdRingan::whereIn('id', array_keys($peta))->where('current_status', 'signed')->whereNotIn('id', $voidIds)->get();
        $orang = $rows->pluck('employe_id')->merge($rows->pluck('submitted_by'))->filter()->unique()->values();
        $hp = $rows->isEmpty() ? collect() : Structure::whereIn('employe_id', $orang)->pluck('phone_number', 'employe_id');
        $jabatan = $rows->isEmpty() ? collect() : Structure::whereIn('employe_id', $orang)->pluck('position_name', 'employe_id');
        foreach ($rows as $d) {
            $items = collect($peta[$d->id]);
            $d->type_proses = $untuk === 'umum' ? 'pemesanan_tiket' : 'uangmuka';
            $d->mode_ekstend = true;
            $d->ekstend_ids = $items->pluck('id')->map(fn ($v) => (int) $v)->all();
            $d->ekstend_judul = $items->map(fn ($e) => EkstendProses::judul($e))->implode('; ');
            $d->id_unique = 'ek-' . $untuk . '-' . $d->id;
            $d->pelaksana_hp = $this->kontakWa($hp->get($d->employe_id));
            $d->pengaju_hp = $this->kontakWa($hp->get($d->submitted_by));
            $d->pengaju_jabatan = $jabatan->get($d->submitted_by);
        }

        return $rows;
    }

    // Nomor telepon (bisa lebih dari satu, dipisah koma/titik koma/spasi) -> [{tampil: '0812...', wa: '62812...'}]
    private function kontakWa($raw)
    {
        $hasil = [];
        foreach (preg_split('/[;,|\r\n]+/', (string) $raw) as $bagian) {
            $digit = preg_replace('/\D+/', '', $bagian);
            if ($digit === '') {
                continue;
            }
            if (strpos($digit, '0') === 0) {
                $digit = '62' . substr($digit, 1);
            } elseif (strpos($digit, '62') !== 0) {
                $digit = '62' . $digit;
            }
            if (strlen($digit) < 10 || strlen($digit) > 15) {
                continue;
            }
            $hasil[$digit] = ['tampil' => '0' . substr($digit, 2), 'wa' => $digit];
        }

        return array_values($hasil);
    }

    // Jabatan sekretaris direksi/dirut: tidak dihitung sebagai anggota divisi, hanya saling melihat sesama sekretaris
    private function jabatanSekretaris($namaJabatan)
    {
        return (bool) preg_match('/^sekretaris\s+(direksi|dirut|direktur\s+utama)/i', (string) $namaJabatan);
    }

    // Divisi dan status sekretaris pengaju tiap SPPD pada saat SPPD dibuat, dari riwayat jabatan HR (employee_histories).
    // Tanpa riwayat -> jabatan terakhir/saat ini (struktur aktif, atau tabel HR untuk yang sudah keluar).
    // Mengembalikan [id sppd => ['div' => id divisi|null, 'sek' => bool]].
    private function divisiPengajuPerSppd($baris)
    {
        $hr = config('database.connections.hr.database');
        $jabatan = DB::table("$hr.positions")->get(['id', 'division_id', 'position_name'])->keyBy('id');

        // jabatan utama saja (bukan penugasan PIC/PS), urut waktu
        $riwayat = DB::table("$hr.employee_histories")
            ->where(function ($q) {
                $q->whereNull('as_pic')->orWhere('as_pic', 0);
            })
            ->whereNotIn('contract_need', ['PIC', 'PS'])
            ->whereNotNull('position_id')
            ->orderBy('start_date')->orderBy('id')
            ->get(['employe_id', 'position_id', 'start_date'])
            ->groupBy(fn ($r) => (string) $r->employe_id);

        $sekarang = null; // dimuat hanya bila ada pengaju tanpa riwayat
        $hasil = [];
        foreach ($baris as $b) {
            $pengaju = (string) $b->submitted_by;
            $div = null;
            $nama = null;
            $rows = $riwayat->get($pengaju);
            if ($rows && $rows->isNotEmpty()) {
                $tgl = substr((string) $b->created_at, 0, 10);
                $pakai = $rows->filter(fn ($r) => $r->start_date <= $tgl)->last() ?? $rows->first();
                $j = $jabatan->get($pakai->position_id);
                $div = $j->division_id ?? null;
                $nama = $j->position_name ?? null;
            }
            if (!$div) {
                if ($sekarang === null) {
                    $struktur = Structure::get(['employe_id', 'organization_id', 'position_name'])
                        ->mapWithKeys(fn ($r) => [(string) $r->employe_id => [$r->organization_id, $r->position_name]]);
                    $dariHr = DB::table('employees_hr as e')
                        ->join("$hr.positions as p", 'p.id', '=', 'e.position_id')
                        ->get(['e.employe_id', 'p.division_id', 'p.position_name'])
                        ->mapWithKeys(fn ($r) => [(string) $r->employe_id => [$r->division_id, $r->position_name]]);
                    $sekarang = collect($struktur->all() + $dariHr->all()); // struktur aktif didahulukan; '+' menjaga id numerik
                }
                [$div, $nama] = $sekarang->get($pengaju, [null, null]);
            }
            $hasil[$b->id] = ['div' => $div ? (int) $div : null, 'sek' => $this->jabatanSekretaris($nama)];
        }

        return $hasil;
    }

    // SPPD yang saya ajukan di divisi saya saat ini. SPPD yang dulu saya ajukan di divisi lama
    // (sebelum pindah) pindah ke tab "diajukan tim" admin divisi lama.
    private function sppdSayaAjukan()
    {
        $me = Employe::employeId();
        $org = Employe::getEmployeDivision($me)->organization_id ?? null;
        $baris = Sppd::where('submitted_by', $me)->get(['id', 'submitted_by', 'created_at']);
        $div = $this->divisiPengajuPerSppd($baris);
        $ids = $baris->filter(fn ($b) => ($div[$b->id]['div'] ?? null) === null || (int) $div[$b->id]['div'] === (int) $org)->pluck('id')->all();

        return ListSppdRingan::whereIn('id', $ids)->orderBy('id', 'DESC')->get();
    }

    // SPPD yang diajukan admin lain di divisi yang sama (termasuk pengaju yang sudah resign/pindah divisi),
    // untuk AdminSPPD pengganti. SPPD direksi/dirut hanya tampil bagi AdminSPPD berjabatan Sekretaris Direksi/Dirut.
    private function sppdDivisi()
    {
        $me = Employe::employeId();
        $roles = auth()->user()->roles ?? [];
        if (!in_array('AdminSPPD', $roles, true)) {
            return collect();
        }
        $org = Employe::getEmployeDivision($me)->organization_id ?? null;
        if (!$org) {
            return collect();
        }

        $posisi = (string) Structure::where('employe_id', $me)->value('position_name');
        $sekdir = $this->jabatanSekretaris($posisi);
        $direksi = Structure::whereIn('id_base', [3, 4])->pluck('employe_id')->map(fn ($v) => (string) $v)->all();

        $baris = Sppd::where('submitted_by', '!=', $me)->get(['id', 'submitted_by', 'created_at']);
        $div = $this->divisiPengajuPerSppd($baris);
        // sekretaris: hanya melihat SPPD yang diajukan sesama sekretaris (lintas divisi) dan SPPD direksi/dirut.
        // admin lain: SPPD staf divisinya, tanpa SPPD sekretaris dan SPPD direksi/dirut.
        $ids = $baris->filter(function ($b) use ($div, $org, $sekdir) {
            $info = $div[$b->id] ?? ['div' => null, 'sek' => false];
            return $sekdir ? $info['sek'] : (!$info['sek'] && $info['div'] === (int) $org);
        })->pluck('id')->all();

        $data = ListSppdRingan::whereIn('id', $ids)->orderBy('id', 'DESC')->get();
        if ($sekdir) {
            // tambah SPPD yang pemberangkat/pengajunya direksi
            $tambahan = ListSppdRingan::where('submitted_by', '!=', $me)
                ->where(function ($q) use ($direksi) {
                    $q->whereIn('employe_id', $direksi)->orWhereIn('submitted_by', $direksi);
                })->whereNotIn('id', $ids)->get();
            $data = $data->concat($tambahan)->sortByDesc('id');
        } else {
            $data = $data->filter(fn ($d) => !in_array((string) $d->employe_id, $direksi, true) && !in_array((string) $d->submitted_by, $direksi, true));
        }
        $data = $data->values();

        return $this->tandaiVoid($data, SppdVoid::peta());
    }

    // Tandai SPPD void (belum atau sudah di-approve) dengan alasan; tidak bisa lagi setelah verifikasi realisasi selesai.
    // Hanya AdminSPPD atau admin yang mengajukan SPPD itu.
    function void(Request $request, $id)
    {
        $sppd = Sppd::find($id);
        if (!$sppd) {
            return response()->json(['success' => false, 'message' => 'SPPD tidak ditemukan.'], 404);
        }
        $me = Employe::employeId();
        if (!in_array('AdminSPPD', auth()->user()->roles ?? [], true) && (string) $sppd->submitted_by !== (string) $me) {
            return response()->json(['success' => false, 'message' => 'Hanya admin SPPD atau pengaju SPPD yang bisa menandai void.'], 403);
        }
        $alasan = trim((string) $request->alasan);
        if (mb_strlen($alasan) < 5) {
            return response()->json(['success' => false, 'message' => 'Alasan void wajib diisi (minimal 5 karakter).'], 422);
        }
        if (SppdVoid::where('sppd_id', $sppd->id)->exists()) {
            return response()->json(['success' => false, 'message' => 'SPPD ini sudah ditandai void.'], 422);
        }
        if (ListSppdRingan::where('id', $sppd->id)->where('realisasi_status', 'verified')->exists()) {
            return response()->json(['success' => false, 'message' => 'Verifikasi realisasi SPPD ini sudah selesai, tidak bisa di-void.'], 422);
        }

        SppdVoid::create(['sppd_id' => $sppd->id, 'nomor_sppd' => $sppd->nomor_sppd, 'voided_by' => $me, 'alasan' => $alasan]);
        SuratLogService::catat($sppd->nomor_dokumen, $me, 'Menandai SPPD void (tidak jadi berangkat/tidak digunakan lagi): ' . $alasan);
        Peristiwa::sppdVoid($sppd, $alasan, $me);

        return new PostResource(true, 'SPPD ' . $sppd->nomor_sppd . ' ditandai void.', []);
    }

    // SPPD void tidak bisa diubah, disetujui, atau direalisasikan lagi
    private function tolakBilaVoid($sppd)
    {
        if ($sppd && in_array((int) $sppd->id, SppdVoid::ids(), true)) {
            return response()->json(['success' => false, 'message' => 'SPPD ini sudah ditandai void.'], 422);
        }

        return null;
    }

    // realisasi_biaya rebuilds hitungan_biaya (~0.5s), so skip it for an SPPD without realisasi
    private function hasRealisasi($id)
    {
        return DB::connection('mysql4')->table('realisasi_tujuan as a')
            ->join('tujuan_sppds as t', 't.id', '=', 'a.id_tujuan')
            ->where('t.id_sppd', $id)->exists();
    }




    function getDetail($id, Request $request)
    {
       // Undangan base64 is only needed by the edit form; skip the heavy encoding otherwise
       $withFile = $request->boolean('file');
        $data = ListSppdRingan::find($id);
        // Not in list_sppd_ringan: summing them inside the view rebuilds hitungan_biaya for every SPPD
        $biaya = DB::connection('mysql4')->table('hitungan_biaya')
            ->selectRaw('sum(total_biaya) as perkiraan_total, sum(uang_muka) as uang_muka')->where('id_sppd', $id)->first();
        if ($data) {
            $data->perkiraan_total = $biaya->perkiraan_total;
            $data->uang_muka = $biaya->uang_muka;
        }
        $tujuans = HitunganBiaya::where('id_sppd', $id)->get();
        $tigaPuluhIds = TigaPuluh::whereIn('id_tujuan', $tujuans->pluck('id'))->pluck('id_tujuan')->all();
        // isian "Perlu tiket" per tujuan (view hitungan_biaya tidak memuatnya); tanpa kolom/data = perlu tiket
        $perluTiket = $this->adaKolomPerluTiket() ? TujuanSppd::where('id_sppd', $id)->pluck('perlu_tiket', 'id') : collect();
        $buktiTermin = $this->adaTabelTermin() ? ProsesTermin::where('id_sppd', $id)->get()->keyBy(fn ($r) => $r->id_tujuan . ':' . $r->termin) : collect();
        $tiketPerTujuan = ProsesTiket::where('id_sppd', $id)->selectRaw('id_tujuan, sum(harga) as total')->groupBy('id_tujuan')->pluck('total', 'id_tujuan');
        foreach ($tujuans as $t) {
            $t->perlu_tiket = (int) ($perluTiket[$t->id] ?? 1);
            if ($withFile && $t->file_undangan !== '-') {
                $t->base64_undangan = base64_encode(Storage::disk('public_sppd')->get($t->file_undangan));
            } else {
                $t->base64_undangan = '-';
            }
            $t->tigapuluh = in_array($t->id, $tigaPuluhIds) ? TigaPuluh::where('id_tujuan', $t->id)->first() : null;
            if ($t->tigapuluh === null) {
                $data['tigap']=false;
            }else{
                $data['tigap']=true;
            }
            // rincian + termin uang muka (persentase, hotel per malam, termin 4 hari); tiket aktual dari proses UmumSppd
            $rincian = $this->rincianUangMuka($t, (float) ($tiketPerTujuan[$t->id] ?? 0));
            foreach ($rincian['termin'] as &$tm) {
                $tm['id_tujuan'] = (int) $t->id;
                $b = $buktiTermin->get($t->id . ':' . $tm['no']);
                $aktual = $b && (int) ($b->ditunda_aktual ?? 0) === 1;
                $tm['bukti'] = $b && !$aktual ? ['file' => $b->file, 'tgl_upload' => (string) $b->created_at, 'oleh' => Employe::where('employe_id', $b->diproses_oleh)->value('first_name')] : null;
                $tm['aktual'] = $aktual ? ['catatan' => $b->catatan, 'tgl' => (string) $b->created_at, 'oleh' => Employe::where('employe_id', $b->diproses_oleh)->value('first_name')] : null;
            }
            unset($tm);
            $t->rincian_uang_muka = $rincian;
            $t->uang_muka = $rincian['total'];
            // perkiraan biaya per tujuan: komponen yang kosong (tarif master belum ada) dihitung 0, bukan membuat seluruh total NULL/0
            $t->total_biaya = round((float) $t->fix_um + (float) $t->fix_tl + (float) $t->fix_us + (float) $t->fix_tiket + (float) $t->fix_hotel + (float) $t->bbm, 2);
            $t->termin = array_map(fn ($x) => (object) $x, $rincian['termin']);
        }
        if ($data) {
            $data->uang_muka = round($tujuans->sum(fn ($t) => (float) $t->uang_muka), 2);
            $data->perkiraan_total = round($tujuans->sum(fn ($t) => (float) $t->total_biaya), 2);
            $data->tiket_vendor = round($tujuans->sum(fn ($t) => (float) ($t->rincian_uang_muka['tiket']['jumlah'] ?? 0)), 2);
        }
        $rill = Realisasi::where('id_sppd', $id)->first();
        if (!is_null($rill)) {
            $rill->submitter_name = Employe::where('employe_id', $rill->submitted_by)->first()->first_name;
        }

        $data['realisasi'] = $rill;
        $data['ekstend']=EkstendView::where('id_sppd', $id)->get();
        // jenis extend (tambah hari / tujuan baru), kategori, dan perlu tiket untuk ditampilkan pada lembar persetujuan
        if (EkstendProses::aktif() && $data['ekstend']->isNotEmpty()) {
            $tambahan = Ekstend::whereIn('id', $data['ekstend']->pluck('id'))->get()->keyBy('id');
            $kategori = DB::connection('mysql4')->table('categori_sppds')->pluck('categori_sppd', 'id');
            foreach ($data['ekstend'] as $x) {
                $e = $tambahan->get($x->id);
                $x->tipe = $e->tipe ?? 'perpanjang';
                $x->perlu_tiket = (int) ($e->perlu_tiket ?? 0);
                $x->moda = $e->moda ?? null;
                $x->kategori_nama = $e && $e->id_kategori ? ($kategori[$e->id_kategori] ?? null) : null;
                $x->nama_tujuan_baru = $e->detail_tujuan ?? null;
                $x->butuh_proses = (int) ($e->butuh_proses ?? 0);
                $x->bbm = (float) ($e->bbm ?? 0);
            }
        }
        // approval per extend (extend-1, extend-2, ...): nomor, status, langkah penyetuju, dan apakah masih bisa dibatalkan
        if (EkstendProses::aktif() && EkstendProses::adaNoUrut() && $data['ekstend']->isNotEmpty()) {
            $tambahan = Ekstend::whereIn('id', $data['ekstend']->pluck('id'))->get()->keyBy('id');
            $langkahDoc = VerifStep::where('id_doc', $data->nomor_dokumen)->where('type', 'like', 'extend%')->orderBy('step')->get()->groupBy('type');
            $nama = Employe::whereIn('employe_id', $langkahDoc->flatten()->pluck('id_employe')->unique()->all())->pluck('first_name', 'employe_id');
            foreach ($data['ekstend'] as $x) {
                $e = $tambahan->get($x->id);
                $x->no_urut = $e && $e->no_urut ? (int) $e->no_urut : null;
                $x->tipe_approval = $x->no_urut ? 'extend-' . $x->no_urut : 'extend';
                $x->dibatalkan_at = $e->dibatalkan_at ?? null;
                $x->alasan_batal = $e->alasan_batal ?? null;
                $x->disetujui_at = $e->disetujui_at ?? null;
                $langkah = $langkahDoc->get($x->tipe_approval, collect());
                $x->langkah = $langkah->map(fn ($l) => [
                    'step' => (int) $l->step,
                    'id_employe' => $l->id_employe,
                    'nama' => $nama[$l->id_employe] ?? $l->id_employe,
                    'jabatan' => $l->id_current_position,
                    'status' => $l->status,
                    'ket' => $l->ket,
                    'waktu' => (string) $l->updated_at,
                ])->values();
                $x->status_approval = $x->dibatalkan_at ? 'dibatalkan'
                    : ($langkah->contains(fn ($l) => in_array($l->status, ['rejected', 'reject'], true)) ? 'ditolak'
                    : ($e && $e->disetujui_at ? 'disetujui' : 'menunggu'));
                $x->bisa_batal = !$x->dibatalkan_at && $e && $this->alasanTidakBisaBatalEkstend($e) === null;
            }
        }
        // extend yang sudah disetujui: tiket dan uang muka (termin + bukti) terpisah dari tujuan SPPD awal
        $data['ekstend_items'] = EkstendProses::disetujui((int) $id)->map(fn ($e) => [
            'id' => (int) $e->id,
            'no_urut' => $e->no_urut ? (int) $e->no_urut : null,
            'tipe' => $e->tipe,
            'judul' => EkstendProses::judul($e),
            'nama_tujuan' => $e->tipe === 'tujuan_baru' ? $e->detail_tujuan : TujuanSppd::where('id', $e->id_tujuan)->value('detail_tujuan'),
            'start' => (string) $e->start,
            'end' => (string) $e->end,
            'alasan' => $e->alasan,
            'perlu_tiket' => (int) $e->perlu_tiket,
            'by_umum' => (int) $e->by_umum,
            'uangmuka' => (int) $e->uangmuka,
            'sumber_tiket' => $e->sumber_tiket,
            'rincian_uang_muka' => EkstendProses::rincian($e),
            'tiket' => ProsesTiket::withoutGlobalScopes()->where('id_ekstend', $e->id)->orderBy('arah', 'DESC')->get(),
        ])->values();

        $data['tujuan_sppd'] = $tujuans;
        $data['current_type']=UnderReview::where('id', $id)->first()->current_type ?? null;

        $data['check_doc'] = CheklistDoc::where('id_sppd', $id)->get();

        // Sum in the same query so realisasi_biaya (which rebuilds hitungan_biaya) is read only once
        $rb = !$this->hasRealisasi($id) ? collect() : RealisasiBiaya::where('id_sppd', $id)
            ->select('*', DB::raw('sum(jumlah_rill) over () as sum_rill_biaya, sum(rill_tiket) over () as sum_rill_tiket'))->get();
        if ($data) {
            $data->rill_biaya = $rb->first()->sum_rill_biaya ?? null;
            $data->rill_tiket = $rb->first()->sum_rill_tiket ?? null;
        }
        foreach ($rb as $r) {
            unset($r->sum_rill_biaya, $r->sum_rill_tiket);
        }
        $data['realisasi_biaya'] = $rb;
        $data['approval'] = ListApproval::where('id_sppd', $id)->orderBy('step', 'ASC')->get();
        $data['log_pengajuan'] = LogPengajuan::where('id_sppd', $id)->orderBy('created_at', 'ASC')->get();
        $data['proses'] = Proses::where('id_sppd', $id)->get();
        $data['proses_tiket'] = ProsesTiket::where('id_sppd', $id)->orderBy('id_tujuan')->orderBy('arah', 'DESC')->get();
        $data['proses_termin'] = $this->adaTabelTermin() ? ProsesTermin::where('id_sppd', $id)->orderBy('id_tujuan')->orderBy('termin')->get() : [];
        return new PostResource(true, 'success', $data);
    }


    function updatePengajuan(Request $request, $id)
    {
        $sppd = Sppd::find($id);
        if ($tolak = $this->tolakBilaVoid($sppd)) {
            return $tolak;
        }

        $sppd->nama = $request->name;
        $sppd->jabatan = $request->jabatann;
        $sppd->golongan_rate = $request->rate;
        $sppd->narasi_st = $request->narasi;
        $tujuans = $request->tujuan_sppd;
        if ($sppd->touch()) {
            TujuanSppd::where('id_sppd', $id)->delete();
            for ($i = 0; $i < count($tujuans); $i++) {
                if ($tujuans[$i]['file_undangan'] !== '-') {
                    $file = base64_decode(str_replace('data:application/pdf;base64,', '', $tujuans[$i]['file_undangan']), true);
                    $fileName = 'undangan/' . date('Y') . '/' . date('m') . '/' . date('d') . '/' . $sppd->id . '/undangan-' . ($i + 1) . '.pdf';
                    if (Storage::disk('public_sppd')->put($fileName, $file)) {
                        $file_undangan = $fileName;
                    }
                } else {
                    $file_undangan = '-';
                }
                TujuanSppd::insert([
                    'id_sppd' => $sppd->id,
                    'jenis_sppd' => $tujuans[$i]['jenis_sppd'],
                    'dasar' => $tujuans[$i]['dasar_sppd'],
                    'file_undangan' => $file_undangan,
                    'klasifikasi' => $tujuans[$i]['klasifikasi'],
                    'sumber' => $tujuans[$i]['sumber_biaya'] || null,
                    'rkap' => $tujuans[$i]['renbis'],
                    'p_tiket' => $tujuans[$i]['p_tiket'],
                    'p_um' => $tujuans[$i]['p_um'],
                    'p_tl' => $tujuans[$i]['p_tl'],
                    'p_us' => $tujuans[$i]['p_us'],
                    'p_hotel' => $tujuans[$i]['p_hotel'],
                    'kategori' => $tujuans[$i]['kategori_sppd'],
                    'detail_tujuan' => $tujuans[$i]['detail_tujuan'],
                    'tugas' => $tujuans[$i]['tugas_sppd'],
                    'waktu_berangkat' => date('Y-m-d H:i:s', strtotime($tujuans[$i]['waktu_berangkat'])),
                    'waktu_kembali' =>  date('Y-m-d H:i:s', strtotime($tujuans[$i]['waktu_kembali'])),
                    'moda' => $tujuans[$i]['moda'],
                    'bbm' => $tujuans[$i]['ubbm'],
                    'share_with' => $tujuans[$i]['shareWith'],
                    ...$this->kolomPerluTiket($tujuans[$i]),
                ]);
            }
            // isi SPPD berubah: PDF bertanda tangan yang tersimpan sebelumnya sudah tidak berlaku
            SuratTteService::forgetStoredPdfs($sppd->nomor_dokumen);

            return new PostResource(true, 'success', []);
        }
    }

    function persetujuan(Request $request, $id_doc)
    {
        if ($tolak = $this->tolakBilaVoid(Sppd::where('nomor_dokumen', $id_doc)->first())) {
            return $tolak;
        }
        if ($request->type == 'check_document') {

            if (CheklistDokRealisasi::where('id_sppd', Sppd::where('nomor_dokumen', $id_doc)->first()->id)->delete()) {
                $c = $request->check_doc;
                for ($i = 0; $i < count($c); $i++) {
                    CheklistDokRealisasi::insert([
                        'id_sppd' => Sppd::where('nomor_dokumen', $id_doc)->first()->id,
                        'id_doc' => $c[$i]['id_doc'],
                        'status' => $c[$i]['status'],
                    ]);
                }
            }
        }
        $verif = VerifStep::where('id_employe', Employe::employeId())->where('id_doc',  $id_doc)->where('status', NULL)->orderBy('step')->limit(1)->first();
        $verif->status = $request->status;
        $verif->ket = $request->catatan_persetujuan;
        $contentHash = null;
        if ($request->status === 'approve') {
            $contentHash = SuratTteService::sppdContentHash($id_doc);
            $verif->signature = $this->signApproval($id_doc, $verif, $contentHash);
            // hash rantai ikut disimpan dalam satu UPDATE (UPDATE kedua memicu trigger log lagi, log persetujuan jadi ganda)
            if (($hash = SuratTteService::chainUntukSimpan($verif, $contentHash)) !== null) {
                $verif->chain_sha256 = $hash;
            }
        }
        if ($verif->save()) {
            if ($request->status === 'approve') {
                // paraf/TTE baru: PDF jenis ini dibuat ulang dari isi terbaru saat dibuka
                SuratTteService::forgetStoredPdfs($id_doc, $verif->type);
            }
            if ($verif->type === 'extend' || str_starts_with((string) $verif->type, 'extend-')) {
                $this->selesaikanEkstend((string) $id_doc, (string) $verif->type);
            }
            return new PostResource(true, 'success', []);
        }
    }

    // Same RSA signature the adm letters use; the TTE code is derived from it
    private function signApproval($id_doc, $verif, $contentHash)
    {
        try {
            $employeId = Employe::employeId();
            CryptoService::ensureKeys($employeId);

            return SignatureService::signDocument($employeId, [
                'id_doc' => $id_doc,
                'type' => $verif->type,
                'step' => $verif->step,
                'employe_id' => $employeId,
                'ket' => $verif->ket,
                'content_sha256' => $contentHash,
                'signed_at' => now()->toIso8601String(),
            ]);
        } catch (\Throwable $e) {
            report($e);
            \Log::warning('Approval signature could not be created', ['id_doc' => $id_doc, 'error' => $e->getMessage(), 'at' => $e->getFile() . ':' . $e->getLine()]);

            return null;
        }
    }

    function signPdf($id, Request $request)
    {
        $request->validate(['pdf' => 'required_unless:cached,1,true|nullable|string|max:30000000', 'type' => 'required|string|max:50', 'kind' => 'nullable|string|max:50', 'cached' => 'nullable']);

        $sppd = Sppd::find($id);
        $tte = $sppd ? SuratTteService::find($sppd->nomor_dokumen, $request->type) : null;
        if (!$tte) {
            $approved = $sppd ? VerifStep::where('id_doc', $sppd->nomor_dokumen)->where('type', $request->type)->where('status', 'approve')->get() : collect();
            $reason = !$sppd ? 'sppd_not_found'
                : ($approved->isEmpty() ? 'step_not_approved' : ($approved->contains(fn ($s) => $s->signature) ? 'lookup_failed' : 'no_signature'));
            \Log::info('SPPD sign-pdf skipped', ['sppd_id' => $id, 'type' => $request->type, 'reason' => $reason]);

            $steps = $sppd ? VerifStep::where('id_doc', $sppd->nomor_dokumen)->orderBy('step')->get(['step', 'type', 'status', 'signature'])
                ->map(fn ($s) => "{$s->type}#{$s->step}:" . ($s->status ?? 'null') . ':' . ($s->signature ? 'sig' : 'nosig'))->implode(', ') : '';

            return response()->json(['success' => false, 'reason' => $reason, 'message' => 'Dokumen ini tidak memiliki TTE (' . $reason . '). Langkah: ' . $steps], 404);
        }

        // Probe: serve the already signed file so the browser can skip generating the PDF
        if ($request->boolean('cached')) {
            $stored = SuratTteService::storedPdf($sppd->nomor_dokumen, $request->type, (string) $request->kind);

            return new PostResource(true, 'success', ['pdf' => $stored ? base64_encode($stored) : null]);
        }

        $bytes = base64_decode(preg_replace('#^data:application/pdf;base64,#', '', $request->pdf), true);
        if ($bytes === false || strncmp($bytes, '%PDF-', 5) !== 0) {
            return response()->json(['success' => false, 'message' => 'File PDF tidak valid'], 422);
        }

        try {
            $signed = SuratTteService::issueSignedPdf($bytes, $sppd->nomor_dokumen, $tte, $request->type, 'SPPD', (string) $request->kind);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['success' => false, 'message' => 'PDF tidak dapat ditandatangani: ' . mb_substr($e->getMessage(), 0, 200)], 500);
        }

        return new PostResource(true, 'success', ['pdf' => base64_encode($signed)]);
    }

    function getRomawi($bln)
    {
        switch ($bln) {
            case 1:
                return "I";
            case 2:
                return "II";
            case 3:
                return "III";
            case 4:
                return "IV";
            case 5:
                return "V";
            case 6:
                return "VI";
            case 7:
                return "VII";
            case 8:
                return "VIII";
            case 9:
                return "IX";
            case 10:
                return "X";
            case 11:
                return "XI";
            case 12:
                return "XII";
            default:
                return "Invalid";
        }
    }


    // realisasi
    function submitRealisasi(Request $request)
    {
        if ($tolak = $this->tolakBilaVoid(Sppd::find($request->id_sppd))) {
            return $tolak;
        }
        if (ListSppdRingan::where('id', $request->id_sppd)->where('realisasi_status', 'verified')->exists()) {
            return response()->json(['success' => false, 'message' => 'Realisasi SPPD ini sudah diverifikasi, tidak bisa diubah lagi.'], 422);
        }

        if (Realisasi::where('id_sppd', $request->id_sppd)->exists()) {
            Realisasi::where('id_sppd', $request->id_sppd)->delete();
        }
        $file = base64_decode(str_replace('data:application/pdf;base64,', '', $request->doc_file), true);
        $fileName = 'realisasi/' . $request->id_sppd . '.pdf';
        if (Storage::disk('public_sppd')->put($fileName, $file)) {
            $realisasi = new Realisasi();
            $realisasi->id_sppd = $request->id_sppd;
            $realisasi->submitted_by = Employe::employeId();
            $realisasi->doc_file = $fileName;
            if ($realisasi->save()) {
                $tujuan_realisasi = $request->tujuan_realisasi;
                for ($i = 0; $i < count($tujuan_realisasi); $i++) {
                    if (RealisasiTujuan::where('id_tujuan', $tujuan_realisasi[$i]['id'])->exists()) {
                        RealisasiTujuan::where('id_tujuan', $tujuan_realisasi[$i]['id'])->delete();
                    }
                    RealisasiTujuan::insert([
                        'id_tujuan' => $tujuan_realisasi[$i]['id'],
                        'rill_tiket'  => $tujuan_realisasi[$i]['rill_tiket'],
                        'rill_hotel' => $tujuan_realisasi[$i]['rill_hotel'],
                        'rill_t_lokal'=> $tujuan_realisasi[$i]['rill_t_lokal'],
                        'rill_t_umum'=> $tujuan_realisasi[$i]['rill_t_umum'],
                        'rill_bbm' => $tujuan_realisasi[$i]['rill_bbm'],
                        'rill_wb' =>  date('Y-m-d H:i:s', strtotime($tujuan_realisasi[$i]['rill_wb'])),
                        'rill_wt' => date('Y-m-d H:i:s', strtotime($tujuan_realisasi[$i]['rill_wt'])),
                    ]);
                }
                return new PostResource(true, 'success', []);
            }
        } else {
            return new PostResource(false, 'error', []);
        }
    }


    function done(Request $request)
    {


        $proses = new Proses();

        if ($request->who === 'umum') {
            return $this->doneUmum($request);
        }

        if ($request->file != '') {
            $file = base64_decode(str_replace('data:application/pdf;base64,', '', $request->file));
            $fileName = 'proses/' .  $request->id_sppd . '/proses-' . $request->who . '.pdf';
            if (Storage::disk('public_sppd')->put($fileName, $file)) {
                $proses->file = $fileName;
            }
        }


        $proses->id_sppd = $request->id_sppd;
        $proses->process_by = Employe::employeId();
        $proses->as = $request->who;
        if ($proses->save()) {
            $this->laporProsesKeuangan((int) $request->id_sppd, (string) $request->who, !empty($proses->file));

            return new PostResource(true, 'success', []);
        }
    }

    /** proses divisi umum untuk extend yang disetujui (tiket per extend); tidak menyentuh proses SPPD awal */
    private function doneUmumEkstend(Request $request)
    {
        $request->validate([
            'id_sppd' => 'required|integer',
            'sumber_tiket' => 'required|in:umum,mandiri',
            'tikets' => 'required_if:sumber_tiket,umum|array',
            'tikets.*.id_ekstend' => 'required|integer',
            'tikets.*.arah' => 'required|in:pergi,pulang',
            'tikets.*.keterangan' => 'nullable|string|max:255',
            'tikets.*.file' => 'required|string',
            'tikets.*.harga' => 'required|numeric|min:0',
        ]);
        $idSppd = (int) $request->id_sppd;
        $items = EkstendProses::disetujui($idSppd)->where('perlu_tiket', 1)->where('by_umum', 0)->keyBy('id');
        if ($items->isEmpty()) {
            return response()->json(['message' => 'Tidak ada extend yang menunggu proses tiket pada SPPD ini'], 422);
        }
        $tikets = $request->sumber_tiket === 'umum' ? ($request->tikets ?? []) : [];
        if ($request->sumber_tiket === 'umum' && count($tikets) === 0) {
            return response()->json(['message' => 'Minimal satu tiket harus diisi'], 422);
        }
        foreach ($tikets as $t) {
            if (!$items->has((int) $t['id_ekstend'])) {
                return response()->json(['message' => 'Extend tidak valid atau sudah diproses'], 422);
            }
        }

        $disk = Storage::disk('public_sppd');
        $saved = [];
        $me = Employe::employeId();
        try {
            DB::connection('mysql4')->transaction(function () use ($request, $idSppd, $tikets, $items, $disk, $me, &$saved) {
                foreach ($tikets as $i => $t) {
                    $raw = base64_decode(preg_replace('#^data:application/pdf;base64,#', '', $t['file']), true);
                    if ($raw === false || $raw === '') {
                        throw new \RuntimeException('File tiket tidak valid');
                    }
                    $name = 'proses/' . $idSppd . '/tiket-ek' . (int) $t['id_ekstend'] . '-' . ($i + 1) . '-' . $t['arah'] . '-' . uniqid() . '.pdf';
                    if (!$disk->put($name, $raw)) {
                        throw new \RuntimeException('Gagal menyimpan file tiket');
                    }
                    $saved[] = $name;

                    $row = new ProsesTiket();
                    $row->id_sppd = $idSppd;
                    $row->id_tujuan = (int) $items[(int) $t['id_ekstend']]->id_tujuan;
                    $row->id_ekstend = (int) $t['id_ekstend'];
                    $row->arah = $t['arah'];
                    $row->keterangan = $t['keterangan'] ?? null;
                    $row->file = $name;
                    $row->harga = $t['harga'];
                    $row->uploaded_by = $me;
                    $row->save();
                }
                Ekstend::whereIn('id', $items->keys()->all())->update(['by_umum' => 1, 'sumber_tiket' => $request->sumber_tiket]);
            });
        } catch (\Throwable $e) {
            foreach ($saved as $f) {
                $disk->delete($f);
            }

            return response()->json(['message' => $e->getMessage()], 422);
        }

        $this->laporProsesUmumEkstend($idSppd, Ekstend::whereIn('id', $items->keys()->all())->get(), false);

        return new PostResource(true, 'success', []);
    }

    /** log (satu baris) + notifikasi untuk proses/ubah tiket divisi umum pada extend */
    private function laporProsesUmumEkstend(int $idSppd, $items, bool $ubah): void
    {
        try {
            $tiket = ProsesTiket::withoutGlobalScopes()->whereIn('id_ekstend', $items->pluck('id')->all())->orderBy('id')->get();
            $judul = $items->mapWithKeys(fn ($e) => [(int) $e->id => EkstendProses::judul($e)]);
            $ringkas = $tiket->count() > 0 ? $tiket->count() . ' tiket, total ' . $this->rp($tiket->sum(fn ($t) => (float) $t->harga)) : 'tanpa tiket dari divisi umum';
            $rinci = $tiket->map(fn ($t) => ucfirst((string) $t->arah) . ' ' . ($judul[(int) $t->id_ekstend] ?? '-') . ': ' . $this->rp($t->harga) . (trim((string) $t->keterangan) !== '' ? ' (' . $t->keterangan . ')' : ''))->implode('; ');
            $sumber = $items->first()->sumber_tiket ?? null;
            $baris = ($ubah ? 'Tiket extend diubah divisi umum' : 'Extend diproses divisi umum') . '. Sumber tiket: ' . $this->labelSumberTiket($sumber) . '; ' . $ringkas . ($rinci !== '' ? ' (' . $rinci . ')' : '');
            $this->catatLogSppd($idSppd, [$this->potongLog($baris)]);
            $sppd = Sppd::find($idSppd);
            if ($sppd) {
                Peristiwa::sppdProsesUmum($sppd, $ubah ? 'tiket_diubah' : 'diproses', 'Extend: ' . $items->map(fn ($e) => EkstendProses::judul($e))->implode('; ') . '. ' . $ringkas . '.', Employe::employeId(), true);
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Laporan proses divisi umum (extend) gagal', ['id_sppd' => $idSppd, 'error' => $e->getMessage()]);
        }
    }

    // Proses UmumSppd: pilih sumber tiket; bila diupload UmumSppd, tiket pergi/pulang per tujuan (bisa lebih dari satu)
    private function doneUmum(Request $request)
    {
        if ($request->boolean('ekstend')) {
            return $this->doneUmumEkstend($request);
        }
        $request->validate([
            'id_sppd' => 'required|integer',
            'sumber_tiket' => 'required|in:umum,mandiri',
            'tikets' => 'required_if:sumber_tiket,umum|array',
            'tikets.*.id_tujuan' => 'required|integer',
            'tikets.*.arah' => 'required|in:pergi,pulang',
            'tikets.*.keterangan' => 'nullable|string|max:255',
            'tikets.*.file' => 'required|string',
            'tikets.*.harga' => 'required|numeric|min:0',
        ]);

        $idSppd = (int) $request->id_sppd;
        if (Proses::where('id_sppd', $idSppd)->where('as', 'umum')->exists()) {
            return response()->json(['message' => 'SPPD ini sudah diproses UmumSppd. Gunakan "Ubah Tiket" pada tabel Sudah Diproses.'], 422);
        }
        $tikets = $request->sumber_tiket === 'umum' ? ($request->tikets ?? []) : [];
        if ($request->sumber_tiket === 'umum' && count($tikets) === 0) {
            return response()->json(['message' => 'Minimal satu tiket harus diisi'], 422);
        }

        $tujuanValid = TujuanSppd::where('id_sppd', $idSppd)->pluck('id')->all();
        foreach ($tikets as $t) {
            if (!in_array((int) $t['id_tujuan'], $tujuanValid, true)) {
                return response()->json(['message' => 'Tujuan SPPD tidak valid'], 422);
            }
        }

        $disk = Storage::disk('public_sppd');
        $saved = [];
        try {
            DB::connection('mysql4')->transaction(function () use ($request, $idSppd, $tikets, $disk, &$saved) {
                $proses = new Proses();
                $proses->id_sppd = $idSppd;
                $proses->process_by = Employe::employeId();
                $proses->as = 'umum';
                $proses->sumber_tiket = $request->sumber_tiket;
                $proses->save();

                foreach ($tikets as $i => $t) {
                    $raw = base64_decode(preg_replace('#^data:application/pdf;base64,#', '', $t['file']), true);
                    if ($raw === false || $raw === '') {
                        throw new \RuntimeException('File tiket tidak valid');
                    }
                    $name = 'proses/' . $idSppd . '/tiket-' . $proses->id . '-' . ($i + 1) . '-' . $t['arah'] . '.pdf';
                    if (!$disk->put($name, $raw)) {
                        throw new \RuntimeException('Gagal menyimpan file tiket');
                    }
                    $saved[] = $name;

                    $row = new ProsesTiket();
                    $row->id_sppd = $idSppd;
                    $row->id_tujuan = (int) $t['id_tujuan'];
                    $row->arah = $t['arah'];
                    $row->keterangan = $t['keterangan'] ?? null;
                    $row->file = $name;
                    $row->harga = $t['harga'];
                    $row->uploaded_by = Employe::employeId();
                    $row->save();
                }
            });
        } catch (\Throwable $e) {
            foreach ($saved as $f) {
                $disk->delete($f);
            }
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $this->laporProsesUmum($idSppd, null, [], false);

        return new PostResource(true, 'success', []);
    }

    // ---------- Log dan notifikasi proses SPPD (UmumSppd dan KeuanganSppd) ----------

    private function labelSumberTiket(?string $k): string
    {
        return [
            'umum' => 'Diupload oleh divisi umum',
            'mandiri' => 'Dipesan mandiri (diupload saat realisasi)',
            // nilai lama (sebelum SPPD tanpa tiket tidak lagi diproses UmumSppd), hanya untuk menampilkan data lama
            'tidak_ada' => 'Tidak ada tiket',
            'pelaksana' => 'Diupload pelaksana SPPD (saat realisasi)',
            'admin' => 'Diupload admin pengaju (saat realisasi)',
        ][$k] ?? '-';
    }

    private function rp($v): string
    {
        return 'Rp ' . number_format((float) $v, 0, ',', '.');
    }

    /** satu baris tiket: "Pergi ke <tujuan>: Rp ... (keterangan)" */
    private function barisTiket($t, $tujuan): string
    {
        $t = (object) $t;
        $ket = trim((string) ($t->keterangan ?? ''));

        return ucfirst((string) $t->arah) . ' ke ' . ($tujuan[$t->id_tujuan] ?? '-') . ': ' . $this->rp($t->harga) . ($ket !== '' ? ' (' . $ket . ')' : '');
    }

    /** catat ke log SPPD (tombol Logs); kegagalan mencatat tidak boleh membatalkan proses */
    private function catatLogSppd(int $idSppd, array $baris): void
    {
        try {
            $dok = Sppd::where('id', $idSppd)->value('nomor_dokumen');
            if (!$dok) {
                return;
            }
            $me = Employe::employeId();
            foreach ($baris as $b) {
                SuratLogService::catat((string) $dok, $me, $b);
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Log proses SPPD gagal', ['id_sppd' => $idSppd, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Ganti baris log yang baru ditulis trigger `log` (tabel proses) dengan versi berisi rincian, supaya satu proses = satu baris log.
     * Bila baris trigger tidak ditemukan, catat sebagai baris baru.
     */
    private function gantiAtauCatatLog(int $idSppd, string $aktivitasTrigger, string $aktivitas): void
    {
        try {
            $dok = Sppd::where('id', $idSppd)->value('nomor_dokumen');
            if (!$dok) {
                return;
            }
            $logs = DB::connection('adm')->table('ptpemaco_esign.logs');
            $idLog = (clone $logs)->where('id_document', $dok)->where('activity', $aktivitasTrigger)
                ->where('created_at', '>=', now()->subMinutes(2))->orderByDesc('id')->value('id');
            if ($idLog) {
                (clone $logs)->where('id', $idLog)->update(['activity' => mb_substr($aktivitas, 0, 255), 'updated_at' => now()]);
            } else {
                SuratLogService::catat((string) $dok, Employe::employeId(), $aktivitas);
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Log proses SPPD gagal', ['id_sppd' => $idSppd, 'error' => $e->getMessage()]);
        }
    }

    /** potong teks log agar muat di kolom activity (255 karakter) dengan tanda "…" bila terpotong */
    private function potongLog(string $teks, int $maks = 255): string
    {
        return mb_strlen($teks) <= $maks ? $teks : mb_substr($teks, 0, $maks - 1) . '…';
    }

    /**
     * Log + notifikasi setelah UmumSppd memproses SPPD ($ubah false) atau mengubah tiketnya ($ubah true; $snap = tiket sebelum diubah).
     * Log dibuat ringkas: satu baris untuk satu kali proses/ubah.
     */
    private function laporProsesUmum(int $idSppd, ?string $sumberLama, array $snap, bool $ubah): void
    {
        try {
            $baru = ProsesTiket::where('id_sppd', $idSppd)->orderBy('id')->get();
            $sumberBaru = Proses::where('id_sppd', $idSppd)->where('as', 'umum')->value('sumber_tiket');
            $tujuan = TujuanSppd::where('id_sppd', $idSppd)->pluck('detail_tujuan', 'id');
            $total = $baru->sum(fn ($t) => (float) $t->harga);
            $ringkasTiket = $baru->count() > 0 ? $baru->count() . ' tiket, total ' . $this->rp($total) : 'tanpa tiket dari UmumSppd';

            if (!$ubah) {
                $baris = 'Diproses UmumSppd. Sumber tiket: ' . $this->labelSumberTiket($sumberBaru) . '; ' . $ringkasTiket
                    . ($baru->count() > 0 ? ' (' . $baru->map(fn ($t) => $this->barisTiket($t, $tujuan))->implode('; ') . ')' : '');
                $kejadian = 'diproses';
            } else {
                $bagian = [];
                if ($sumberLama !== $sumberBaru) {
                    $bagian[] = 'sumber tiket ' . $this->labelSumberTiket($sumberLama) . ' menjadi ' . $this->labelSumberTiket($sumberBaru);
                }
                $idBaru = $baru->pluck('id')->all();
                foreach ($snap as $id => $lama) {
                    if (!in_array((int) $id, $idBaru, true)) {
                        $bagian[] = 'hapus ' . $this->barisTiket($lama, $tujuan);
                    }
                }
                foreach ($baru as $t) {
                    if (!array_key_exists($t->id, $snap)) {
                        $bagian[] = 'tambah ' . $this->barisTiket($t, $tujuan);
                        continue;
                    }
                    $lama = (object) $snap[$t->id];
                    $beda = [];
                    if ((int) $lama->id_tujuan !== (int) $t->id_tujuan) {
                        $beda[] = 'tujuan ' . ($tujuan[$lama->id_tujuan] ?? '-') . ' menjadi ' . ($tujuan[$t->id_tujuan] ?? '-');
                    }
                    if ($lama->arah !== $t->arah) {
                        $beda[] = 'arah ' . $lama->arah . ' menjadi ' . $t->arah;
                    }
                    if ((float) $lama->harga !== (float) $t->harga) {
                        $beda[] = 'harga ' . $this->rp($lama->harga) . ' menjadi ' . $this->rp($t->harga);
                    }
                    if (trim((string) $lama->keterangan) !== trim((string) $t->keterangan)) {
                        $beda[] = 'keterangan "' . $lama->keterangan . '" menjadi "' . $t->keterangan . '"';
                    }
                    if ($lama->file !== $t->file) {
                        $beda[] = 'file diganti';
                    }
                    if ($beda) {
                        $bagian[] = 'ubah ' . ucfirst((string) $t->arah) . ' ke ' . ($tujuan[$t->id_tujuan] ?? '-') . ' (' . implode(', ', $beda) . ')';
                    }
                }
                $baris = 'Tiket diubah UmumSppd: ' . ($bagian ? implode('; ', $bagian) : 'tanpa perubahan') . '. Total sekarang: ' . $ringkasTiket;
                $kejadian = 'tiket_diubah';
            }

            if ($ubah) {
                $this->catatLogSppd($idSppd, [$this->potongLog($baris)]);
            } else {
                $this->gantiAtauCatatLog($idSppd, 'SPPD sudah di proses oleh bagian umum', $this->potongLog($baris));
            }

            $sppd = Sppd::find($idSppd);
            if ($sppd) {
                Peristiwa::sppdProsesUmum($sppd, $kejadian, 'Sumber tiket: ' . $this->labelSumberTiket($sumberBaru) . '; ' . $ringkasTiket . '.', Employe::employeId());
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Laporan proses UmumSppd gagal', ['id_sppd' => $idSppd, 'error' => $e->getMessage()]);
        }
    }

    /** notifikasi setelah KeuanganSppd memproses uang muka atau pembayaran realisasi (log-nya sudah ditulis trigger `log` pada tabel proses) */
    private function laporProsesKeuangan(int $idSppd, string $who, bool $adaLampiran): void
    {
        try {
            $sppd = Sppd::find($idSppd);
            if ($sppd) {
                Peristiwa::sppdProsesKeuangan($sppd, $who === 'uangmuka' ? 'uangmuka' : 'realisasi', $adaLampiran, Employe::employeId());
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Laporan proses KeuanganSppd gagal', ['id_sppd' => $idSppd, 'error' => $e->getMessage()]);
        }
    }

    /**
     * UmumSppd mengubah hasil prosesnya (sumber tiket dan daftar tiket). Tetap boleh setelah KeuanganSppd memprosesnya,
     * karena tiket pulang sering baru diupload belakangan.
     * Tiket lama dikenali lewat `id`; tanpa `file` berarti file lama dipertahankan, tiket baru wajib berfile.
     */
    function ubahUmum(Request $request)
    {
        $request->validate([
            'id_sppd' => 'required|integer',
            'sumber_tiket' => 'required|in:umum,mandiri',
            'tikets' => 'required_if:sumber_tiket,umum|array',
            'tikets.*.id' => 'nullable|integer',
            'tikets.*.id_tujuan' => 'required|integer',
            'tikets.*.arah' => 'required|in:pergi,pulang',
            'tikets.*.keterangan' => 'nullable|string|max:255',
            'tikets.*.file' => 'nullable|string',
            'tikets.*.harga' => 'required|numeric|min:0',
        ]);

        $idSppd = (int) $request->id_sppd;
        $sppd = ListSppdRingan::find($idSppd);
        if (!$sppd || $sppd->current_status !== 'signed') {
            return response()->json(['message' => 'SPPD tidak ditemukan atau belum disetujui'], 422);
        }
        if (!Proses::where('id_sppd', $idSppd)->where('as', 'umum')->exists()) {
            return response()->json(['message' => 'SPPD ini belum diproses UmumSppd'], 422);
        }

        $tikets = $request->sumber_tiket === 'umum' ? array_values($request->tikets ?? []) : [];
        if ($request->sumber_tiket === 'umum' && count($tikets) === 0) {
            return response()->json(['message' => 'Minimal satu tiket harus diisi'], 422);
        }

        $tujuanValid = TujuanSppd::where('id_sppd', $idSppd)->pluck('id')->all();
        $lama = ProsesTiket::where('id_sppd', $idSppd)->get()->keyBy('id');
        $sumberLama = Proses::where('id_sppd', $idSppd)->where('as', 'umum')->value('sumber_tiket');
        $snap = $lama->map(fn ($r) => $r->only(['arah', 'id_tujuan', 'keterangan', 'harga', 'file']))->all(); // kondisi sebelum diubah, untuk log
        $dipakai = [];
        foreach ($tikets as $t) {
            if (!in_array((int) $t['id_tujuan'], $tujuanValid, true)) {
                return response()->json(['message' => 'Tujuan SPPD tidak valid'], 422);
            }
            if (!empty($t['id'])) {
                if (!$lama->has((int) $t['id']) || isset($dipakai[(int) $t['id']])) {
                    return response()->json(['message' => 'Tiket yang diubah tidak ditemukan'], 422);
                }
                $dipakai[(int) $t['id']] = true;
            } elseif (empty($t['file'])) {
                return response()->json(['message' => 'File tiket wajib diunggah untuk tiket baru'], 422);
            }
        }

        $disk = Storage::disk('public_sppd');
        $baru = [];      // file yang baru ditulis (dibuang bila gagal)
        $usang = [];     // file lama yang dibuang setelah berhasil
        $simpanPdf = function (string $dataUrl, string $arah) use ($disk, $idSppd, &$baru) {
            $raw = base64_decode(preg_replace('#^data:application/pdf;base64,#', '', $dataUrl), true);
            if ($raw === false || $raw === '') {
                throw new \RuntimeException('File tiket tidak valid');
            }
            $nama = 'proses/' . $idSppd . '/tiket-' . uniqid() . '-' . $arah . '.pdf';
            if (!$disk->put($nama, $raw)) {
                throw new \RuntimeException('Gagal menyimpan file tiket');
            }
            $baru[] = $nama;

            return $nama;
        };

        try {
            DB::connection('mysql4')->transaction(function () use ($request, $idSppd, $tikets, $lama, $dipakai, $simpanPdf, &$usang) {
                Proses::where('id_sppd', $idSppd)->where('as', 'umum')->update(['sumber_tiket' => $request->sumber_tiket, 'updated_at' => now()]);

                // tiket yang tidak dikirim lagi (atau semuanya, bila sumber bukan "umum") dihapus
                foreach ($lama as $id => $row) {
                    if (!isset($dipakai[$id])) {
                        $usang[] = $row->file;
                        $row->delete();
                    }
                }

                foreach ($tikets as $t) {
                    if (!empty($t['id'])) {
                        $row = $lama->get((int) $t['id']);
                        if (!empty($t['file'])) {
                            $usang[] = $row->file;
                            $row->file = $simpanPdf($t['file'], $t['arah']);
                        }
                    } else {
                        $row = new ProsesTiket();
                        $row->id_sppd = $idSppd;
                        $row->file = $simpanPdf($t['file'], $t['arah']);
                        $row->uploaded_by = Employe::employeId();
                    }
                    $row->id_tujuan = (int) $t['id_tujuan'];
                    $row->arah = $t['arah'];
                    $row->keterangan = $t['keterangan'] ?? null;
                    $row->harga = $t['harga'];
                    $row->save();
                }
            });
        } catch (\Throwable $e) {
            foreach ($baru as $f) {
                $disk->delete($f);
            }

            return response()->json(['message' => $e->getMessage()], 422);
        }

        foreach ($usang as $f) {
            $disk->delete($f);
        }

        $this->laporProsesUmum($idSppd, $sumberLama, $snap, true);

        return new PostResource(true, 'success', []);
    }

    // ---------- Bukti transfer uang muka per termin (KeuanganSppd) ----------

    /** tabel proses_termin (db/2026-11-18/proses_termin.sql) sudah ada? */
    /**
     * Uang muka yang sebagian terminnya sudah dibuktikan tetap "dalam proses" di daftar KeuanganSppd;
     * diberi termin_terbukti / termin_total agar tampil penanda (mis. 1/2 termin). Selesai bila semua termin terbukti.
     */
    private function tandaiProgresTermin($data)
    {
        if (!$this->adaTabelTermin()) {
            return $data;
        }
        // extend: progres termin dihitung per extend (bukan dari termin SPPD awal)
        $data = $data->map(function ($d) {
            if (!empty($d['mode_ekstend']) && ($d['type_proses'] ?? null) === 'uangmuka') {
                $sudah = 0;
                $total = 0;
                foreach (Ekstend::whereIn('id', $d['ekstend_ids'] ?? [])->get() as $e) {
                    $r = EkstendProses::rincian($e);
                    $total += count($r['termin'] ?? []);
                    $sudah += ProsesTermin::withoutGlobalScopes()->where('id_ekstend', $e->id)->count();
                }
                if ($sudah > 0) {
                    $d['termin_terbukti'] = $sudah;
                    $d['termin_total'] = $total;
                }
            }

            return $d;
        });
        $ids = $data->filter(fn ($d) => ($d['type_proses'] ?? null) === 'uangmuka' && empty($d['mode_ekstend']))->pluck('id')->all();
        if (!$ids) {
            return $data;
        }
        $terbukti = ProsesTermin::whereIn('id_sppd', $ids)->selectRaw('id_sppd, count(*) as jumlah')->groupBy('id_sppd')->pluck('jumlah', 'id_sppd');
        if ($terbukti->isEmpty()) {
            return $data;
        }
        $tujuan = HitunganBiaya::whereIn('id_sppd', $terbukti->keys()->all())->get()->groupBy('id_sppd');
        $tiket = ProsesTiket::whereIn('id_sppd', $terbukti->keys()->all())->selectRaw('id_tujuan, sum(harga) as total')->groupBy('id_tujuan')->pluck('total', 'id_tujuan');

        return $data->map(function ($d) use ($terbukti, $tujuan, $tiket) {
            $n = $terbukti->get($d['id'] ?? 0);
            if (($d['type_proses'] ?? null) === 'uangmuka' && empty($d['mode_ekstend']) && $n) {
                $total = $tujuan->get($d['id'], collect())->sum(fn ($t) => count($this->rincianUangMuka($t, (float) ($tiket[$t->id] ?? 0))['termin']));
                $d['termin_terbukti'] = (int) $n;
                $d['termin_total'] = (int) $total;
            }

            return $d;
        });
    }

    /** SPPD dibuat sejak config sppd.proses_paralel_mulai: divisi umum dan divisi keuangan bekerja bersamaan */
    private function prosesParalel($baris): bool
    {
        return $baris && $baris->created_at && strtotime((string) $baris->created_at) >= strtotime((string) config('sppd.proses_paralel_mulai'));
    }

    private function adaTabelTermin(): bool
    {
        return \Illuminate\Support\Facades\Cache::remember('proses_termin_ada', 60, fn () => Schema::connection('mysql4')->hasTable('proses_termin'));
    }

    /** KeuanganSppd sudah mulai memproses (uang muka, pembayaran, atau minimal satu bukti termin): ditandai di daftar UmumSppd (tiket tetap bisa diubah) */
    private function keuanganSudahMemproses(int $idSppd, $baris): bool
    {
        if ((int) $baris->uangmuka === 1 || (int) $baris->by_keuangan === 1) {
            return true;
        }

        return $this->adaTabelTermin() && ProsesTermin::where('id_sppd', $idSppd)->exists();
    }

    /** @return array{0:string,1:string} [ekstensi, isi biner] dari data URL pdf/jpg/png; melempar RuntimeException bila tidak sah */
    private function bacaBukti(string $dataUrl): array
    {
        if (!preg_match('#^data:(application/pdf|image/jpeg|image/png);base64,#', $dataUrl, $m)) {
            throw new \RuntimeException('Bukti transfer harus berupa file PDF, JPG, atau PNG');
        }
        $isi = base64_decode(substr($dataUrl, strlen($m[0])), true);
        if ($isi === false || $isi === '') {
            throw new \RuntimeException('File bukti transfer tidak valid');
        }
        if (strlen($isi) > 5 * 1024 * 1024) {
            throw new \RuntimeException('Ukuran bukti transfer maksimal 5 MB');
        }
        $ok = ['application/pdf' => [str_starts_with($isi, '%PDF-'), 'pdf'], 'image/jpeg' => [str_starts_with($isi, "\xFF\xD8\xFF"), 'jpg'], 'image/png' => [str_starts_with($isi, "\x89PNG"), 'png']][$m[1]];
        if (!$ok[0]) {
            throw new \RuntimeException('Isi file bukti transfer tidak sesuai jenis filenya');
        }

        return [$ok[1], $isi];
    }

    /**
     * KeuanganSppd mengupload bukti transfer uang muka per termin.
     * Uang muka dianggap selesai diproses (baris proses 'uangmuka') setelah semua termin semua tujuan punya bukti.
     * Payload: id_sppd, bukti[] = {id_tujuan, termin, file (data URL pdf/jpg/png)}
     */
    /** bukti transfer termin uang muka untuk extend (bukti[].id_ekstend + termin); extend selesai bila semua terminnya terbukti */
    private function uploadBuktiTerminEkstend(Request $request)
    {
        $request->validate([
            'id_sppd' => 'required|integer',
            'bukti' => 'required|array|min:1',
            'bukti.*.id_ekstend' => 'required|integer',
            'bukti.*.termin' => 'required|integer|min:1',
            'bukti.*.file' => 'required|string',
        ]);
        if (!EkstendProses::aktif() || !$this->adaTabelTermin()) {
            return response()->json(['message' => 'Tabel extend belum dibuat (jalankan db/2026-11-18/ekstend_proses.sql)'], 422);
        }
        $idSppd = (int) $request->id_sppd;
        $baris = ListSppdRingan::find($idSppd);
        if (!$baris || $baris->current_status !== 'signed') {
            return response()->json(['message' => 'SPPD tidak ditemukan atau belum disetujui'], 422);
        }
        if (array_key_exists($idSppd, SppdVoid::peta())) {
            return response()->json(['message' => 'SPPD ini sudah di-void'], 422);
        }
        $items = EkstendProses::disetujui($idSppd)->where('uangmuka', 0)->keyBy('id');

        $jadwal = [];
        $rincian = [];
        foreach ($items as $e) {
            $r = EkstendProses::rincian($e);
            $rincian[$e->id] = $r;
            foreach ($r['termin'] ?? [] as $x) {
                $jadwal[$e->id . ':' . $x['no']] = ['id_ekstend' => (int) $e->id, 'id_tujuan' => (int) $e->id_tujuan, 'no' => (int) $x['no'], 'tgl' => $x['tgl_bayar'], 'jumlah' => (float) $x['jumlah']];
            }
        }
        $sudah = ProsesTermin::withoutGlobalScopes()->where('id_ekstend', '<>', 0)->where('id_sppd', $idSppd)->get()->keyBy(fn ($r) => $r->id_ekstend . ':' . $r->termin);

        $pilih = [];
        foreach ($request->bukti as $b) {
            $kunci = (int) $b['id_ekstend'] . ':' . (int) $b['termin'];
            if (!isset($jadwal[$kunci])) {
                return response()->json(['message' => 'Termin tidak ditemukan pada extend yang menunggu uang muka'], 422);
            }
            if ($sudah->has($kunci) || isset($pilih[$kunci])) {
                return response()->json(['message' => (($sudah->get($kunci)->ditunda_aktual ?? 0) ? 'Termin ' . $jadwal[$kunci]['no'] . ' sudah ditandai dibayar di aktual' : 'Bukti transfer termin ' . $jadwal[$kunci]['no'] . ' sudah ada')], 409);
            }
            try {
                $pilih[$kunci] = $this->bacaBukti((string) $b['file']);
            } catch (\RuntimeException $e) {
                return response()->json(['message' => 'Termin ' . $jadwal[$kunci]['no'] . ': ' . $e->getMessage()], 422);
            }
        }

        $disk = Storage::disk('public_sppd');
        $baru = [];
        $me = Employe::employeId();
        try {
            DB::connection('mysql4')->transaction(function () use ($pilih, $jadwal, $idSppd, $disk, $me, &$baru) {
                foreach ($pilih as $kunci => [$ext, $isi]) {
                    $j = $jadwal[$kunci];
                    $nama = 'proses/' . $idSppd . '/termin-ek' . $j['id_ekstend'] . '-' . $j['no'] . '-' . uniqid() . '.' . $ext;
                    if (!$disk->put($nama, $isi)) {
                        throw new \RuntimeException('Gagal menyimpan bukti transfer');
                    }
                    $baru[] = $nama;
                    $row = new ProsesTermin();
                    $row->id_sppd = $idSppd;
                    $row->id_tujuan = $j['id_tujuan'];
                    $row->id_ekstend = $j['id_ekstend'];
                    $row->termin = $j['no'];
                    $row->tgl_bayar = $j['tgl'];
                    $row->jumlah = $j['jumlah'];
                    $row->file = $nama;
                    $row->diproses_oleh = $me;
                    $row->save();
                }
            });
        } catch (\Throwable $e) {
            foreach ($baru as $f) {
                $disk->delete($f);
            }

            return response()->json(['message' => $e->getMessage()], 422);
        }

        // extend yang seluruh terminnya sudah dibuktikan => uang mukanya selesai
        $selesaiIds = [];
        foreach ($rincian as $idEk => $r) {
            if (ProsesTermin::withoutGlobalScopes()->where('id_ekstend', $idEk)->count() >= count($r['termin'] ?? [])) {
                $selesaiIds[] = $idEk;
            }
        }
        if ($selesaiIds) {
            Ekstend::whereIn('id', $selesaiIds)->update(['uangmuka' => 1]);
        }
        $semuaSelesai = EkstendProses::disetujui($idSppd)->where('uangmuka', 0)->isEmpty();
        $this->laporBuktiTerminEkstend($idSppd, $pilih ? array_map(fn ($k) => $jadwal[$k], array_keys($pilih)) : [], $items, $semuaSelesai);

        return new PostResource(true, 'success', ['selesai' => $semuaSelesai]);
    }

    private function laporBuktiTerminEkstend(int $idSppd, array $termin, $items, bool $selesai): void
    {
        try {
            $judul = $items->mapWithKeys(fn ($e) => [(int) $e->id => EkstendProses::judul($e)]);
            $daftar = collect($termin)->map(fn ($j) => 'termin ' . $j['no'] . ' ' . ($judul[$j['id_ekstend']] ?? '') . ' ' . $this->rp($j['jumlah']) . ' (jatuh tempo ' . date('d-m-Y', strtotime($j['tgl'])) . ')')->implode('; ');
            $sudahTermin = ProsesTermin::withoutGlobalScopes()->whereIn('id_ekstend', $items->pluck('id')->all())->count();
            $totalTermin = (int) $items->sum(fn ($e) => count((EkstendProses::rincian($e)['termin'] ?? [])));
            $baris = 'Bukti transfer uang muka extend diupload divisi keuangan: ' . $daftar . '. Progres ' . $sudahTermin . '/' . $totalTermin . ' termin extend' . ($selesai ? '; uang muka extend selesai diproses' : '');
            $this->catatLogSppd($idSppd, [$this->potongLog($baris)]);
            $sppd = Sppd::find($idSppd);
            if ($sppd) {
                Peristiwa::sppdBuktiTermin($sppd, 'Extend: ' . $daftar, $sudahTermin, $totalTermin, $selesai, Employe::employeId());
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Laporan bukti termin extend gagal', ['id_sppd' => $idSppd, 'error' => $e->getMessage()]);
        }
    }

    function uploadBuktiTermin(Request $request)
    {
        if ($request->boolean('ekstend')) {
            return $this->uploadBuktiTerminEkstend($request);
        }
        $request->validate([
            'id_sppd' => 'required|integer',
            'bukti' => 'required|array|min:1',
            'bukti.*.id_tujuan' => 'required|integer',
            'bukti.*.termin' => 'required|integer|min:1',
            'bukti.*.file' => 'required|string',
        ]);
        if (!$this->adaTabelTermin()) {
            return response()->json(['message' => 'Tabel bukti termin belum dibuat (jalankan db/2026-11-18/proses_termin.sql)'], 422);
        }

        $idSppd = (int) $request->id_sppd;
        $baris = ListSppdRingan::find($idSppd);
        if (!$baris || $baris->current_status !== 'signed') {
            return response()->json(['message' => 'SPPD tidak ditemukan atau belum disetujui'], 422);
        }
        if (array_key_exists($idSppd, SppdVoid::peta())) {
            return response()->json(['message' => 'SPPD ini sudah di-void'], 422);
        }
        if ((int) $baris->uangmuka === 1) {
            return response()->json(['message' => 'Uang muka SPPD ini sudah selesai diproses'], 409);
        }
        // SPPD lama: tetap menunggu divisi umum; SPPD baru (sejak proses_paralel_mulai) boleh bersamaan
        if (!$this->prosesParalel($baris) && (int) $baris->by_umum === 0 && $this->perluTiketQuery(ListSppdRingan::where('id', $idSppd), true)->exists()) {
            return response()->json(['message' => 'SPPD ini belum diproses UmumSppd'], 409);
        }

        // jadwal termin menurut perhitungan uang muka
        $jadwal = [];
        $namaTujuan = [];
        foreach (HitunganBiaya::where('id_sppd', $idSppd)->get() as $t) {
            $namaTujuan[$t->id] = $t->detail_tujuan;
            foreach (UangMuka::rincian($t)['termin'] as $x) {
                $jadwal[$t->id . ':' . $x['no']] = ['id_tujuan' => (int) $t->id, 'no' => (int) $x['no'], 'tgl' => $x['tgl_bayar'], 'jumlah' => (float) $x['jumlah']];
            }
        }
        $sudah = ProsesTermin::where('id_sppd', $idSppd)->get()->keyBy(fn ($r) => $r->id_tujuan . ':' . $r->termin);

        $pilih = [];
        foreach ($request->bukti as $b) {
            $kunci = (int) $b['id_tujuan'] . ':' . (int) $b['termin'];
            if (!isset($jadwal[$kunci])) {
                return response()->json(['message' => 'Termin tidak ditemukan pada perhitungan uang muka SPPD ini'], 422);
            }
            if ($sudah->has($kunci) || isset($pilih[$kunci])) {
                return response()->json(['message' => (($sudah->get($kunci)->ditunda_aktual ?? 0) ? 'Termin ' . $jadwal[$kunci]['no'] . ' sudah ditandai dibayar di aktual' : 'Bukti transfer termin ' . $jadwal[$kunci]['no'] . ' sudah ada')], 409);
            }
            try {
                $pilih[$kunci] = $this->bacaBukti((string) $b['file']);
            } catch (\RuntimeException $e) {
                return response()->json(['message' => 'Termin ' . $jadwal[$kunci]['no'] . ': ' . $e->getMessage()], 422);
            }
        }

        $disk = Storage::disk('public_sppd');
        $baru = [];
        $me = Employe::employeId();
        try {
            DB::connection('mysql4')->transaction(function () use ($pilih, $jadwal, $idSppd, $disk, $me, &$baru) {
                foreach ($pilih as $kunci => [$ext, $isi]) {
                    $j = $jadwal[$kunci];
                    $nama = 'proses/' . $idSppd . '/termin-' . $j['id_tujuan'] . '-' . $j['no'] . '-' . uniqid() . '.' . $ext;
                    if (!$disk->put($nama, $isi)) {
                        throw new \RuntimeException('Gagal menyimpan bukti transfer');
                    }
                    $baru[] = $nama;
                    $row = new ProsesTermin();
                    $row->id_sppd = $idSppd;
                    $row->id_tujuan = $j['id_tujuan'];
                    $row->termin = $j['no'];
                    $row->tgl_bayar = $j['tgl'];
                    $row->jumlah = $j['jumlah'];
                    $row->file = $nama;
                    $row->diproses_oleh = $me;
                    $row->save();
                }
            });
        } catch (\Throwable $e) {
            foreach ($baru as $f) {
                $disk->delete($f);
            }

            return response()->json(['message' => $e->getMessage()], 422);
        }

        // semua termin sudah dibuktikan => uang muka selesai diproses
        $selesai = ProsesTermin::where('id_sppd', $idSppd)->count() >= count($jadwal);
        if ($selesai && !Proses::where('id_sppd', $idSppd)->where('as', 'uangmuka')->exists()) {
            $p = new Proses();
            $p->id_sppd = $idSppd;
            $p->process_by = $me;
            $p->as = 'uangmuka'; // trigger log menulis satu baris, diganti di bawah dengan rincian
            $p->save();
        }
        $this->laporBuktiTermin($idSppd, array_map(fn ($k) => $jadwal[$k], array_keys($pilih)), count($jadwal), $namaTujuan, $selesai);

        return new PostResource(true, 'success', ['selesai' => $selesai]);
    }

    /** kolom proses_termin.ditunda_aktual (db/2026-11-20/uangmuka_dibayar_aktual.sql) tersedia */
    private function adaKolomAktual(): bool
    {
        if (\Illuminate\Support\Facades\Cache::get('proses_termin_has_ditunda_aktual') === true) {
            return true;
        }
        $ada = $this->adaTabelTermin() && Schema::connection('mysql4')->hasColumn('proses_termin', 'ditunda_aktual');
        if ($ada) {
            \Illuminate\Support\Facades\Cache::put('proses_termin_has_ditunda_aktual', true, 300);
        }

        return $ada;
    }

    /**
     * KeuanganSppd menandai SATU ATAU BEBERAPA TERMIN uang muka tidak dibayarkan di muka (mis. SPPD mendadak): termin itu dicatat tanpa bukti
     * transfer dan dibayarkan saat realisasi (aktual). Termin lain tetap bisa dibayar dan dibuktikan seperti biasa; uang muka selesai bila
     * seluruh termin sudah berbukti atau ditandai. termin[]: {id_tujuan, termin} (SPPD awal) atau {id_ekstend, termin} (ekstend = true).
     */
    function terminDiAktual(Request $request)
    {
        $request->validate([
            'id_sppd' => 'required|integer',
            'termin' => 'required|array|min:1',
            'termin.*.termin' => 'required|integer|min:1',
            'termin.*.id_tujuan' => 'nullable|integer',
            'termin.*.id_ekstend' => 'nullable|integer',
            'alasan' => 'nullable|string|max:255',
        ]);
        if (!$this->adaKolomAktual()) {
            return response()->json(['message' => 'Fitur ini belum aktif di database: jalankan db/2026-11-20/uangmuka_dibayar_aktual.sql terlebih dulu.'], 422);
        }
        $idSppd = (int) $request->id_sppd;
        $baris = ListSppdRingan::find($idSppd);
        if (!$baris || $baris->current_status !== 'signed') {
            return response()->json(['message' => 'SPPD tidak ditemukan atau belum disetujui'], 422);
        }
        if (array_key_exists($idSppd, SppdVoid::peta())) {
            return response()->json(['message' => 'SPPD ini sudah di-void'], 422);
        }
        $ekstend = $request->boolean('ekstend');
        $alasan = trim((string) $request->alasan);
        $me = Employe::employeId();

        // jadwal termin (kunci "<tujuan|extend>:<no>") dan termin yang sudah punya bukti / penanda
        $jadwal = [];
        $rincian = [];
        $namaTujuan = [];
        if ($ekstend) {
            if (!EkstendProses::aktif()) {
                return response()->json(['message' => 'Tabel extend belum dibuat (jalankan db/2026-11-18/ekstend_proses.sql)'], 422);
            }
            $items = EkstendProses::disetujui($idSppd)->where('uangmuka', 0)->keyBy('id');
            foreach ($items as $e) {
                $r = EkstendProses::rincian($e);
                $rincian[$e->id] = $r;
                $namaTujuan[$e->id] = EkstendProses::judul($e);
                foreach ($r['termin'] ?? [] as $x) {
                    $jadwal[$e->id . ':' . $x['no']] = ['id_ekstend' => (int) $e->id, 'id_tujuan' => (int) $e->id_tujuan, 'no' => (int) $x['no'], 'tgl' => $x['tgl_bayar'], 'jumlah' => (float) $x['jumlah']];
                }
            }
            $sudah = ProsesTermin::withoutGlobalScopes()->where('id_ekstend', '<>', 0)->where('id_sppd', $idSppd)->get()->keyBy(fn ($r) => $r->id_ekstend . ':' . $r->termin);
        } else {
            if ((int) $baris->uangmuka === 1) {
                return response()->json(['message' => 'Uang muka SPPD ini sudah selesai diproses'], 409);
            }
            foreach (HitunganBiaya::where('id_sppd', $idSppd)->get() as $t) {
                $namaTujuan[$t->id] = $t->detail_tujuan;
                foreach (UangMuka::rincian($t)['termin'] as $x) {
                    $jadwal[$t->id . ':' . $x['no']] = ['id_ekstend' => 0, 'id_tujuan' => (int) $t->id, 'no' => (int) $x['no'], 'tgl' => $x['tgl_bayar'], 'jumlah' => (float) $x['jumlah']];
                }
            }
            $sudah = ProsesTermin::where('id_sppd', $idSppd)->get()->keyBy(fn ($r) => $r->id_tujuan . ':' . $r->termin);
        }

        $pilih = [];
        foreach ($request->termin as $x) {
            $kunci = ($ekstend ? (int) ($x['id_ekstend'] ?? 0) : (int) ($x['id_tujuan'] ?? 0)) . ':' . (int) $x['termin'];
            if (!isset($jadwal[$kunci])) {
                return response()->json(['message' => 'Termin tidak ditemukan pada perhitungan uang muka SPPD ini'], 422);
            }
            if ($sudah->has($kunci) || isset($pilih[$kunci])) {
                return response()->json(['message' => 'Termin ' . $jadwal[$kunci]['no'] . ' sudah punya bukti transfer atau sudah ditandai'], 409);
            }
            $pilih[$kunci] = $jadwal[$kunci];
        }

        DB::connection('mysql4')->transaction(function () use ($pilih, $idSppd, $me, $alasan) {
            foreach ($pilih as $j) {
                $row = new ProsesTermin();
                $row->id_sppd = $idSppd;
                $row->id_tujuan = $j['id_tujuan'];
                $row->id_ekstend = $j['id_ekstend'];
                $row->termin = $j['no'];
                $row->tgl_bayar = $j['tgl'];
                $row->jumlah = $j['jumlah'];
                $row->file = ''; // tanpa bukti transfer: dibayar saat realisasi (aktual)
                $row->ditunda_aktual = 1;
                $row->catatan = $alasan !== '' ? $alasan : null;
                $row->diproses_oleh = $me;
                $row->save();
            }
        });

        // uang muka selesai bila seluruh termin berbukti atau ditandai
        $banyakTujuan = count($namaTujuan) > 1;
        $daftar = collect($pilih)->map(fn ($j) => 'termin ' . $j['no'] . ($banyakTujuan ? ' ' . ($namaTujuan[$ekstend ? $j['id_ekstend'] : $j['id_tujuan']] ?? '') : '') . ' ' . $this->rp($j['jumlah']))->implode('; ');
        if ($ekstend) {
            $selesaiIds = [];
            foreach ($rincian as $idEk => $r) {
                if (ProsesTermin::withoutGlobalScopes()->where('id_ekstend', $idEk)->count() >= count($r['termin'] ?? [])) {
                    $selesaiIds[] = $idEk;
                }
            }
            if ($selesaiIds) {
                Ekstend::whereIn('id', $selesaiIds)->update(['uangmuka' => 1]);
            }
            $selesai = EkstendProses::disetujui($idSppd)->where('uangmuka', 0)->isEmpty();
            $this->catatLogSppd($idSppd, [$this->potongLog('Uang muka extend tidak dibayarkan di muka (dibayar saat realisasi/aktual) oleh KeuanganSppd: ' . $daftar . ($alasan !== '' ? '. Alasan: ' . $alasan : '') . ($selesai ? '; uang muka extend selesai diproses' : ''))]);
        } else {
            $selesai = ProsesTermin::where('id_sppd', $idSppd)->count() >= count($jadwal);
            if ($selesai && !Proses::where('id_sppd', $idSppd)->where('as', 'uangmuka')->exists()) {
                $p = new Proses();
                $p->id_sppd = $idSppd;
                $p->process_by = $me;
                $p->as = 'uangmuka'; // trigger log menulis satu baris, diganti di bawah dengan rincian
                $p->save();
            }
            $baris = 'Uang muka tidak dibayarkan di muka (dibayar saat realisasi/aktual) oleh KeuanganSppd: ' . $daftar . ($alasan !== '' ? '. Alasan: ' . $alasan : '');
            if ($selesai) {
                $this->gantiAtauCatatLog($idSppd, 'Uang muka SPPD sudah di proses oleh bagian keuangan', $this->potongLog($baris . '; uang muka selesai diproses'));
            } else {
                $this->catatLogSppd($idSppd, [$this->potongLog($baris)]);
            }
        }
        $sppd = Sppd::find($idSppd);
        if ($sppd) {
            Peristiwa::sppdUangMukaAktual($sppd, $daftar, $alasan, $me, $ekstend);
        }

        return new PostResource(true, 'success', ['selesai' => $selesai]);
    }

    /** log (satu baris per upload) + notifikasi ke pelaksana dan admin pengaju */
    private function laporBuktiTermin(int $idSppd, array $termin, int $totalTermin, array $namaTujuan, bool $selesai): void
    {
        try {
            $banyakTujuan = count($namaTujuan) > 1;
            $daftar = collect($termin)->map(fn ($j) => 'termin ' . $j['no'] . ($banyakTujuan ? ' ' . ($namaTujuan[$j['id_tujuan']] ?? '') : '') . ' ' . $this->rp($j['jumlah']) . ' (jatuh tempo ' . date('d-m-Y', strtotime($j['tgl'])) . ')')->implode('; ');
            $sudahTermin = ProsesTermin::where('id_sppd', $idSppd)->count();
            $baris = 'Bukti transfer uang muka diupload KeuanganSppd: ' . $daftar . '. Progres ' . $sudahTermin . '/' . $totalTermin . ' termin' . ($selesai ? '; uang muka selesai diproses' : '');
            if ($selesai) {
                $this->gantiAtauCatatLog($idSppd, 'Uang muka SPPD sudah di proses oleh bagian keuangan', $this->potongLog($baris));
            } else {
                $this->catatLogSppd($idSppd, [$this->potongLog($baris)]);
            }
            $sppd = Sppd::find($idSppd);
            if ($sppd) {
                Peristiwa::sppdBuktiTermin($sppd, $daftar, $sudahTermin, $totalTermin, $selesai, Employe::employeId());
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Laporan bukti termin gagal', ['id_sppd' => $idSppd, 'error' => $e->getMessage()]);
        }
    }

    function getNomorSppd(Request $request)
    {
        $wb = new DateTime($request->wb);
        $data = ListSppdRingan::whereDate('mulai_tugas', '>=', $wb)->get();
        foreach ($data as $d) {
            $date = new DateTime($d->mulai_tugas);
            $d->berangkat = $date->format('Y-m-d');
            $d->value = $d->id;
            $d->label = $d->nomor_sppd . ' a/n ' . $d->nama;
        }
        return new PostResource(true, 'list data sppd', $data);
    }

    function dataDashboard()
    {
        // The dasboard, groupByKar and realisasi_rkap views each rebuild list_sppd and realisasi_biaya
        // (~1.5s apiece), so read both once and aggregate here the same way the views do
        $rill = DB::connection('mysql4')->table('realisasi_biaya')
            ->selectRaw('id_sppd, id_rkap, sum(jumlah_rill) as jumlah')->groupBy('id_sppd', 'id_rkap')->get();
        $rillSppd = $rill->groupBy('id_sppd')->map(fn ($r) => $r->whereNotNull('jumlah')->isEmpty() ? null : $r->sum('jumlah'));
        $rillRkap = $rill->groupBy('id_rkap')->map(fn ($r) => $r->sum('jumlah'));

        $sppds = ListSppdRingan::get(['id', 'employe_id', 'nama', 'jabatan', 'mulai_tugas', 'selesai_tugas', 'current_status', 'realisasi_status']);
        $verified = $sppds->where('realisasi_status', 'verified')->values();
        // MySQL groups and compares these case-insensitively
        $ci = fn ($v) => mb_strtolower((string) $v);
        $perKaryawan = fn ($rows, $key) => $rows->groupBy(fn ($s) => $ci($key($s)))->map(function ($g) use ($rillSppd, $ci) {
            $rills = $g->map(fn ($s) => $rillSppd->get($s->id))->filter(fn ($v) => $v !== null);

            return [
                'employe_id' => $g->first()->employe_id,
                'nama' => $g->first()->nama,
                'jabatan' => $g->reduce(fn ($max, $s) => $max === null || $ci($s->jabatan) >= $ci($max) ? $s->jabatan : $max),
                'last_berangkat' => $g->max('mulai_tugas'),
                'budget' => $rills->isEmpty() ? null : $rills->sum(),
                'qty' => $g->count(),
            ];
        })->sortByDesc('budget')->values();

        $year = (string) now()->year;
        $now = now()->format('Y-m-d H:i:s');
        $verifiedThisYear = $verified->filter(fn ($s) => substr((string) $s->mulai_tugas, 0, 4) === $year);
        $data = [];
        $data['dashboard'] = $perKaryawan($verifiedThisYear, fn ($s) => $s->employe_id . '|' . $s->nama)->first() ?? [];
        // Summary cards on the SPPD dashboard
        $data['dashboard']['pengajuan'] = $sppds->where('current_status', 'under_review')->count();
        $data['dashboard']['aktif'] = $sppds->filter(fn ($s) => $s->current_status === 'signed' && $s->mulai_tugas <= $now && $now <= $s->selesai_tugas)->count();
        $data['dashboard']['selesai'] = $verifiedThisYear->count();
        $data['dashboard']['total_aktual'] = $verifiedThisYear->sum(fn ($s) => $rillSppd->get($s->id) ?? 0);

        // tahun is a YEAR column (whereYear would wrap it in YEAR() and match nothing); show the latest year whose RKAP is filled in
        $tahunRkap = DB::connection('mysql4')->table('renbis')->where('rkap', '>', 0)->max('tahun');
        $rkap = DB::connection('mysql4')->table('renbis')->where('tahun', $tahunRkap)->get();
        $data['tahun_rkap'] = $tahunRkap;
        $data['label'] = $rkap->pluck('renbis')->all();
        $data['value'] = $rkap->map(fn ($r) => $r->rkap ? $rillRkap->get($r->id, 0) / $r->rkap * 100 : null)->all();

        $data['groupKar'] = $perKaryawan($verified, fn ($s) => $s->nama)->values();
        return new PostResource(true, 'dashboard', $data);
    }

    /**
     * Total hari extend yang masih aktif pada satu SPPD (menunggu atau disetujui; yang dibatalkan atau ditolak tidak dihitung).
     * Satu extend = (tanggal akhir - tanggal mulai + 1) hari. $kecualiIds: extend yang sedang diganti pengajuan baru.
     */
    private function hariEkstendTerpakai(int $idSppd, array $kecualiIds = []): int
    {
        if (!EkstendProses::aktif()) {
            return 0;
        }
        $q = Ekstend::where('butuh_proses', 1)->whereIn('id_tujuan', TujuanSppd::where('id_sppd', $idSppd)->pluck('id'));
        if (EkstendProses::adaNoUrut()) {
            $q->whereNull('dibatalkan_at');
        }
        if ($kecualiIds) {
            $q->whereNotIn('id', $kecualiIds);
        }

        return (int) $q->get(['start', 'end'])->sum(fn ($e) => max(0, (int) floor((strtotime((string) $e->end) - strtotime((string) $e->start)) / 86400) + 1));
    }

    /**
     * Data untuk dokumen keuangan lembar proses (voucher pengeluaran kas dan tabel standar biaya): rekening pelaksana serta
     * nama penandatangan dari sistem (pencetak = pengguna yang mengunduh, Manajer Keuangan, Direktur Umum dan Keuangan).
     * Tanda tangan sendiri dikosongkan (ditandatangani basah setelah dicetak).
     */
    function dataDokumenKeuangan($id)
    {
        $sppd = Sppd::find($id);
        if (!$sppd) {
            return response()->json(['success' => false, 'message' => 'SPPD tidak ditemukan.'], 404);
        }
        $db = DB::connection('mysql4');
        $orang = function (?string $employeId) use ($db) {
            if (!$employeId) {
                return null;
            }
            $r = $db->table('ptpemaco_apam.struktur_lengkap_oke')->where('employe_id', $employeId)->first(['employe_id', 'position_name']);
            $nama = Employe::where('employe_id', $employeId)->value('first_name');

            return $nama ? ['employe_id' => $employeId, 'nama' => trim($nama), 'jabatan' => $r->position_name ?? null] : null;
        };
        $pemegang = function (array $namaJabatan, ?int $posisiId = null) use ($db, $orang) {
            $q = $db->table('ptpemaco_apam.struktur_lengkap_oke')->whereNotNull('employe_id');
            $q->where(function ($w) use ($namaJabatan, $posisiId) {
                foreach ($namaJabatan as $n) {
                    $w->orWhereRaw('TRIM(position_name) = ?', [$n]);
                }
                if ($posisiId) {
                    $w->orWhere('position_id', $posisiId);
                }
            });

            return $orang($q->value('employe_id'));
        };

        $bank = $db->table('ptpemaco_hr_production.employee_bank_account')->where('employe_id', $sppd->employe_id)
            ->orderByDesc('active')->orderByDesc('id')->first(['nama_bank', 'nomor_rekening', 'nama_akun_bank']);

        return new PostResource(true, 'success', [
            'pelaksana' => ['nama' => $sppd->nama, 'employe_id' => $sppd->employe_id, 'bank' => $bank ? [
                'nama_bank' => trim((string) $bank->nama_bank),
                'nomor_rekening' => trim((string) $bank->nomor_rekening),
                'nama_akun_bank' => trim((string) $bank->nama_akun_bank),
            ] : null],
            'pencetak' => $orang(Employe::employeId()),
            // pilihan Petugas Transaksi di voucher: seluruh staf dan supervisor keuangan yang aktif
            'petugas' => $db->table('ptpemaco_apam.struktur_lengkap_oke as s')->join('ptpemaco_apam.employees as e', 'e.employe_id', '=', 's.employe_id')
                ->where(fn ($w) => $w->where('s.position_name', 'like', 'Staf Keuangan%')->orWhere('s.position_name', 'like', 'Supervisor Keuangan%'))
                ->orderBy('s.position_name')->orderBy('e.first_name')
                ->get(['s.employe_id', 'e.first_name', 's.position_name'])
                ->map(fn ($r) => ['employe_id' => $r->employe_id, 'nama' => trim($r->first_name), 'jabatan' => $r->position_name])->values(),
            'manajer_keuangan' => $pemegang(['Manajer Keuangan']),
            'dir_keuangan' => $pemegang(['Direktur Umum dan Keuangan'], 6),
        ]);
    }

    /** batas tanggal akhir extend: hari terakhir SPPD (tanggal kembali terakhir seluruh tujuan awal) + maks_hari_extend hari; null bila SPPD tak punya tujuan */
    private function batasAkhirEkstend(int $idSppd): ?string
    {
        $akhir = TujuanSppd::where('id_sppd', $idSppd)->max('waktu_kembali');

        return $akhir ? date('Y-m-d', strtotime(date('Y-m-d', strtotime((string) $akhir)) . ' +' . (int) config('sppd.maks_hari_extend', 5) . ' days')) : null;
    }

    /**
     * Rentang tanggal yang boleh dipilih untuk klaim Hotel 30% pada satu tujuan: dari tanggal berangkat tujuan sampai tanggal kembali,
     * diperpanjang sampai akhir extend yang SUDAH DISETUJUI (extend belum disetujui, ditolak, atau dibatalkan tidak dihitung).
     * @return array{mulai:string,akhir:string,ekstend_akhir:?string}
     */
    private function jendelaHotel30(TujuanSppd $t, ?string $akhirEkstendDisetujui = null): array
    {
        $mulai = date('Y-m-d', strtotime((string) $t->waktu_berangkat));
        $akhirSppd = date('Y-m-d', strtotime((string) $t->waktu_kembali));
        $akhirEks = $akhirEkstendDisetujui ? date('Y-m-d', strtotime($akhirEkstendDisetujui)) : null;

        return ['mulai' => $mulai, 'akhir' => $akhirEks && $akhirEks > $akhirSppd ? $akhirEks : $akhirSppd, 'ekstend_akhir' => $akhirEks];
    }

    /** akhir extend disetujui (aktif) per tujuan, untuk jendela Hotel 30% */
    private function akhirEkstendDisetujuiPerTujuan(array $idTujuan)
    {
        if (!$idTujuan || !EkstendProses::aktif()) {
            return collect();
        }
        $q = Ekstend::whereIn('id_tujuan', $idTujuan)->whereNotNull('disetujui_at')->where('butuh_proses', 1);
        if (EkstendProses::adaNoUrut()) {
            $q->whereNull('dibatalkan_at');
        }

        return $q->selectRaw('id_tujuan, max(`end`) as akhir')->groupBy('id_tujuan')->pluck('akhir', 'id_tujuan');
    }

    function getTujuanById($id)
    {
        $data = TujuanSppd::where('id_sppd', $id)->get();
        $tigaPuluh = TigaPuluh::whereIn('id_tujuan', $data->pluck('id'))->get()->unique('id_tujuan')->keyBy('id_tujuan');
        $ekstendAkhir = EkstendProses::aktif()
            ? Ekstend::whereIn('id_tujuan', $data->pluck('id'))->where('tipe', 'perpanjang')->whereNotNull('disetujui_at')->selectRaw('id_tujuan, max(`end`) as akhir, count(*) as banyak')->groupBy('id_tujuan')->get()->keyBy('id_tujuan')
            : collect();
        $maksHari = (int) config('sppd.maks_hari_extend', 5);
        $hariTerpakai = $this->hariEkstendTerpakai((int) $id);
        $batasAkhir = $this->batasAkhirEkstend((int) $id);
        // extend yang sudah diajukan (untuk ditandai di kalender form extend): menunggu, disetujui, dan extend lama; yang dibatalkan/ditolak tidak ditampilkan
        $terajukan = collect();
        if (EkstendProses::aktif()) {
            $adaNo = EkstendProses::adaNoUrut();
            $terajukan = Ekstend::whereIn('id_tujuan', $data->pluck('id'))
                ->when($adaNo, fn ($q) => $q->whereNull('dibatalkan_at'))->orderBy('id')->get()
                ->filter(fn ($e) => !($adaNo && $e->no_urut && (int) $e->butuh_proses === 0 && !$e->disetujui_at))
                ->map(fn ($e) => [
                    'id' => (int) $e->id,
                    'no_urut' => $adaNo && $e->no_urut ? (int) $e->no_urut : null,
                    'tipe' => $e->tipe ?: 'perpanjang',
                    'judul' => EkstendProses::judul($e),
                    'alasan' => $e->alasan,
                    'id_tujuan' => (int) $e->id_tujuan,
                    'perlu_tiket' => (int) $e->perlu_tiket,
                    'id_kategori' => $e->id_kategori ? (int) $e->id_kategori : null,
                    'nama_tujuan_baru' => $e->detail_tujuan,
                    'moda' => $e->moda,
                    'bbm' => (float) ($e->bbm ?? 0),
                    'start' => date('Y-m-d', strtotime((string) $e->start)),
                    'end' => date('Y-m-d', strtotime((string) $e->end)),
                    'status' => $e->disetujui_at ? 'disetujui' : ((int) $e->butuh_proses === 1 ? 'menunggu' : 'lama'),
                ])->values();
        }
        $akhirEksPerTujuan = $this->akhirEkstendDisetujuiPerTujuan($data->pluck('id')->all());
        foreach ($data as $t) {
            // rentang tanggal Hotel 30%: tanggal tujuan sampai akhir extend yang sudah disetujui
            $j = $this->jendelaHotel30($t, $akhirEksPerTujuan->get($t->id));
            $t->hotel30_mulai = $j['mulai'];
            $t->hotel30_akhir = $j['akhir'];
            $t->hotel30_ekstend_akhir = $j['ekstend_akhir'];
            $t->ekstend_terajukan = $terajukan;
            $t->ekstend_hari_maks = $maksHari;
            $t->ekstend_hari_terpakai = $hariTerpakai;
            $t->ekstend_batas_akhir = $batasAkhir;
            $t->tigapuluh = $tigaPuluh->get($t->id);
            // extend sebelumnya yang sudah disetujui (extend boleh berulang): akhir terakhir dan jumlahnya
            $t->ekstend_akhir = $ekstendAkhir->get($t->id)->akhir ?? null;
            $t->ekstend_banyak = (int) ($ekstendAkhir->get($t->id)->banyak ?? 0);
        }
        return new PostResource(true, 'success', $data);
    }

    /**
     * Pengajuan extend: tambah hari pada tujuan yang ada (tipe perpanjang) atau tujuan baru (tipe tujuan_baru).
     * Setelah semua persetujuan selesai, extend masuk proses divisi umum (bila perlu tiket) lalu divisi keuangan (uang muka).
     */
    function insertEkstend(Request $request)
    {
        $ekstendAktif = EkstendProses::aktif();
        $tipe = $ekstendAktif && $request->input('tipe') === 'tujuan_baru' ? 'tujuan_baru' : 'perpanjang';
        $aturan = [
            'alasan' => 'required|string',
            'start' => 'required|date',
            'end' => 'required|date|after_or_equal:start',
            'bbm' => 'nullable|numeric|min:0',
        ];
        if ($tipe === 'tujuan_baru') {
            $aturan += [
                'id_sppd' => 'required|integer',
                'detail_tujuan' => 'required|string|max:255',
                'id_kategori' => 'required|integer',
                'moda' => 'nullable|string|max:50',
            ];
        } else {
            $aturan['id_tujuan'] = 'required|integer';
        }
        $request->validate($aturan);

        $idTujuan = (int) $request->id_tujuan;
        if ($tipe === 'tujuan_baru') {
            // tujuan acuan: tujuan pertama SPPD (menentukan golongan/tarif, porsi biaya, dan SPPD-nya)
            $idTujuan = (int) TujuanSppd::where('id_sppd', (int) $request->id_sppd)->orderBy('id')->value('id');
            if (!$idTujuan) {
                return response()->json(['message' => 'SPPD tidak ditemukan'], 422);
            }
            if (!DB::connection('mysql4')->table('categori_sppds')->where('id', (int) $request->id_kategori)->exists()) {
                return response()->json(['message' => 'Kategori tujuan tidak valid'], 422);
            }
        }

        // extend boleh dilakukan beberapa kali: tanggal mulai perpanjangan berikutnya harus setelah extend sebelumnya yang sudah disetujui
        if ($ekstendAktif && $tipe === 'perpanjang') {
            $akhir = Ekstend::where('id_tujuan', $idTujuan)->where('tipe', 'perpanjang')->whereNotNull('disetujui_at')->max('end');
            if ($akhir && strtotime((string) $request->start) <= strtotime((string) $akhir)) {
                return response()->json(['message' => 'Tanggal mulai harus setelah extend sebelumnya (sampai ' . date('d-m-Y', strtotime((string) $akhir)) . ')'], 422);
            }
        }

        // ganti hanya pengajuan baru yang belum selesai disetujui untuk tujuan/jenis yang sama; data extend yang sudah ada sebelumnya
        // (butuh_proses = 0) dan yang sudah disetujui/diproses tidak disentuh. Tanpa kolom baru, perilaku lama tetap berlaku.
        $ganti = Ekstend::where('id_tujuan', $idTujuan)->when($ekstendAktif, fn ($q) => $q->where('butuh_proses', 1)->whereNull('disetujui_at')->where('tipe', $tipe));
        if ($tipe === 'tujuan_baru') {
            $ganti->where('detail_tujuan', $request->detail_tujuan);
        }

        // masih ada extend yang menunggu persetujuan pada SPPD ini: pengajuan ini MENGEDIT extend tersebut (tidak membuat extend baru)
        $sedangMenunggu = null;
        if ($ekstendAktif) {
            $sedangMenunggu = Ekstend::whereIn('id_tujuan', TujuanSppd::where('id_sppd', (int) TujuanSppd::where('id', $idTujuan)->value('id_sppd'))->pluck('id'))
                ->where('butuh_proses', 1)->whereNull('disetujui_at')
                ->when(EkstendProses::adaNoUrut(), fn ($q) => $q->whereNull('dibatalkan_at'))->orderBy('id')->first();
        }

        // batas total hari extend per SPPD; pengajuan menunggu yang akan diganti tidak dihitung
        if ($ekstendAktif) {
            // extend hanya untuk SPPD yang sudah di-approve (tanda tangan akhir selesai)
            if (!ListSppdRingan::where('id', (int) TujuanSppd::where('id', $idTujuan)->value('id_sppd'))->where('current_status', 'signed')->exists()) {
                return response()->json(['success' => false, 'message' => 'Extend hanya bisa diajukan untuk SPPD yang sudah di-approve.'], 422);
            }
            $maks = (int) config('sppd.maks_hari_extend', 5);
            $idSppdEx = (int) TujuanSppd::where('id', $idTujuan)->value('id_sppd');
            $terpakai = $this->hariEkstendTerpakai($idSppdEx, $sedangMenunggu ? [(int) $sedangMenunggu->id] : (clone $ganti)->pluck('id')->all());
            $hariBaru = (int) floor((strtotime((string) $request->end) - strtotime((string) $request->start)) / 86400) + 1;
            $batasAkhir = $this->batasAkhirEkstend($idSppdEx);
            $akhirSppd = TujuanSppd::where('id_sppd', $idSppdEx)->max('waktu_kembali');
            if ($akhirSppd && date('Y-m-d', strtotime((string) $request->start)) <= date('Y-m-d', strtotime((string) $akhirSppd))) {
                return response()->json(['success' => false, 'message' => 'Tanggal mulai extend harus setelah hari terakhir SPPD (' . date('d-m-Y', strtotime((string) $akhirSppd)) . ').'], 422);
            }
            if ($batasAkhir && date('Y-m-d', strtotime((string) $request->end)) > $batasAkhir) {
                return response()->json(['success' => false, 'message' => "Tanggal akhir extend maksimal {$maks} hari setelah hari terakhir SPPD, yaitu sampai " . date('d-m-Y', strtotime($batasAkhir)) . '.'], 422);
            }
            if ($terpakai + $hariBaru > $maks) {
                $sisa = max(0, $maks - $terpakai);

                return response()->json(['success' => false, 'message' => "Total extend maksimal {$maks} hari per SPPD. Sudah dipakai {$terpakai} hari (sisa {$sisa} hari), pengajuan ini {$hariBaru} hari. Batalkan extend sebelumnya bila ingin menambah."], 422);
            }
        }
        // pengajuan yang diganti: langkah approval-nya (extend-<no_urut>) ikut dihapus supaya tidak menggantung
        if (!$sedangMenunggu && $ekstendAktif && EkstendProses::adaNoUrut()) {
            $nomorDoc = Sppd::where('id', TujuanSppd::where('id', $idTujuan)->value('id_sppd'))->value('nomor_dokumen');
            $tipeLama = (clone $ganti)->whereNotNull('no_urut')->pluck('no_urut')->map(fn ($n) => 'extend-' . $n)->all();
            if ($nomorDoc && $tipeLama) {
                VerifStep::where('id_doc', $nomorDoc)->whereIn('type', $tipeLama)->whereNull('status')->delete();
            }
        }
        if ($sedangMenunggu) {
            return $this->ubahEkstendMenunggu($sedangMenunggu, $request, $tipe, $idTujuan);
        }
        $ganti->delete();

        $ekstend = new Ekstend();
        $ekstend->id_tujuan = $idTujuan;
        $ekstend->alasan = $request->alasan;
        $ekstend->start = $request->start;
        $ekstend->end = $request->end;
        $ekstend->submitted_by = Employe::employeId();
        if ($ekstendAktif) {
            $ekstend->tipe = $tipe;
            $ekstend->perlu_tiket = filter_var($request->input('perlu_tiket', false), FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
            $ekstend->butuh_proses = 1;
            if (EkstendProses::adaBbm()) {
                $ekstend->bbm = (float) $request->input('bbm', 0);
            }
            if ($tipe === 'tujuan_baru') {
                $ekstend->id_kategori = (int) $request->id_kategori;
                $ekstend->detail_tujuan = $request->detail_tujuan;
                $ekstend->moda = $request->moda ?: null;
            }
        }

        if ($ekstend->save()) {
            return new PostResource(true, 'success', []);
        } else {
            return new PostResource(false, 'error', []);
        }
    }

    /**
     * Mengedit extend yang masih menunggu persetujuan (nomor dan identitasnya tetap). Karena isinya berubah, approval diulang dari awal:
     * status, catatan dan tanda tangan langkahnya dikosongkan.
     */
    private function ubahEkstendMenunggu(Ekstend $e, Request $request, string $tipe, int $idTujuan)
    {
        $e->id_tujuan = $idTujuan;
        $e->alasan = $request->alasan;
        $e->start = $request->start;
        $e->end = $request->end;
        $e->submitted_by = Employe::employeId();
        $e->tipe = $tipe;
        $e->perlu_tiket = filter_var($request->input('perlu_tiket', false), FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
        if (EkstendProses::adaBbm()) {
            $e->bbm = (float) $request->input('bbm', 0);
        }
        $e->id_kategori = $tipe === 'tujuan_baru' ? (int) $request->id_kategori : null;
        $e->detail_tujuan = $tipe === 'tujuan_baru' ? $request->detail_tujuan : null;
        $e->moda = $tipe === 'tujuan_baru' ? ($request->moda ?: null) : null;
        $e->save();

        $sppd = Sppd::find(EkstendProses::idSppd($e));
        $tipeLangkah = $e->no_urut ? 'extend-' . $e->no_urut : 'extend';
        if ($sppd) {
            $reset = ['status' => null, 'ket' => null, 'signature' => null];
            if (Schema::connection('esign')->hasColumn('verif_steps', 'chain_sha256')) {
                $reset['chain_sha256'] = null;
            }
            VerifStep::where('id_doc', $sppd->nomor_dokumen)->where('type', $tipeLangkah)->update($reset);
            SuratTteService::forgetStoredPdfs($sppd->nomor_dokumen, $tipeLangkah);
            $this->catatLogSppd((int) $sppd->id, [$this->potongLog(($e->no_urut ? 'Extend-' . $e->no_urut : 'Extend') . ' diubah (belum disetujui): ' . EkstendProses::judul($e) . '. Approval diulang.')]);
        }
        Peristiwa::sppdExtendDiubah($e);

        return new PostResource(true, ($e->no_urut ? 'Extend ' . $e->no_urut : 'Extend') . ' yang masih menunggu persetujuan diperbarui.', []);
    }

    /**
     * Dipanggil setelah satu langkah persetujuan 'extend' tersimpan: bila seluruh langkah sudah disetujui, extend yang menunggu
     * ditandai disetujui (masuk proses divisi umum/keuangan); bila ada yang menolak, extend yang menunggu tidak diproses.
     */
    private function selesaikanEkstend(string $idDoc, string $tipeLangkah = 'extend'): void
    {
        if (!EkstendProses::aktif()) {
            return;
        }
        try {
            $langkah = VerifStep::where('id_doc', $idDoc)->where('type', $tipeLangkah)->get();
            if ($langkah->isEmpty()) {
                return;
            }
            $sppd = Sppd::where('nomor_dokumen', $idDoc)->first();
            if (!$sppd) {
                return;
            }
            $menunggu = Ekstend::where('butuh_proses', 1)->whereNull('disetujui_at')
                ->whereIn('id_tujuan', TujuanSppd::where('id_sppd', $sppd->id)->pluck('id'));
            if (preg_match('/^extend-(\d+)$/', $tipeLangkah, $m)) {
                // approval per extend: hanya extend bernomor itu yang terpengaruh
                $menunggu->where('no_urut', (int) $m[1]);
            } elseif (EkstendProses::adaNoUrut()) {
                // langkah 'extend' lama: hanya extend lama (tanpa nomor urut)
                $menunggu->whereNull('no_urut');
            }
            if ($langkah->contains(fn ($l) => $l->status === 'rejected' || $l->status === 'reject')) {
                $ditolak = (clone $menunggu)->get();
                $menunggu->update(['butuh_proses' => 0]);
                if ($ditolak->isNotEmpty()) {
                    $this->catatLogSppd((int) $sppd->id, [$this->potongLog('Extend ditolak: ' . $ditolak->map(fn ($x) => ($x->no_urut ? 'Extend-' . $x->no_urut . ' ' : '') . EkstendProses::judul($x))->implode('; '))]);
                }

                return;
            }
            if ($langkah->every(fn ($l) => $l->status === 'approve')) {
                $ids = (clone $menunggu)->pluck('id')->all();
                if ($ids) {
                    Ekstend::whereIn('id', $ids)->update(['disetujui_at' => now()]);
                    Peristiwa::sppdExtendSiapDiproses($sppd, Ekstend::whereIn('id', $ids)->get(), Employe::employeId());
                    $this->catatLogSppd((int) $sppd->id, [$this->potongLog('Extend disetujui: ' . Ekstend::whereIn('id', $ids)->get()->map(fn ($e) => ($e->no_urut ? 'Extend-' . $e->no_urut . ' ' : '') . EkstendProses::judul($e))->implode('; ') . '. Masuk proses ' . (Ekstend::whereIn('id', $ids)->where('perlu_tiket', 1)->exists() ? 'divisi umum (tiket) lalu divisi keuangan (uang muka)' : 'divisi keuangan (uang muka)'))]);
                }
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Penyelesaian extend gagal', ['id_doc' => $idDoc, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Batalkan extend (admin SPPD atau pengaju SPPD). Hanya extend dengan approval per pengajuan (no_urut terisi) yang belum diproses
     * divisi umum maupun keuangan. Data tetap tersimpan (dibatalkan_at); langkah approval yang belum final (menunggu / ditolak) dihapus.
     */
    function batalEkstend(Request $request, $id)
    {
        if (!EkstendProses::adaNoUrut()) {
            return response()->json(['success' => false, 'message' => 'Fitur batal extend belum aktif di database: jalankan db/2026-11-19/ekstend_per_pengajuan.sql terlebih dulu.'], 422);
        }
        $e = Ekstend::find($id);
        if (!$e) {
            return response()->json(['success' => false, 'message' => 'Extend tidak ditemukan.'], 404);
        }
        $idSppd = EkstendProses::idSppd($e);
        $sppd = $idSppd ? Sppd::find($idSppd) : null;
        if (!$sppd) {
            return response()->json(['success' => false, 'message' => 'SPPD extend ini tidak ditemukan.'], 404);
        }
        $me = Employe::employeId();
        if (!in_array('AdminSPPD', auth()->user()->roles ?? [], true) && (string) $sppd->submitted_by !== (string) $me) {
            return response()->json(['success' => false, 'message' => 'Hanya admin SPPD atau pengaju SPPD yang bisa membatalkan extend.'], 403);
        }
        if ($tolak = $this->tolakBilaVoid($sppd)) {
            return $tolak;
        }
        $alasan = trim((string) $request->alasan);
        if (mb_strlen($alasan) < 5) {
            return response()->json(['success' => false, 'message' => 'Alasan pembatalan wajib diisi (minimal 5 karakter).'], 422);
        }
        if ($e->dibatalkan_at) {
            return response()->json(['success' => false, 'message' => 'Extend ini sudah dibatalkan.'], 422);
        }
        if ($tolakProses = $this->alasanTidakBisaBatalEkstend($e)) {
            return response()->json(['success' => false, 'message' => $tolakProses], 422);
        }

        $tipeLangkahBatal = $e->no_urut ? 'extend-' . $e->no_urut : 'extend';
        $penyetujuMenunggu = VerifStep::where('id_doc', $sppd->nomor_dokumen)->where('type', $tipeLangkahBatal)->whereNull('status')->pluck('id_employe')->unique()->values()->all();
        DB::connection('mysql4')->transaction(function () use ($e, $me, $alasan) {
            $e->butuh_proses = 0;
            $e->dibatalkan_at = now();
            $e->dibatalkan_oleh = $me;
            $e->alasan_batal = mb_substr($alasan, 0, 255);
            $e->save();
        });
        // langkah approval di database lain (esign): hapus yang belum final supaya tidak menahan antrean review atau menandai SPPD ditolak.
        // Extend lama (tanpa nomor) memakai langkah 'extend' yang dipakai bersama: dihapus hanya bila tidak ada extend lama lain yang masih menunggu.
        $tipeLangkah = $e->no_urut ? 'extend-' . $e->no_urut : 'extend';
        $masihAdaLain = !$e->no_urut && Ekstend::where('id', '<>', $e->id)->whereNull('no_urut')->where('butuh_proses', 1)->whereNull('disetujui_at')
            ->whereIn('id_tujuan', TujuanSppd::where('id_sppd', $sppd->id)->pluck('id'))->exists();
        if (!$masihAdaLain) {
            VerifStep::where('id_doc', $sppd->nomor_dokumen)->where('type', $tipeLangkah)
                ->where(fn ($q) => $q->whereNull('status')->orWhereIn('status', ['rejected', 'reject']))->delete();
        }
        SuratTteService::forgetStoredPdfs($sppd->nomor_dokumen, $tipeLangkah);
        $nama = $e->no_urut ? 'Extend-' . $e->no_urut : 'Extend';
        $this->catatLogSppd((int) $sppd->id, [$this->potongLog($nama . ' dibatalkan: ' . EkstendProses::judul($e) . '. Alasan: ' . $alasan)]);

        Peristiwa::sppdExtendDibatalkan($sppd, $e, $alasan, $me, $penyetujuMenunggu);

        return new PostResource(true, $nama . ' dibatalkan.', []);
    }

    /** null bila extend masih boleh dibatalkan; selain itu pesan alasannya */
    private function alasanTidakBisaBatalEkstend(Ekstend $e): ?string
    {
        if ($e->dibatalkan_at) {
            return 'Extend ini sudah dibatalkan.';
        }
        // extend bernomor yang sudah ditolak tidak aktif lagi (butuh_proses dikosongkan saat ditolak)
        if ($e->no_urut && (int) $e->butuh_proses === 0 && !$e->disetujui_at) {
            return 'Extend ini sudah ditolak sehingga tidak perlu dibatalkan.';
        }
        // extend lama (tanpa nomor) hanya bisa dibatalkan selama masih pengajuan baru yang belum disetujui; data sebelum fitur proses tidak diubah
        if (!$e->no_urut && ((int) $e->butuh_proses !== 1 || $e->disetujui_at)) {
            return 'Extend lama yang sudah selesai tidak bisa dibatalkan.';
        }
        if ((int) $e->by_umum === 1 || (int) $e->uangmuka === 1
            || ProsesTiket::withoutGlobalScopes()->where('id_ekstend', $e->id)->exists()
            || ($this->adaTabelTermin() && ProsesTermin::withoutGlobalScopes()->where('id_ekstend', $e->id)->exists())) {
            return 'Extend ini sudah diproses divisi umum/keuangan sehingga tidak bisa dibatalkan.';
        }

        return null;
    }

    function tgaPlhPersen(Request $request)
    {
        // Hotel 30% hanya setelah SPPD disetujui, dengan tanggal di dalam rentang tujuan + extend yang sudah disetujui
        $request->validate([
            'id_tujuan' => 'required|integer',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);
        $tujuanHotel = TujuanSppd::find((int) $request->id_tujuan);
        if (!$tujuanHotel) {
            return response()->json(['success' => false, 'message' => 'Tujuan tidak ditemukan.'], 422);
        }
        $barisHotel = ListSppdRingan::find((int) $tujuanHotel->id_sppd);
        if (!$barisHotel || $barisHotel->current_status !== 'signed') {
            return response()->json(['success' => false, 'message' => 'Hotel 30% hanya bisa diajukan setelah SPPD disetujui.'], 422);
        }
        if (array_key_exists((int) $tujuanHotel->id_sppd, SppdVoid::peta())) {
            return response()->json(['success' => false, 'message' => 'SPPD ini sudah di-void.'], 422);
        }
        $j = $this->jendelaHotel30($tujuanHotel, $this->akhirEkstendDisetujuiPerTujuan([(int) $tujuanHotel->id])->get($tujuanHotel->id));
        $dari = date('Y-m-d', strtotime((string) $request->start_date));
        $hingga = date('Y-m-d', strtotime((string) $request->end_date));
        if ($dari < $j['mulai'] || $hingga > $j['akhir']) {
            $ket = 'SPPD ' . date('d-m-Y', strtotime($j['mulai'])) . ' s.d. ' . date('d-m-Y', strtotime((string) $tujuanHotel->waktu_kembali))
                . ($j['ekstend_akhir'] ? ', extend disetujui sampai ' . date('d-m-Y', strtotime($j['ekstend_akhir'])) : '');

            return response()->json(['success' => false, 'message' => 'Tanggal Hotel 30% harus dalam ' . date('d-m-Y', strtotime($j['mulai'])) . ' s.d. ' . date('d-m-Y', strtotime($j['akhir'])) . " ({$ket})."], 422);
        }


        // Hotel 30% hanya diajukan sekali per tujuan; pengajuan berikutnya hanya mengubah tanggal pengajuan sebelumnya
        $sudahAda = TigaPuluh::where('id_tujuan', (int) $request->id_tujuan)->orderBy('id')->first();
        if ($sudahAda) {
            $dipakai = DB::connection('esign')->table('hotel30_tokens')->where('id_tujuan', (int) $request->id_tujuan)->whereNotNull('dipakai_at')->exists();
            if ($dipakai) {
                return response()->json(['success' => false, 'message' => 'Pengajuan Hotel 30% ini sudah dijawab pelaksana sehingga tidak bisa diubah.'], 422);
            }
            $sudahAda->mulai = $request->start_date;
            $sudahAda->selesai = $request->end_date;
            $sudahAda->save();

            return new PostResource(true, 'Pengajuan Hotel 30% sebelumnya berhasil diubah.', []);
        }

        // pelaksana bukan karyawan (employe_id '-'): persetujuannya lewat tautan email, jadi email wajib diisi
        $sppdTujuan = DB::connection('mysql4')->table('tujuan_sppds as t')->join('sppds as s', 's.id', '=', 't.id_sppd')
            ->where('t.id', $request->id_tujuan)->first(['s.employe_id']);
        $tamu = $sppdTujuan && Hotel30TamuController::adalahTamu($sppdTujuan->employe_id);
        if ($tamu) {
            $request->validate(['email' => 'required|email|max:150']);
        }

        $tp=new TigaPuluh();
        $tp->id_tujuan=$request->id_tujuan;
        $tp->submitted_by=Employe::employeId();
        $tp->mulai=$request->start_date;
        $tp->selesai=$request->end_date;

        if ($tp->save()) {
            if ($tamu) {
                [, $pesan] = app(Hotel30TamuController::class)->buatDanKirim((int) $request->id_tujuan, $request->input('email'));

                return new PostResource(true, 'Pengajuan berhasil. ' . $pesan, []);
            }

            return new PostResource(true, 'success', []);
        } else {
            return new PostResource(false, 'error', []);
        }
    }
}
