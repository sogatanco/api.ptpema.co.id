<?php

namespace App\Http\Controllers\Sppd;

use App\Http\Controllers\Controller;
use App\Http\Resources\PostResource;
use App\Models\Sppd\Ekstend;
use App\Models\Sppd\EkstendView;
use App\Models\Sppd\TigaPuluh;
use Illuminate\Http\Request;
use App\Models\Sppd\Sppd;
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
use App\Models\Sppd\UnderReview;
use App\Models\Sppd\LogPengajuan;
use App\Models\Sppd\Proses;
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

                ]);
            }
            return new PostResource(true, 'success', []);
        }
    }

    function getSubmitted(Request $request)
    {
        if ($request->ref == 'mine') {
            $data = ListSppdRingan::where('employe_id', Employe::employeId())->orderBy('id', 'DESC')->get();
        } elseif ($request->ref == 'review') {
            $data = UnderReview::where('current_reviewer', Employe::employeId())->orderBy('id', 'DESC')->get();
        } elseif ($request->ref == 'approved_by') {
            $data = ApprovedSppd::where('approval_id', Employe::employeId())->get();
        } elseif ($request->ref == 'by_umum') {
            $data = ListSppdRingan::where('current_status', 'signed')->where('by_umum', 0)->get();
            foreach ($data as $d) {
                $d->type_proses = 'pemesanan_tiket';
                $d->id_unique = rand(1, 100) * $d->id;
            }
        } elseif ($request->ref == 'by_keuangan') {
            $data1 = ListSppdRingan::where('uangmuka', 0)->where('current_status', 'signed')->get();

            $data1 = $data1->map(function ($d1) {
                $d1->type_proses = 'uangmuka';
                $d1array = $d1->toArray();
                $d1array = ['id_unique' => (rand(1, 100) * $d1->id)] + $d1array;
                return $d1array;
            });


            $data2 = ListSppdRingan::where('realisasi_status', 'verified')->where('by_keuangan', 0)->get();
            $data2 = $data2->map(function ($d2) {
                $d2->type_proses = 'realisasi';
                $d2array = $d2->toArray();
                $d2array = ['id_unique' => (rand(1, 100) * $d2->id)] + $d2array;
                return $d2array;
            });
            $tempCollection = collect([$data1, $data2]);
            $data = $tempCollection->flatten(1);
        } else {
            $data = ListSppdRingan::where('submitted_by', Employe::employeId())->orderBy('id', 'DESC')->get();
        }

        return new PostResource(true, $request->ref, $data);
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
        foreach ($tujuans as $t) {
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
            $hr = 4;
            $hariall = $t->jumlah_hari;

            if ($hariall > $hr) {

                $j_k = ($hariall) % $hr;
                $jt = ($hariall - $j_k) / $hr;

                $terminArray = [];
                for ($tr = 0; $tr < $jt; $tr++) {
                    if ($tr == 0) {
                        $terminArray[$tr] = (object)[
                            'id' => $tr,
                            'tgl_bayar' => date('Y-m-d', strtotime($t->waktu_berangkat)),
                            'jumlah' => ($t->rate_wb * $t->rate_um) + ($t->rate_wb * $t->rate_tr)  + $t->bbm + (($hr - 1) * ($t->rate_um + $t->rate_tr)) + ($t->rate_hotel * $hr)
                        ];
                    } elseif ($tr == ($jt - 1)) {
                        if ($j_k <= 0) {
                            $terminArray[$tr] = (object)[
                                'id' => $tr,
                                'tgl_bayar' => date('Y-m-d', strtotime($t->waktu_berangkat . '+ ' . ($tr * $hr) . ' days')),
                                'jumlah' => ($t->rate_wt * $t->rate_um) + ($t->rate_wt * $t->rate_tr)  + (($hr - 1) * ($t->rate_um + $t->rate_tr) + ($hr * $t->rate_hotel))
                            ];
                        } else {
                            $terminArray[$tr] = (object)[
                                'id' => $tr,
                                'tgl_bayar' => date('Y-m-d', strtotime($t->waktu_berangkat . '+ ' . ($tr * $hr) . ' days')),
                                'jumlah' => ($hr * ($t->rate_um + $t->rate_tr + $t->rate_hotel))
                            ];
                        }
                    } else {
                        $terminArray[$tr] = (object)[
                            'id' => $tr,
                            'tgl_bayar' => date('Y-m-d', strtotime($t->waktu_berangkat . '+ ' . ($tr * $hr) . ' days')),
                            'jumlah' => ($hr * ($t->rate_um + $t->rate_tr + $t->rate_hotel))
                        ];
                    }
                    // $terminArray[$tr]=$tr;
                }

                if ($j_k > 0) {
                    $terminArray[$tr] = (object)[
                        'id' => $tr,
                        'tgl_bayar' => date('Y-m-d', strtotime($t->waktu_berangkat . '+ ' . ($jt * $hr) . ' days')),
                        'jumlah' => ($t->rate_wt * $t->rate_um) + ($t->rate_wt * $t->rate_tr)  + (($j_k - 1) * ($t->rate_um + $t->rate_tr + $t->rate_hotel))
                    ];
                }
                $t->termin = $terminArray;
            } else {
                $obj = (object)[
                    'id' => 0,
                    'tgl_bayar' => $t->waktu_berangkat,
                    'jumlah' => $t->uang_muka
                ];
                $t->termin = array($obj);
            }
        }
        $rill = Realisasi::where('id_sppd', $id)->first();
        if (!is_null($rill)) {
            $rill->submitter_name = Employe::where('employe_id', $rill->submitted_by)->first()->first_name;
        }

        $data['realisasi'] = $rill;
        $data['ekstend']=EkstendView::where('id_sppd', $id)->get();

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
        return new PostResource(true, 'success', $data);
    }


    function updatePengajuan(Request $request, $id)
    {
        $sppd = Sppd::find($id);

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
                ]);
            }
            return new PostResource(true, 'success', []);
        }
    }

    function persetujuan(Request $request, $id_doc)
    {
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
        $verif = VerifStep::where('id_employe', Employe::employeId())->where('id_doc',  $id_doc)->where('status', NULL)->limit(1)->first();
        $verif->status = $request->status;
        $verif->ket = $request->catatan_persetujuan;
        $contentHash = null;
        if ($request->status === 'approve') {
            $contentHash = SuratTteService::sppdContentHash($id_doc);
            $verif->signature = $this->signApproval($id_doc, $verif, $contentHash);
        }
        if ($verif->save()) {
            if ($request->status === 'approve') {
                SuratTteService::recordChain($verif, $contentHash);
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
            return new PostResource(true, 'success', []);
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

        $data['groupKar'] = $perKaryawan($verified, fn ($s) => $s->nama)->map(fn ($k) => collect($k)->except('employe_id')->all())->values();
        return new PostResource(true, 'dashboard', $data);
    }

    function getTujuanById($id)
    {
        $data = TujuanSppd::where('id_sppd', $id)->get();
        $tigaPuluh = TigaPuluh::whereIn('id_tujuan', $data->pluck('id'))->get()->unique('id_tujuan')->keyBy('id_tujuan');
        foreach ($data as $t) {
            $t->tigapuluh = $tigaPuluh->get($t->id);
        }
        return new PostResource(true, 'success', $data);
    }

    function insertEkstend(Request $request)
    {
        // Hapus ekstend dengan id_tujuan yang sama sebelum insert
        Ekstend::where('id_tujuan', $request->id_tujuan)->delete();

        $ekstend = new Ekstend();
        $ekstend->id_tujuan = $request->id_tujuan;
        $ekstend->alasan = $request->alasan;
        $ekstend->start = $request->start;
        $ekstend->end = $request->end;
        $ekstend->submitted_by = Employe::employeId();

        if ($ekstend->save()) {
            return new PostResource(true, 'success', []);
        } else {
            return new PostResource(false, 'error', []);
        }
    }

    function tgaPlhPersen(Request $request)
    {

        $tp=new TigaPuluh();
        $tp->id_tujuan=$request->id_tujuan;
        $tp->submitted_by=Employe::employeId();
        $tp->mulai=$request->start_date;
        $tp->selesai=$request->end_date;

        if ($tp->save()) {
            return new PostResource(true, 'success', []);
        } else {
            return new PostResource(false, 'error', []);
        }
    }
}
