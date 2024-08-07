<?php

namespace App\Http\Controllers\Sppd;

use App\Http\Controllers\Controller;
use App\Http\Resources\PostResource;
use Illuminate\Http\Request;
use App\Models\Sppd\Sppd;
use App\Models\Sppd\TujuanSppd;
use App\Models\Employe;
use App\Models\Sppd\PenomoranSppd;
use App\Models\Sppd\KetetapanSppd;


class PengajuanController extends Controller
{

    public function store(Request $request)
    {
        $sppd = new Sppd();
        $sppd->nomor_sppd = ((PenomoranSppd::find($request->nomor)->last_number) + 1) . '/PEMA/ST-' . PenomoranSppd::find($request->nomor)->kode. '/' .$this->getRomawi(date('m')) . '/' . date('m') . '/' . date('Y');
        $sppd->nomor_dokumen=unique_random('documents', 'doc_id', 40);
        $sppd->employe_id=$request->employe_id;
        $sppd->nama=$request->name;
        $sppd->jabatan=$request->jabatann;
        $sppd->golongan_rate=$request->rate;
        $sppd->ketetapan=
        $sppd->submitted_by=Employe::employeId();
        $sppd->ketetapan=KetetapanSppd::where('status', 'active')->first()->id;
        if($sppd->save()){
            return new PostResource(true, 'success !!', $sppd);
        }
        // return new PostResource(true, unique_random('documents', 'doc_id', 40), $request->all());
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
}
