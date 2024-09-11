<?php

namespace App\Http\Controllers\Sppd;

use App\Http\Controllers\Controller;
use App\Http\Resources\PostResource;
use Illuminate\Http\Request;
use App\Models\Sppd\Sppd;
use App\Models\Sppd\TujuanSppd;
use App\Models\Employe;
use App\Models\ESign\VerifStep;
use App\Models\Sppd\HitunganBiaya;
use App\Models\Sppd\PenomoranSppd;
use App\Models\Sppd\KetetapanSppd;
use App\Models\Sppd\ListApproval;
use Illuminate\Support\Facades\Storage;
use App\Models\Sppd\ListSppd;
use App\Models\Sppd\LogPengajuan;
use App\Models\Sppd\Realisasi;
use App\Models\Sppd\RealisasiTujuan;
use Illuminate\Cache\RateLimiting\Limit;

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
        $sppd->ketetapan =
            $sppd->submitted_by = Employe::employeId();
        $sppd->ketetapan = KetetapanSppd::where('status', 'active')->first()->id;
        $tujuans = $request->tujuan_sppd;

        if ($sppd->save()) {

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
                    'waktu_kembali' =>  date('Y-m-d H:i:s', strtotime($tujuans[$i]['waktu_kembali']))
                ]);
            }
            return new PostResource(true, 'success', []);
        }
    }

    function getSubmitted(Request $request){
        if($request->ref=='mine'){
            $data=ListSppd::where('employe_id', Employe::employeId())->orderBy('id', 'DESC')->get();
        }elseif($request->ref=='review'){
            $data=ListSppd::where('current_reviewer', Employe::employeId())->orderBy('id', 'DESC')->get();          
        }
        else{
            $data=ListSppd::where('submitted_by', Employe::employeId())->orderBy('id', 'DESC')->get();
        }
       
        return new PostResource(true, $request->ref, $data);
    }

    function getDetail($id){
        $data=ListSppd::find($id);
        $tujuans=HitunganBiaya::where('id_sppd', $id)->get();
        foreach ($tujuans as $t){
            if($t->file_undangan !== '-'){
                $t->base64_undangan=base64_encode(Storage::disk('public_sppd')->get($t->file_undangan));
            }else{
                $t->base64_undangan='-';
            }
            
        }
        $data['tujuan_sppd']=$tujuans;
        $data['approval']=ListApproval::where('id_sppd', $id)->orderBy('step', 'ASC')->get();
        $data['log_pengajuan']=LogPengajuan::where('id_sppd', $id)->orderBy('created_at', 'ASC')->get();
        return new PostResource(true, 'success', $data);
    }


    function updatePengajuan(Request $request, $id){
        $sppd=Sppd::find($id);

        $sppd->nama=$request->name;
        $sppd->jabatan = $request->jabatann;
        $sppd->golongan_rate = $request->rate;
        $tujuans = $request->tujuan_sppd;
        if($sppd->touch()){
            if(TujuanSppd::where('id_sppd', $id)->delete()){
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
                        'sumber' => $tujuans[$i]['sumber_biaya']||null,
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
                        'waktu_kembali' =>  date('Y-m-d H:i:s', strtotime($tujuans[$i]['waktu_kembali']))
                    ]);
                }
                return new PostResource(true, 'success', []);
            }
        }
    }

    function persetujuan(Request $request, $id_doc){
        $verif=VerifStep::where('id_employe', Employe::employeId())->where('id_doc',  $id_doc)->where('status', NULL)->limit(1)->first();
        $verif->status=$request->status;
        $verif->ket=$request->catatan_persetujuan;
        if($verif->save()){
            return new PostResource(true, 'success', []);   
        }
    }

    function getRomawi($bln)
    {
        switch ($bln) {
            case 1:
                return "I";
                break;
            case 2:
                return "II";
                break;
            case 3:
                return "III";
                break;
            case 4:
                return "IV";
                break;
            case 5:
                return "V";
                break;
            case 6:
                return "VI";
                break;
            case 7:
                return "VII";
                break;
            case 8:
                return "VIII";
                break;
            case 9:
                return "IX";
                break;
            case 10:
                return "X";
                break;
            case 11:
                return "XI";
                break;
            case 12:
                return "XII";
                break;
        }
    }


// realisasi
    function submitRealisasi(Request $request){

        if(Realisasi::where('id_sppd', $request->id_sppd)->exists()){
            Realisasi::where('id_sppd', $request->id_sppd)->delete();
            RealisasiTujuan::where('id_sppd', $request->id_sppd)->delete();
        }
        $file = base64_decode(str_replace('data:application/pdf;base64,', '', $request->doc_file), true);
        $fileName = 'realisasi/' . date('Y') . '/' . date('m') . '/' . date('d') . '/' . $request->id_sppd . '.pdf';
        if (Storage::disk('public_sppd')->put($fileName, $file)) {
            $realisasi=new Realisasi();
            $realisasi->id_sppd=$request->id_sppd;
            $realisasi->submitted_by=Employe::employeId();
            $realisasi->doc_file=$fileName;
            if($realisasi->save()){
                $tujuan_realisasi=$request->tujuan_realisasi;
                for ($i = 0; $i < count($tujuan_realisasi); $i++) {
                    RealisasiTujuan::insert([
                        'id_tujuan' => $tujuan_realisasi[$i]['id'],
                        'rill_tiket'  => $tujuan_realisasi[$i]['rill_tiket'],
                        'rill_hotel'=> $tujuan_realisasi[$i]['rill_hotel'],
                        'rill_wb'=>  date('Y-m-d H:i:s', strtotime($tujuan_realisasi[$i]['rill_wb'])),
                        'rill_wt'=> date('Y-m-d H:i:s', strtotime($tujuan_realisasi[$i]['rill_wt'])),
                    ]);
                }
                return new PostResource(true, 'success', []);
            }
        }else{
            return new PostResource(false, 'error', []);
        }
    }
}


