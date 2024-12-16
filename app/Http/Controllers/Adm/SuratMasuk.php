<?php

namespace App\Http\Controllers\Adm;

use App\Http\Controllers\Controller;
use App\Http\Resources\PostResource;
use App\Models\Adm\Disposisi;
use App\Models\Adm\ListSuratMasuk;
use App\Models\Division;
use App\Models\Employe;
use App\Models\Position;
use App\Models\Structure;
use App\Models\Adm\CC;
use App\Models\Adm\ListCC;
use Illuminate\Http\Request;
use App\Models\Adm\SuratMasuk as SM;
use Illuminate\Support\Facades\Storage;

class SuratMasuk extends Controller
{
    public function insert(Request $request)
    {
        $now = new \DateTime();
        $uniqueCode = $now->format('YmdHis') . '-' . substr(microtime(), 2, 6);

        $teks = str_replace(' ', '-', $request->perihal);
        $teks = preg_replace('/[^a-zA-Z-]/', '', $teks);
        $fileName = 'surat_masuk/' . date('Y') . '/' . date('m') . '/' . $uniqueCode . '-' . $teks . '.pdf';

        $suratMasuk = new SM();

        if (Storage::disk('public_adm')->put($fileName, file_get_contents($request->file('file')->getRealPath()))) {
            $suratMasuk->file = $fileName;
            $suratMasuk->nomor = $request->nomorSurat;
            $suratMasuk->pengirim = $request->pengirim;
            $suratMasuk->perihal = $request->perihal;
            $suratMasuk->id_direksi = $request->dir;
            $suratMasuk->via = $request->via;
            $suratMasuk->insert_by = Employe::employeId();
            $suratMasuk->tgl_surat = date('Y-m-d', strtotime($request->tglSurat));
            if ($suratMasuk->save()) {
                return new PostResource(true, 'Berhasil', []);
            }

        }

        // return new PostResource(true, 'sdgsdg',$request->all());
    }

    public function getSM($what)
    {
        $data = [];
        if ($what == 'to_me') {
            $data = ListSuratMasuk::where('live_receiver', Employe::employeId())->whereNull('tindak_lanjut')->latest('diterima')->get();
            foreach ($data as $d) {
                $d->diproses = count(Disposisi::where('id_surat', $d->id_surat)->get());
            }
        } else if ($what == 'tinjut') {
            $data = ListSuratMasuk::where('live_receiver', Employe::employeId())->whereNotNull('tindak_lanjut')->latest('ditinjut')->get();
            foreach ($data as $d) {
                $d->diproses = count(Disposisi::where('id_surat', $d->id_surat)->get());
            }
        } else if ($what == 'all') {
            $data = SM::latest('created_at')->get();
            foreach ($data as $d) {
                $d->nama_dir = Structure::where('position_id', $d->id_direksi)->first('first_name')->first_name;
                $d->by_name = Structure::where('employe_id', $d->insert_by)->first('first_name')->first_name;
                if(count(Disposisi::where('id_surat', $d->id)->get())==1){
                    if(count(Disposisi::where('id_surat', $d->id)->whereNull('tindak_lanjut')->get())<1){
                        $c=2;
                    }else{
                        $c=1;
                    }
                }else{
                    $c=count(Disposisi::where('id_surat', $d->id)->get() );
                }
                $d->diproses = $c;
            }
        }else if ($what == 'cc') {
            $data=ListCC::where('live_cc', Employe::employeId())->latest('cc_at')->get();
        }

        return new PostResource(true, 'data Surat Masuk', $data);
    }

    public function getDetail($id)
    {
        $data = SM::find($id);
        if ($data->file !== null && file_exists(public_path('adm/' . $data->file))) {
            $data['file_surat'] = base64_encode(file_get_contents(public_path('adm/' . $data->file)));
        }
        return new PostResource(true, 'data Surat Masuk', $data);
    }

    public function disposisi($idSurat, Request $request)
    {
        $nowDispo = Disposisi::find(ListSuratMasuk::where('id_surat', $idSurat)->where('live_receiver', Employe::employeId())->first('id')->id);
        $nowDispo->tindak_lanjut = $request->what;
        $nowDispo->tinjut_by = Employe::employeId();
        $nowDispo->tinjut_by_position=Structure::where('employe_id', Employe::employeId())->first('position_name')->position_name;

        if ($nowDispo->save()) {
            if ($request->what === 'dispo') {
                $nextDispo = new Disposisi();

                $nextDispo->id_surat = $idSurat;
                $nextDispo->employe_id = Employe::employeId();
                $nextDispo->position = Structure::where('employe_id', Employe::employeId())->first('position_name')->position_name;
                $nextDispo->tickler = implode(",", $request->tickler);
                $nextDispo->catatan = $request->catatan;
                $nextDispo->dispo_to = $request->to['type'];
                $nextDispo->id_penerima = $request->to['value'];
                $nextDispo->updated_at = null;
                if ($nextDispo->save()) {
                    if (count($request->cc) > 0) {
                        for ($i = 0; $i < count($request->cc); $i++) {
                            CC::create([
                                'id_dispo' => $nextDispo->id,
                                'cc_to' => $request->cc[$i]['type'],
                                'id_penerima' => $request->cc[$i]['value'],
                                'created_at' => $nextDispo->created_at,
                            ]);
                        }
                    }
                    return new PostResource(true, 'Surat Sudah Berhasil di disposisi', []);
                }
            } else {
                return new PostResource(true, 'Surat Sudah Berhasil di Proses', []);
            }
        }
    }

    public function delete($id)
    {
        $sm = SM::find($id);
        if ($sm->delete()) {
            return new PostResource(true, 'Surat Sudah Berhasil di Hapus', []);
        }
    }

    public function getRiwayat($id)
    {
        $riwayat = Disposisi::where('id_surat', $id)->get();
        $collection = collect();
        foreach ($riwayat as $r) {
            $cc=[];

            $data=CC::where('id_dispo', $r->id)->get();
            foreach ($data as $c) {
                array_push($cc, ($c->cc_to=='position'?Position::where('position_id',$c->id_penerima)->first('position_name')->position_name:Division::where('organization_id',$c->id_penerima)->first('organization_name')->organization_name));
            }

           if($r->tindak_lanjut== 'tinjut'){

            $collection->push([
                'employe_id' => $r->employe_id,
                'position' => $r->position,
                'nama' => Structure::where('employe_id', $r->employe_id)->first('first_name')->first_name,
                'activity' =>  str_contains($r->position, 'Administrator')?'Dokumen disampaikan kepada '.($r->dispo_to=='position'?Position::where('position_id',$r->id_penerima)->first('position_name')->position_name:'')
                
                :
                
                'Dokumen didisposisikan kepada '.($r->dispo_to=='position'?Position::where('position_id',$r->id_penerima)->first('position_name')->position_name:Division::where('organization_id',$r->id_penerima)->first('organization_name')->organization_name),
                'cc'=>$cc,
                'waktu'=>$r->created_at,
            ]);
            $collection->push([
                'employe_id' => $r->tinjut_by,
                'position' => $r->tinjut_by_position,
                'nama' => Structure::where('employe_id', $r->tinjut_by)->first('first_name')->first_name,
                'activity' => 'Surat ditindaklanjuti dan tidak didisposisi',
                'waktu'=>$r->updated_at,
            ]);

           }else{
            $collection->push([
                'employe_id' => $r->employe_id,
                'position' => $r->position,
                'nama' => Structure::where('employe_id', $r->employe_id)->first('first_name')->first_name,
                'activity' => str_contains($r->position, 'Administrator')?'Dokumen disampaikan kepada '.($r->dispo_to=='position'?Position::where('position_id',$r->id_penerima)->first('position_name')->position_name:'')
                
                :
                
                'Dokumen didisposisikan kepada '.($r->dispo_to=='position'?Position::where('position_id',$r->id_penerima)->first('position_name')->position_name:Division::where('organization_id',$r->id_penerima)->first('organization_name')->organization_name),
                'cc'=>$cc,
                'waktu'=>$r->created_at,
            ]);

           }
        }

        return new PostResource(true,'Riwayat', $collection->toArray());

    }

    public function readCC($id){
        $cc=CC::find($id);
        $cc->read_by=Employe::employeId();
        $cc->reader_position=Structure::where('employe_id',Employe::employeId())->first('position_name')->position_name;
        $cc->save();
    }
}
