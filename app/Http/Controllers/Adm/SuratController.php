<?php

namespace App\Http\Controllers\Adm;

use App\Http\Controllers\Controller;
use App\Http\Resources\PostResource;
use App\Models\Adm\ApprovedDocument;
use App\Models\Adm\ListSurat;
use App\Models\Adm\PenomoranSurat;
use App\Models\Adm\Surat;
use App\Models\Employe;
use App\Models\ESign\VerifStep;
use App\Models\Structure;
use App\Models\Verify\ListVerif;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Services\SignatureService;
use App\Services\CryptoService;

class SuratController extends Controller
{
    function insert(Request $request)
    {


        $surat = new Surat();


        if ($request->lampiran + 0 > 0) {
            $file = base64_decode(str_replace('data:application/pdf;base64,', '', $request->fileLampiran), true);
            $fileName = 'lampiran/' . date('Y') . '/' . sprintf("%02d", ((PenomoranSurat::where('type', $request->type)->first()->last_number) + 1)) . '.pdf';
            if (Storage::disk('public_adm')->put($fileName, $file)) {
                $surat->file_lampiran = $fileName;
            }
        }

        $surat->nomor_surat = sprintf("%02d", ((PenomoranSurat::where('type', $request->type)->first()->last_number) + 1)) . '/' . PenomoranSurat::where('type', $request->type)->first()->kode . '/' . $this->getRomawi(date('m')) . '/' . date('Y');
        $surat->no_document = unique_random('documents', 'doc_id', 40);
        $surat->kepada = $request->kepada;
        $surat->perihal = $request->perihal;
        $surat->j_lampiran = $request->lampiran;
        $surat->jenis_lampiran = $request->jenislampiran;
        $surat->isi_surat = $request->isiSurat;
        $surat->tembusans = implode(",", $request->tembusans);
        $surat->id_divisi = $request->divisi;
        $surat->submitted_by = Employe::employeId();
        $surat->submitted_current_position = (Structure::where('employe_id', $surat->submitted_by)->first('position_name')->position_name);
        $surat->sign_by = $request->ttdBy;
        $surat->bahasa = $request->bhs;

        if ($surat->save()) {
            return new PostResource(true, 'Data Inserted', []);
        }

    }

    public function update(Request $request)
    {
        $surat = Surat::find($request->id);
        if ($request->lampiran + 0 > 0 && $surat->file_lampiran !== '') {
            $file = base64_decode(str_replace('data:application/pdf;base64,', '', $request->fileLampiran), true);
            $fileName = 'lampiran/' . date('Y') . '/' . strtok($surat->nomor_surat, '/') . '.pdf';
            if (Storage::disk('public_adm')->put($fileName, $file)) {
                $surat->file_lampiran = $fileName;
            }
        }

        $surat->kepada = $request->kepada;
        $surat->perihal = $request->perihal;
        $surat->j_lampiran = $request->lampiran;
        $surat->jenis_lampiran = $request->jenislampiran;
        $surat->isi_surat = $request->isiSurat;
        $surat->tembusans = implode(",", $request->tembusans);
        $surat->id_divisi = $request->divisi;
        $surat->submitted_by = Employe::employeId();
        $surat->submitted_current_position = (Structure::where('employe_id', $surat->submitted_by)->first('position_name')->position_name);
        $surat->sign_by = $request->ttdBy;
        $surat->bahasa = $request->bhs;
        if ($surat->save()) {
            return new PostResource(true, 'Data Updated', []);
        }
    }

    public function getSurat($what)
    {
        if ($what === 'approved') {
            $data = ApprovedDocument::where('id_employe', Employe::employeId())->get();
        } elseif ($what === 'review') {
            $data = ListSurat::where('current_reviewer', Employe::employeId())->get();
        } elseif ($what === 'signed') {
            $data = ApprovedDocument::where('type', 'sign')->latest('created_at')->get();
        } else {
            $data = ListSurat::where('created_by', Employe::employeId())->latest()->get();
        }

        return new PostResource(true, 'data surat', $data);
    }

    function detail($id)
    {
        // $doc = Surat::find($id);
        // $signer = ListVerif::where('id_doc', $doc->no_document)->where('type', 'sign')->first();

        // $document['perubahan_terakhir'] = $doc->updated_at;
        // $document['nomor_dokument'] = $doc->no_document;

        // $document['nomorSurat'] = $doc->nomor_surat;
        // $document['tglSurat'] = $doc->created_at;
        // $document['lampiran'] = $doc->j_lampiran;
        // $document['jenisLampiran'] = $doc->jenis_lampiran;
        // $document['kepada'] = $doc->kepada;
        // $document['perihal'] = $doc->perihal;
        // $document['isiSurat'] = $doc->isi_surat;
        // $document['ttdBy'] = $doc->sign_by;
        // $document['bhs'] = $doc->bahasa;
        // if ($doc->tembusans !== null && $doc->tembusans !== '') {
        //     $document['tembusans'] = explode(',', $doc->tembusans);
        // } else {
        //     $document['tembusans'] = [];
        // }
        // if ($doc->file_lampiran !== null && file_exists(public_path('adm/' . $doc->file_lampiran))) {
        //     $document['lampiran'] = base64_encode(file_get_contents(public_path('adm/' . $doc->file_lampiran)));
        // } else {
        //     $document['lampiran'] = '-';
        // }
        // $document['signer']['first_name'] = $signer->employe_name;
        // $document['signer']['position_name'] = $signer->id_current_position;
        // $document['status']=ListSurat::find($id)->status;
      
        $data = ListSurat::find($id);
        $si = ListVerif::where('id_doc', $data->no_document)->where('type', 'sign')->first();

        $data['signer']['first_name'] = $si->employe_name;
        $data['signer']['position_name'] = $si->id_current_position;
        // $data['signer'] = Employe::where('employe_id', $data->sign_by)->first();
        $data['tglSurat'] = $data->created_at;
        $data['nomorSurat'] = $data->nomor_surat;
        $data['lampiran'] = $data->j_lampiran;
        $data['jenisLampiran'] = $data->jenis_lampiran;
        $data['isiSurat'] = $data->isi_surat;
        $data['ttdBy'] = $data->sign_by;
        $data['bhs'] = $data->bhs;
        if ($data->tembusans !== null && $data->tembusans !== '') {
            $data['tembusans'] = explode(',', $data->tembusans);
        } else {
            $data['tembusans'] = [];
        }

        if ($data->file_lampiran !== null && file_exists(public_path('adm/' . $data->file_lampiran))) {
            $data['fileLampiran'] = base64_encode(file_get_contents(public_path('adm/' . $data->file_lampiran)));
        } else {
            $data['fileLampiran'] = '';
        }
        return new PostResource(true, 'data surat', $data);
    }

    function detail1($id)
    {
        $doc = Surat::find($id);
        $signer = ListVerif::where('id_doc', $doc->no_document)->where('type', 'sign')->first();

        $document['perubahan_terakhir'] = $doc->updated_at;
        $document['nomor_dokument'] = $doc->no_document;

        $document['nomorSurat'] = $doc->nomor_surat;
        $document['tglSurat'] = $doc->created_at;
        $document['lampiran'] = $doc->j_lampiran;
        $document['jenisLampiran'] = $doc->jenis_lampiran;
        $document['kepada'] = $doc->kepada;
        $document['perihal'] = $doc->perihal;
        $document['isiSurat'] = $doc->isi_surat;
        $document['ttdBy'] = $doc->sign_by;
        $document['bhs'] = $doc->bahasa;
        if ($doc->tembusans !== null && $doc->tembusans !== '') {
            $document['tembusans'] = explode(',', $doc->tembusans);
        } else {
            $document['tembusans'] = [];
        }
        if ($doc->file_lampiran !== null && file_exists(public_path('adm/' . $doc->file_lampiran))) {
            $document['lampiran'] = base64_encode(file_get_contents(public_path('adm/' . $doc->file_lampiran)));
        } else {
            $document['lampiran'] = '-';
        }
        $document['signer']['first_name'] = $signer->employe_name;
        $document['signer']['position_name'] = $signer->id_current_position;
        $document['status']=ListSurat::find($id)->status;
        return new PostResource(true, 'data surat', $document);
    }

    function reviewDokumen($id_doc, Request $request)
    {
        $document=self::detail1(Surat::where('no_document', $id_doc)->first('id')->id);
        // $doc = Surat::where('no_document', $id_doc)->first();
        // $signer = ListVerif::where('id_doc', $id_doc)->where('type', 'sign')->first();

        // $document['perubahan_terakhir'] = $doc->updated_at;
        // $document['nomor_dokument'] = $doc->no_document;

        // $document['nomorSurat'] = $doc->nomor_surat;
        // $document['tglSurat'] = $doc->created_at;
        // $document['lampiran'] = $doc->j_lampiran;
        // $document['jenisLampiran'] = $doc->jenis_lampiran;
        // $document['kepada'] = $doc->kepada;
        // $document['perihal'] = $doc->perihal;
        // $document['isiSurat'] = $doc->isi_surat;
        // $document['bhs'] = $doc->bahasa;
        // if ($doc->tembusans !== null && $doc->tembusans !== '') {
        //     $document['tembusans'] = explode(',', $doc->tembusans);
        // } else {
        //     $document['tembusans'] = [];
        // }
        // if ($doc->file_lampiran !== null && file_exists(public_path('adm/' . $doc->file_lampiran))) {
        //     $document['lampiran'] = base64_encode(file_get_contents(public_path('adm/' . $doc->file_lampiran)));
        // } else {
        //     $document['lampiran'] = '-';
        // }
        // $document['signer']['first_name'] = $signer->employe_name;
        // $document['signer']['position_name'] = $signer->id_current_position;

        if(is_null(CryptoService::getPublicKey(Employe::employeId()))){
            CryptoService::generateKeys(Employe::employeId());
        }

        $signature = SignatureService::signDocument(Employe::employeId(), $document->toArray(request())['data']);
        
        $verif = VerifStep::where('id_doc', $id_doc)->where('id_employe', Employe::employeId())->where('status', NULL)->first();
        $verif->status = $request->status;
        $verif->ket = $request->catatan_persetujuan;
        $verif->signature=$signature;
        if ($verif->save()) {
            return new PostResource(true, 'success', $document);
        }

        return new PostResource(true, 'success',  $document->toArray(request())['data']);
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
}
