<?php

namespace App\Http\Controllers\Vendors;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Vendor\Kbli;
use App\Models\Vendor\ViewKbli;
use App\Models\Vendor\MasterKbli;
use App\Models\Vendor\ViewPerusahaan;
use App\Http\Resources\PostResource;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Exceptions\HttpResponseException;

class KlbiController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api_vendor');
    }

    public function store(Request $request)
    {
        $idKbli = $request->id_kbli;
        $perusahaanId = ViewPerusahaan::where('user_id', Auth::user()->id)->get()->first()->id;
        
        
        $kbliIsExist = Kbli::where(['id_kbli' => $idKbli, 'perusahaan_id' => $perusahaanId])->count() > 0;

        if($kbliIsExist){
            throw new HttpResponseException(response([
                "message" => "The data already exist."
            ], 409));
        }

        $k=new Kbli();
        $k->id_kbli=$idKbli;
        $k->perusahaan_id = $perusahaanId;
        if($k->save()){
            return new PostResource(true, 'New Klbi Inserted', []);
        }else{
            return new PostResource(false, 'Failed to insert', []);
        }
        
    }

    public function myKbli(){
        $data=ViewKbli::where('perusahaan_id',ViewPerusahaan::where('user_id', Auth::user()->id)->get()->first()->id )->get();
        return new PostResource(true, 'My Kbli', $data);
    }
    
    public function list()
    {
        $data = MasterKbli::get();
        $list = [];
        for ($i=0; $i < count($data); $i++) { 
            $llist[$i] = [
                'value' => $data[$i]->id_kbli,
                'label' => $data[$i]->nomor_kbli . " - " .$data[$i]->nama_kbli
            ];
        }

        return new PostResource(true, 'List Kbli', $list);
    }

    public function delete($id)
    {
        $deleted = Kbli::find($id)->delete();

        if($deleted){
            return new PostResource(true, 'Kbli has been deleted.', null);
        }else{
            throw new HttpResponseException(response([
                "message" => "Something went wrong."
            ], 500));
        }
    }
}