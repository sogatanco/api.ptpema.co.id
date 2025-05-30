<?php

namespace App\Http\Controllers\Vendors;

use App\Http\Controllers\Controller;
use App\Models\Vendor\MasterBidangUsaha;
use App\Models\Vendor\Perusahaan;
use App\Models\Vendor\BidangUsaha;
use Illuminate\Http\Request;
use App\Http\Controllers\Notification\NotificationController;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;
use App\Models\Vendor\ViewPerusahaan;
use App\Models\Vendor\Akta;
use App\Models\Vendor\MasterKbli;
use App\Models\Vendor\Izin;
use App\Models\Vendor\Jajaran;
use App\Models\Vendor\Kbli;
use App\Models\Vendor\Porto;
use App\Models\Vendor\Spda;
use App\Models\User;
use App\Http\Resources\PostResource;
use Illuminate\Support\Facades\Storage;
use ZipArchive;
use  RecursiveIteratorIterator;
use RecursiveDirectoryIterator;


class PerusahaanController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api_vendor', ['except' => ['spdaStatus']]);

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

        $companyStatus = Perusahaan::select('status_verifikasi_umum', 'status_verifikasi_scm')->where('user_id', $userId)->first();

        if($companyStatus->status_verifikasi_umum !== null){
            $status['status_verifikasi'] = $companyStatus->status_verifikasi_umum;
        }else{
            $status['status_verifikasi'] = $companyStatus->status_verifikasi_scm;
        }

        return response()->json([
            "status" => true,
            'data' => $status,
        ], 200);
    }

    public function getDataUmum()
    {
        $user = Auth::user();

        $dataUmum = Perusahaan::select(
            'perusahaan.id',
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
        )
            ->where('user_id', $user->id)
            ->leftJoin('bidang_usaha', 'bidang_usaha.perusahaan_id', '=', 'perusahaan.id')
            ->first();
        
        $bidangUsaha = BidangUsaha::select('master_bidangusaha.id_bidang AS id', 'master_bidangusaha.nama_bidang AS name')
                        ->where('perusahaan_id', $dataUmum->id)
                        ->leftJoin('master_bidangusaha', 'master_bidangusaha.id_bidang', '=', 'bidang_usaha.master_bidangusaha_id')
                        ->get();

        $dataUmum->bidang_usaha = $bidangUsaha->toArray();
        $dataUmum->npwp_base64 = null;
        $dataUmum->pvd_base64 = null;
        if (file_exists(public_path('vendor_file/' . $dataUmum->file_npwp))) {
            // $dataUmum->npwp_base64 = base64_encode(file_get_contents(public_path('vendor_file/' . $dataUmum->file_npwp)));
            $dataUmum->npwp_base64 = '';
        }
        if (file_exists(public_path('vendor_file/' . $dataUmum->file_pvd))) {
            // $dataUmum->pvd_base64 = base64_encode(file_get_contents(public_path('vendor_file/' . $dataUmum->file_pvd)));
            $dataUmum->pvd_base64 = '';
        }

        return response()->json([
            "success" => true,
            "data" => $dataUmum
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email_alternatif' => ["required"],
            'no_npwp' => ['required'],
            'bidangArray' => ["required"],
            'hp' => ['required'],
            'alamat' => ['required'],
            'provinsi' => ['required'],
            // 'filePvd' => ['required'],
            // 'fileNpwp' => ['required']
        ]);

        if ($validator->fails()) {
            throw new HttpResponseException(response([
                "errors" => $validator->errors(),
                "message" => "Semua kolom wajib diisi dan tidak boleh ada yang kosong."
            ], 400));
        }

        // upload file pvd
        if ($request->filePvd) {
            $file = $request->file('filePvd');
            $filename = ViewPerusahaan::where('user_id', Auth::user()->id)->get()->first()->id . '/pvd.pdf';
            if (Storage::disk('public_vendor')->put($filename, file_get_contents($file))) {
                $p = Perusahaan::find(ViewPerusahaan::where('user_id', Auth::user()->id)->get()->first()->id);
                $p->file_pvd = $filename;
                $p->save();
            } else {
                return new PostResource(false, "Upload PVD Gagal", []);
            }
        }

        // upload file npwp
        if ($request->fileNpwp) {
            $file = $request->file('fileNpwp');
            $filename = ViewPerusahaan::where('user_id', Auth::user()->id)->get()->first()->id . '/npwp.pdf';
            if (Storage::disk('public_vendor')->put($filename, file_get_contents($file))) {
                $p = Perusahaan::find(ViewPerusahaan::where('user_id', Auth::user()->id)->get()->first()->id);
                $p->file_npwp = $filename;
                $p->save();
            } else {
                return new PostResource(false, "Upload NPWP Gagal", []);
            }
        }

        $newData = [
            'nama_perusahaan' => $request->nama_perusahaan,
            'tipe' => $request->tipe,
            'bentuk_usaha' => $request->bentuk_usaha,
            'email_alternatif' => $request->email_alternatif,
            'no_npwp' => $request->no_npwp,
            'hp' => $request->hp,
            'alamat' => $request->alamat,
            'provinsi' => $request->provinsi,
        ];

        $user = Auth::user();
        $company = Perusahaan::where('user_id', $user->id)->first();
        $savedData = Perusahaan::where('user_id', $user->id)->update($newData);

        if ($savedData) {
            // simpan bentuk usaha,
            $bidangArray = $request->bidangArray;

            $oldBidang = BidangUSaha::where('perusahaan_id', $company->id)
                        ->get();

            $oldBidangIds = [];
            for ($obi=0; $obi < count($oldBidang); $obi++) { 
                array_push($oldBidangIds, $oldBidang[$obi]->master_bidangusaha_id);
            }

            $bidangArrayIds = [];
            
            for ($b=0; $b < count($bidangArray); $b++) { 

                array_push($bidangArrayIds, $bidangArray[$b]['id']);

                // jika request bidang tidak di db
                // save bidang
                if(!in_array($bidangArray[$b]['id'], $oldBidangIds)){
                    BidangUsaha::create(['master_bidangusaha_id' => $bidangArray[$b]['id'], 'perusahaan_id' => $company->id]);
                }

                // $bidangUsahaIsCreated = BidangUsaha::where(['perusahaan_id' => $company->id, 'master_bidangusaha_id' => $bidangArray[$b]['id']])->count() >= 1;

                // if (!$bidangUsahaIsCreated) {
                //     BidangUsaha::create(['master_bidangusaha_id' => $bidangArray[$b]['id'], 'perusahaan_id' => $company->id]);
                // };
            }

            for ($ob=0; $ob < count($oldBidang); $ob++) { 

               // jika bidang di db tidak ada di request
                // delete bidang    
               if(!in_array($oldBidang[$ob]->master_bidangusaha_id, $bidangArrayIds)){
                    BidangUsaha::where(['perusahaan_id' => $company->id, 'master_bidangusaha_id' => $oldBidang[$ob]->master_bidangusaha_id])
                                ->delete();
               }
            }

            return response()->json([
                "status" => true,
                "message" => "Update data success."
            ]);
        } else {
            throw new HttpResponseException(response([
                "message" => "Something went wrong."
            ], 500));
        }
    }

    public function submit()
    {

        $userId = Auth::user()->id;
        $company = Perusahaan::where('user_id', $userId)->first();
        
        if($company->status_verifikasi_by === 'umum'){
            $company->status_verifikasi_umum = 'review_submit';
            $company->umum_updated_at = date('Y-m-d H:i:s');
            $adminRole = 'AdminVendorUmum';
        }else{
            $company->status_verifikasi_scm = 'review_submit';
            $company->scm_updated_at = date('Y-m-d H:i:s');
            $adminRole = 'AdminVendorScm';
        }

        $savedSubmit = $company->save();

        if ($savedSubmit) {

            // create notification to admin
            $recipient = User::select('employees.employe_id')
                        ->join('employees', 'employees.user_id', '=', 'users.id')
                        ->where('roles', 'like', '%'.$adminRole.'%')
                        ->first()
                        ->employe_id;

            NotificationController::new('VENDOR_REVIEW_SUBMIT', $recipient, $company->id);

            return response()->json([
                "status" => true,
                "message" => 'Status has been updated.'
            ], 200);
        } else {
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

        if ($akta && $izinBerusaha && $ktp && $skKemenkumham && $faktaIntegritas && $spt && $pph && $lapKeuangan && $RekKoran) {
            $status = true;
        } else {
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

        if ($jajaran && $struktur) {
            $status = true;
        } else {
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

    public function spda()
    {

        $data = ViewPerusahaan::where('user_id', Auth::user()->id)->first();

        $spda = Spda::select('spda.*', 'spda.id AS id_spda', 'perusahaan.id AS id_perusahaan', 'perusahaan.bentuk_usaha', 'perusahaan.nama_perusahaan', 'perusahaan.alamat', 'perusahaan.no_npwp','perusahaan.provinsi', 'perusahaan.email_alternatif', 'perusahaan.hp', 'perusahaan.nomor_registrasi')
                ->where('id_perusahaan', $data->id)
                ->join('perusahaan', 'perusahaan.id', '=', 'spda.id_perusahaan')
                ->get();

        if(count($spda) > 0) {
            for ($i=0; $i < count($spda); $i++) { 
                $spda[$i]['key'] = base64_encode($spda[$i]->id_spda);
            }
        }
        
        $bidangUsaha = BidangUsaha::select('nama_bidang')
                    ->where('perusahaan_id', $data->id)
                    ->join('master_bidangusaha', 'master_bidangusaha.id_bidang', '=', 'bidang_usaha.master_bidangusaha_id')
                    ->get();

        $jajaran = Jajaran::select('nama', 'jabatan')
                    ->where('perusahaan_id', $data->id)
                    ->get();

        $akta = Akta::select('id_akta', 'no_akta', 'tgl_terbit', 'jenis')
                ->where('id_perusahaan', $data->id)->get();

        $kbli = Kbli::select('id', 'nomor_kbli', 'nama_kbli')
                ->where('perusahaan_id', $data->id)
                ->join('master_kbli', 'master_kbli.id_kbli', '=', 'kbli.id_kbli')
                ->get();

        $nib = Izin::select('id_izin','nomor', 'tgl_terbit', 'tgl_berakhir', 'keterangan')
                ->where('perusahaan_id', $data->id)->get();

        $data = [
            'spda' => $spda,
            'data' => [
                'bidangUsaha' => $bidangUsaha,
                'jajaran' => $jajaran,
                'akta' => $akta,
                'kbli' => $kbli,
                'nib' => $nib
            ]
        ];

        // $direksi = [];
        // foreach (Jajaran::where('perusahaan_id', $data->id)->get() as $d) {
        //     array_push($direksi, $d->nama);
        // }
        // $f['nama_perusahaan'] = $data->bentuk_usaha . ' ' . $data->nama_perusahaan;
        // $f['nomor_registrasi'] = $data->nomor_registrasi;
        // $f['alamat'] = $data->alamat;
        // $f['telepon'] = $data->telepon;
        // $f['email'] = $data->email;
        // $f['email_alternatif'] = $data->email_alternatif;
        // $f['nomor_npwp'] = $data->no_npwp;
        // $f['dewan_direksi'] = implode("; ", $direksi);
        // $f['izin'] = Izin::where('perusahaan_id', $data->id)->get();
        // $f['akta'] = Akta::where('id_perusahaan', $data->id)->get();
        // $f['portofolio'] = Porto::where('perusahaan_id', $data->id)->latest()->get();
        // $kbli = Kbli::where('perusahaan_id', $data->id)->get();
        // $d = [];
        // for ($i = 0; $i < count($kbli); $i++) {
        //     $d[$i]['nomor_kbli'] = MasterKbli::where('id_kbli', $kbli[$i]->id_kbli)->first()->nomor_kbli;
        //     $d[$i]['judul_kbli'] = MasterKbli::where('id_kbli', $kbli[$i]->id_kbli)->first()->nama_kbli;
        // }
        // $f['kbli'] = $d;

        return response()->json([
            "status" => true,
            "data" => $data
        ], 200);
    }

    
    public function spdaStatus($spdaId)
    {
        $spdaIdDecode = base64_decode($spdaId);

        $data = null;
        if($spdaIdDecode) {
            $data = Spda::select('spda.*', 'perusahaan.bentuk_usaha', 'perusahaan.nama_perusahaan')
                ->where('spda.id', $spdaIdDecode)
                ->join('perusahaan', 'perusahaan.id', '=', 'spda.id_perusahaan')
                ->first();
        }
        
        return response()->json([
            "status" => true,
            "data" => $data
        ], 200);
    }

    public function downloadzip()
    {
        $dir = public_path('vendor_file/'. ViewPerusahaan::where('user_id', Auth::user()->id)->first()->id);

        // Initialize archive object
        $zip = new ZipArchive();
        $zip_name = time() . ".zip"; // Zip name
        $zip->open($zip_name, ZipArchive::CREATE);

        // Create recursive directory iterator
        /** @var SplFileInfo[] $files */
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir), RecursiveIteratorIterator::LEAVES_ONLY);

        foreach ($files as $name => $file) {
            // Skip directories (they would be added automatically)
            if (!$file->isDir()) {
                // Get real and relative path for current file
                $filePath = $file->getRealPath();
                $relativePath = substr($filePath, strlen($dir) + 1);

                // Add current file to archive
                $zip->addFile($filePath, $relativePath);
            }
        }

        $zip->close();

        //then prompt user to download the zip file
        header('Content-Type: application/zip');
        header('Content-disposition: attachment; filename=' . $zip_name);
        header('Content-Length: ' . filesize($zip_name));
        readfile($zip_name);

        //cleanup the zip file
        unlink($zip_name);
    }

    public function kbliList()
    {
        $userId = Auth::user()->id;

        $company = Perusahaan::select('id')->where('user_id', $userId)->first();

        $kblis = Kbli::select('master_kbli.nomor_kbli')
                    ->leftJoin('master_kbli', 'master_kbli.id_kbli', '=', 'kbli.id_kbli')
                    ->where('perusahaan_id', $company->id)
                    ->get();
        
        $kbliArray = [];
        for ($k=0; $k < count($kblis); $k++) { 
            array_push($kbliArray, $kblis[$k]->nomor_kbli);
        }

        return response()->json([ 
            'status' => true,
            'data' => $kbliArray
        ], 200);
    }
}
