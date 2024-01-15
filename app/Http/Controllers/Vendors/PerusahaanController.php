<?php

namespace App\Http\Controllers\Vendors;

use App\Http\Controllers\Controller;
use App\Models\Vendor\MasterBidangUsaha;
use App\Models\Vendor\Perusahaan;
use App\Models\Vendor\BidangUsaha;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;
use App\Models\Vendor\ViewPerusahaan;
use App\Models\Vendor\Akta;
use App\Models\Vendor\Izin;
use App\Models\Vendor\Jajaran;
use App\Models\Vendor\Kbli;
use App\Models\Vendor\Porto;
use App\Http\Resources\PostResource;
use Illuminate\Support\Facades\Storage;

class PerusahaanController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api_vendor');
    }

    public function listBidangUsaha()
    {
        $data = MasterBidangUsaha::select('id_bidang', 'nama_bidang')->get();

        return response()->json([
            "success" => true,
            "data" => $data
        ]);
    }

    public function statusPerusahaan()
    {
        $userId = Auth::user()->id;

        $companyStatus = Perusahaan::select('status_verifikasi')->where('user_id', $userId)->first();

        return response()->json([
            "status" => true,
            'data' => $companyStatus,
        ], 200);
    }

    public function getDataUmum()
    {
        $user = Auth::user();

        $dataUmum = Perusahaan::select(
                    'perusahaan.nama_perusahaan', 
                    'perusahaan.nomor_registrasi', 
                    'perusahaan.tipe',
                    'perusahaan.bentuk_usaha',
                    'perusahaan.email_alternatif',
                    'perusahaan.no_npwp',
                    'perusahaan.hp',
                    'perusahaan.alamat',
                    'perusahaan.provinsi',
                    'perusahaan.file_npwp',
                    'perusahaan.file_pvd',
                    'master_bidangusaha.id_bidang',
                    'master_bidangusaha.nama_bidang'
                    )
                    ->where('user_id', $user->id)
                    ->leftJoin('bidang_usaha', 'bidang_usaha.perusahaan_id', '=', 'perusahaan.id')
                    ->leftJoin('master_bidangusaha', 'master_bidangusaha.id_bidang', '=', 'bidang_usaha.master_bidangusaha_id')
                    ->first();
                    $dataUmum->npwp_base64=null;
                    $dataUmum->pvd_base64=null;
                    if (file_exists(public_path('vendor_file/' . $dataUmum->file_npwp))) {
                        $dataUmum->npwp_base64= base64_encode(file_get_contents(public_path('vendor_file/' . $dataUmum->file_npwp)));
                    }
                    if (file_exists(public_path('vendor_file/' . $dataUmum->file_pvd))) {
                        $dataUmum->pvd_base64= base64_encode(file_get_contents(public_path('vendor_file/' . $dataUmum->file_pvd)));
                    }     

        return response()->json([
            "success" => true,
            "data" => $dataUmum
        ]);
    }
    
    public function store(Request $request)
    {
        $data = $request->all();
        
        $validator = Validator::make($request->all(), [
            'email_alternatif' => ["required"],
            'no_npwp' => ['required'],
            'id_bidang' => ["required"],
            'hp' => ['required'],
            'alamat' => ['required'],
            'provinsi' => ['required'],
            'file_pvd' => ['required'],
            'file_npwp' => ['required']
        ]);

        if($validator->fails()){
            throw new HttpResponseException(response([
                "errors" => $validator->errors(),
                "message" => "Semua kolom wajib diisi dan tidak boleh ada yang kosong."
            ], 400));
        }

        // upload file pvd
        if($request->file_pvd !== null){
            $file = base64_decode($request->file_pvd, true);
            $filename = ViewPerusahaan::where('user_id', Auth::user()->id)->get()->first()->id .'/pvd.pdf';
            if(Storage::disk('public_vendor')->put($filename, $file)){
                $p=Perusahaan::find(ViewPerusahaan::where('user_id', Auth::user()->id)->get()->first()->id);
                $p->file_pvd=$filename;
                $p->save();
            }else{
                return new PostResource(false, "Upload PVD Gagal", []);
            }
        }

        // upload file npwp
        if($request->file_npwp !== null){
            $file = base64_decode($request->file_npwp, true);
            $filename = ViewPerusahaan::where('user_id', Auth::user()->id)->get()->first()->id .'/npwp.pdf';
            if(Storage::disk('public_vendor')->put($filename, $file)){
                $p=Perusahaan::find(ViewPerusahaan::where('user_id', Auth::user()->id)->get()->first()->id);
                $p->file_npwp=$filename;
                $p->save();
            }else{
                return new PostResource(false, "Upload NPWP Gagal", []);
            }
        }

        $newData = [
            'email_alternatif' => $request->email_alternatif,
            'no_npwp' => $request->no_npwp,
            'hp' => $request->hp,
            'alamat' => $request->alamat,
            'provinsi' => $request->provinsi,
        ];

        $user = Auth::user();
        $company = Perusahaan::where('user_id', $user->id)->first();
        $savedData = Perusahaan::where('user_id', $user->id)->update($newData);

        if($savedData){ 
            // simpan bentuk usaha,
            $bidangUsahaIsCreated = BidangUsaha::where(['perusahaan_id' => $company->id, 'master_bidangusaha_id' => $request->id_bidang])->count() >= 1;

            if(!$bidangUsahaIsCreated){
                BidangUsaha::create(['master_bidangusaha_id' => $request->id_bidang, 'perusahaan_id' => $company->id]);
            };

            return response()->json([
                "status" => true,
                "message" => "Update data success."
            ]);
        }else{
            throw new HttpResponseException(response([
                "message" => "Something went wrong."
            ], 500));
        }
    }
    
    public function submit()
    {
        $status=ViewPerusahaan::where('user_id', Auth::user()->id)->get()->first()->status_verifikasi;
        $p=Perusahaan::find(ViewPerusahaan::where('user_id', Auth::user()->id)->get()->first()->id);
        if($status=='register'){
            $p->status_verifikasi='review_submit';
        }else{
            $p->status_verifikasi='review_update';
        }

        // $statusUpdated = Perusahaan::where('user_id', $idUser)->update(['status_verifikasi' => 'review_submit']);

        if($p->save()){
            return response()->json([
                "status" => true,
                "message" => 'Status has been updated.'
            ], 200);
        }else{
            throw new HttpResponseException(response([
                "message" => "Something went wrong."
            ], 500));
        }
    }

    public function documentStatus()
    {
        $idUser = Auth::user()->id;
        $company = Perusahaan::where('user_id', $idUser)->first();

        $akta = Akta::where('id_perusahaan', $company->id)->count() > 0 ? true : false;
        $izinBerusaha = Izin::where('perusahaan_id', $company->id)->count() > 0 ? true : false;
        $ktp = $company->ktp_pengurus !== '-' ? true : false;
        $skKemenkumham = $company->sk_kemenkumham !== '-' ? true : false;
        $faktaIntegritas = $company->fakta_integritas !== '-' ? true : false;
        $spt = $company->spt !== '-' ? true : false;
        $pph = $company->pph !== '-' ? true : false;
        $lapKeuangan = $company->lap_keuangan !== '-' ? true : false;
        $RekKoran = $company->rek_koran !== '-' ? true : false;

        if($akta && $izinBerusaha && $ktp && $skKemenkumham && $faktaIntegritas && $spt && $pph && $lapKeuangan && $RekKoran ){
            $status = true;
        }else{
            $status = false;
        }

        return response()->json([
            "status" => true,
            "data" => $status,
        ], 200);
    }

    public function jajaranStatus()
    {
        $userId = Auth::user()->id;
        $company = Perusahaan::where('user_id', $userId)->first();

        $jajaran = Jajaran::where('perusahaan_id', $company->id)->count() > 0 ? true : false;
        $struktur = $company->struktur_organisasi !== '-' ? true : false;

        if($jajaran && $struktur){
            $status = true;
        }else{
            $status = false;
        }

        return response()->json([
            "status" => true,
            "data" => $status
        ], 200);
    }

    public function portofolioStatus()
    {
        $userId = Auth::user()->id;
        $company = Perusahaan::where('user_id', $userId)->first();

        $porto = Porto::where('perusahaan_id', $company->id)->count() > 0 ? true : false;

        return response()->json([
            "status" => true,
            "data" => $porto
        ], 200);
    }

    public function bidangUsahaStatus()
    {
        $userId = Auth::user()->id;
        $company = Perusahaan::where('user_id', $userId)->first();

        $status = Kbli::where('perusahaan_id', $company->id)->count() > 0 ? true : false;

        return response()->json([
            "status" => true,
            "data" => $status
        ], 200);
    }

}
