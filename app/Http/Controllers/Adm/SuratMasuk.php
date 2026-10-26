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
    }

    public function getSM($what)
    {
        $data = [];
        if ($what == 'to_me') {
            $data = ListSuratMasuk::where('live_receiver', Employe::employeId())->whereNull('tindak_lanjut')->latest('diterima')->get();
            $counts = $this->disposisiTotals($data->pluck('id_surat'));
            foreach ($data as $d) {
                $d->diproses = $counts->get($d->id_surat) ?? 0;
            }
        } else if ($what == 'tinjut') {
            $data = ListSuratMasuk::where('live_receiver', Employe::employeId())->whereNotNull('tindak_lanjut')->latest('ditinjut')->get();
            $counts = $this->disposisiTotals($data->pluck('id_surat'));
            foreach ($data as $d) {
                $d->diproses = $counts->get($d->id_surat) ?? 0;
            }
        } else if ($what == 'all') {
            $data = SM::latest('created_at')->get();

            // Looked up position/employee name and disposisi status per row (2-5 queries each);
            // the "all" list runs for every surat masuk ever created, so this took minutes to load.
            // Batched below into a handful of queries regardless of row count.
            $positionIds = $data->pluck('id_direksi')->filter()->unique();
            $employeIds = $data->pluck('insert_by')->filter()->unique();
            $byPosition = Structure::whereIn('position_id', $positionIds)->get()->unique('position_id')->keyBy('position_id');
            $byEmploye = Structure::whereIn('employe_id', $employeIds)->get()->unique('employe_id')->keyBy('employe_id');

            $dispoStats = Disposisi::whereIn('id_surat', $data->pluck('id'))
                ->selectRaw('id_surat, count(*) as total, sum(case when tindak_lanjut is null then 1 else 0 end) as null_total')
                ->groupBy('id_surat')
                ->get()
                ->keyBy('id_surat');

            foreach ($data as $d) {
                $d->nama_dir = $byPosition->get($d->id_direksi)->first_name ?? '-';
                $d->by_name = $byEmploye->get($d->insert_by)->first_name ?? '-';

                $stat = $dispoStats->get($d->id);
                $total = $stat->total ?? 0;
                $nullTotal = $stat->null_total ?? 0;
                // Same rule as before: a single disposisi already followed up counts as 2 steps,
                // a single disposisi still pending counts as 1, otherwise the raw count is used.
                $d->diproses = $total == 1 ? ($nullTotal < 1 ? 2 : 1) : $total;
            }
        }else if ($what == 'cc') {
            $data=ListCC::where('live_cc', Employe::employeId())->latest('cc_at')->get();
        }

        return new PostResource(true, 'data Surat Masuk', $data);
    }

    // Total disposisi rows per surat, batched for a whole list in one query
    private function disposisiTotals($suratIds)
    {
        return Disposisi::whereIn('id_surat', $suratIds)
            ->selectRaw('id_surat, count(*) as total')
            ->groupBy('id_surat')
            ->get()
            ->pluck('total', 'id_surat');
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
        $dt=SM::find($id);
        $collection = collect();
        foreach ($riwayat as $r) {
            $cc=[];

            $data=CC::where('id_dispo', $r->id)->get();
            foreach ($data as $c) {
                array_push($cc, ($c->cc_to=='position'?Position::where('position_id',$c->id_penerima)->first('position_name')->position_name:Division::where('organization_id',$c->id_penerima)->first('organization_name')->organization_name));
            }

           if($r->tindak_lanjut== 'tinjut'){

            $collection->push([
                'detail'=>$dt,
                'tickler'=>$r->tickler,
                'catatan'=>$r->catatan,
                'employe_id' => $r->employe_id,
                'position' => $r->position,
                'dispo'=>true,
                'to'=>$r->dispo_to=='position'?Position::where('position_id',$r->id_penerima)->first('position_name')->position_name:Division::where('organization_id',$r->id_penerima)->first('organization_name')->organization_name,
                'nama' => Employe::where('employe_id', $r->employe_id)->first('first_name')->first_name,
                'activity' =>  str_contains($r->position, 'Administrator')?'Dokumen disampaikan kepada '.($r->dispo_to=='position'?Position::where('position_id',$r->id_penerima)->first('position_name')->position_name:'')

                :

                'Dokumen didisposisikan kepada '.($r->dispo_to=='position'?Position::where('position_id',$r->id_penerima)->first('position_name')->position_name:Division::where('organization_id',$r->id_penerima)->first('organization_name')->organization_name),
                'cc'=>$cc,
                'waktu'=>$r->created_at,
            ]);
            $collection->push([
                'tickler'=>$r->tickler,
                'catatan'=>$r->catatan,
                'detail'=>$dt,
                'employe_id' => $r->tinjut_by,
                'dispo'=>false,
                'position' => $r->tinjut_by_position,
                'nama' => Employe::where('employe_id', $r->tinjut_by)->first('first_name')->first_name,
                'activity' => 'Surat ditindaklanjuti dan tidak didisposisi',
                'waktu'=>$r->updated_at,
            ]);

           }else{
            $collection->push([
                'tickler'=>$r->tickler,
                'catatan'=>$r->catatan,
                'detail'=>$dt,
                'employe_id' => $r->employe_id,
                'dispo'=>true,
                'to'=>$r->dispo_to=='position'?Position::where('position_id',$r->id_penerima)->first('position_name')->position_name:Division::where('organization_id',$r->id_penerima)->first('organization_name')->organization_name,
                'position' => $r->position,
                'nama' => Employe::where('employe_id', $r->employe_id)->first('first_name')->first_name,
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
