<?php

namespace App\Http\Controllers\Vendors\Admin;

use App\Http\Controllers\Controller;
use App\Models\Vendor\Perusahaan;
use App\Models\Vendor\ViewPerusahaan;
use App\Models\Vendor\Jajaran;
use App\Models\Employe;
use App\Models\Vendor\Akta;
use App\Models\Vendor\MasterKbli;
use App\Models\Vendor\Spda;
use App\Models\Vendor\Izin;
use App\Models\Vendor\Porto;
use App\Models\Vendor\Log;
use App\Models\Vendor\ViewKbli;
use App\Models\Vendor\Verifikasi;
use App\Http\Resources\PostResource;
use App\Mail\InfoToVendor;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Mail;
use PDO;



class APerusahaanController extends Controller
{
    function index()
    {
        $data = ViewPerusahaan::orderBy('id', 'desc')->get();
        return new PostResource(true, 'list data Perusahaan', $data);
    }

    function show($id)
    {
        $data = ViewPerusahaan::where('id', $id)->first();
        return new PostResource(true, 'Data Perusahaan ' . $id, $data);
    }

    public function requestList()
    {

        $userRoles = Auth::user()->roles;

        if(in_array('AdminVendorUmum', $userRoles)){
            $field = 'status_verifikasi_umum';
        }else{
            $field = 'status_verifikasi_scm';
        }
        
        $data = Perusahaan::select('perusahaan.*', 'perusahaan.id AS perusahaan_id', $field. ' AS status_verifikasi_admin', 'users.*', 'users.id as user_id')
            ->join('users', 'users.id', '=', 'perusahaan.user_id')
            ->where($field, 'review_submit')
            ->orWhere($field, 'review_update')
            ->get();

        return new PostResource(true, 'Request data', $data);
    }

    public function listDataUmum($companyId)
    {
        $data = ViewPerusahaan::where('id', $companyId)->first();

        if($data == null){
            throw new HttpResponseException(response([
                "message" => "data not found"
            ], 404));
        }

        return new PostResource(true, 'List data umum', $data);
    }

    public function listJajaran($companyId)
    {
        $data['jajaran'] = Jajaran::where('perusahaan_id', $companyId)->get();

        $data['file_struktur'] = ViewPerusahaan::where('id', $companyId)->get()->first()->struktur_organisasi;

        return new PostResource(true, 'List jajaran', $data);
    }

    public function listAkta($companyId)
    {
        $listAkta = Akta::where('id_perusahaan', $companyId)->get();

        return new PostResource(true, 'List akta', $listAkta);
    }

    public function listIzin($companyId)
    {
        $listIzin = Izin::where('perusahaan_id', $companyId)->get();

        return new PostResource(true, 'List izin', $listIzin);
    }

    public function listDokumen($companyId)
    {
        $list = [
            'company_profile',
            'ktp_pengurus',
            'sk_kemenkumham',
            'fakta_integritas',
            'spt',
            'pph',
            "lap_keuangan",
            'rek_koran',
        ];

        $data = Perusahaan::select($list)->where('id', $companyId)->first();

        return new PostResource(true, 'List dokumen', $data);
    }

    public function listPortofolio($companyId)
    {
        $data = Porto::where('perusahaan_id', $companyId)->get();

        return new PostResource(true, 'List portofolio', $data);
    }

    public function listKbli($companyId)
    {
        $data = ViewKbli::where('perusahaan_id', $companyId)->get();
        return new PostResource(true, 'List kbli', $data);
    }

    public function updateStatus(Request $request, $companyId)
    {

        $status = $request->query('val');
        $updated = Perusahaan::where('id', $companyId)->update(['status_verifikasi' => $status]);

        if ($updated) {
            return new PostResource(true, 'Status updated successfully', []);
        } else {
            throw new HttpResponseException(response([
                "message" => "Something went wrong."
            ], 500));
        }
    }

    public function sendEmail(Request $request)
    {
        $mailData = [
            'subject' => $request->subject,
            'content' => $request->content
        ];
        if (Mail::to($request->email)->send(new InfoToVendor($mailData))) {
            return new PostResource(true, 'Sended', $mailData);
        }
    }

    public function verif($id, Request $request)
    {
        $p = Perusahaan::find($id);

        $userRoles = Auth::user()->roles;

        if(in_array('AdminVendorUmum', $userRoles)){
            $p->status_verifikasi_umum = $request->status;
            $p->umum_updated_at = date('Y-m-d H:i:s');
            $p->status_verifikasi_by = 'umum';
        }else{
            $p->status_verifikasi_scm = $request->status;
            $p->scm_updated_at = date('Y-m-d H:i:s');
            $p->status_verifikasi_by = 'scm';
        }

        if ($p->save()) {
            $v = new Verifikasi();
            $v->id_perusahaan = $id;
            $v->status_verifikasi = $request->status;
            $v->reviewer = Employe::employeId();
            $v->komentar = $request->komentar;
            if ($v->save()) {
                if ($request->status == 'terverifikasi') {
                    $mailData = [
                        'subject' => 'SELAMAT',
                        'content' => 'Perusahaan anda terverifikasi. '
                    ];

                    // check spda
                    $spda = Spda::where('spda.id_perusahaan', $id)
                            ->where('spda.status', 1)
                            ->first();

                    $nomorSpda = 'SPDA/'.$p->nomor_registrasi.'/';

                    if(!$spda){

                        // save spda
                        $newSpda = new Spda();
                        $newSpda->id_perusahaan = $id;
                        $newSpda->nomor = $nomorSpda.'1';
                        $newSpda->mulai_berlaku = date('Y-m-d');
                        $newSpda->berakhir =  date('Y-m-d', strtotime('+2 years'));
                        $newSpda->status = 1;
                        $newSpda->save();

                    }elseif($spda->berakhir < date("Y-m-d")){

                        // update old spda
                        $spda->status = 0;
                        $spda->save();

                        $countSpda = Spda::where('id_perusahaan', $id)
                                    ->count();

                        // save spda
                        $newSpda = new Spda();
                        $newSpda->id_perusahaan = $id;
                        $newSpda->nomor = $nomorSpda.$countSpda+1;
                        $newSpda->mulai_berlaku = date('Y-m-d');
                        $newSpda->berakhir =  date('Y-m-d', strtotime('+5 years'));
                        $newSpda->status = 1;
                        $newSpda->save();
                    }

                }else{
                    $mailData = [
                        'subject' => 'BELUM TERVERIFIKASI',
                        'content' => $request->komentar
                    ];
                }
                
                $em=ViewPerusahaan::where('id', $id)->first()->email;
                if (Mail::to($em)->send(new InfoToVendor($mailData))) {
                    return new PostResource(true, 'Updated', []);
                }
            }
        }
    }
    public function getLog($id){
        $log=Log::where('perusahaan_id', $id)->latest()->get();
        return new PostResource(true, 'Activities', $log);
    }
// list semua kbli
    public function list()
    {
        $data = MasterKbli::get();
        $list = [];
        for ($i=0; $i < count($data); $i++) { 
            $list[$i] = [
                'value' => $data[$i]->nomor_kbli,
                'label' => $data[$i]->nomor_kbli . " - " .$data[$i]->nama_kbli
            ];
        }

        array_push($list, [
            'value' => 'all_kbli',
            'label' => 'SEMUA KBLI'
        ]);

        return new PostResource(true, 'List Kbli', $list);
    }

    public function companiesToInvite(Request $request)
    {
        $query = $request->query('type');
        $list = Perusahaan::select('id', 'bentuk_usaha', 'nama_perusahaan', 'status_verifikasi_umum', 'status_verifikasi_scm')
                ->where('tipe', $query)->get();

        $userRoles = Auth::user()->roles;

        $data = [];
        for ($i=0; $i < count($list); $i++) { 
            $data[$i] = [
                'value' => $list[$i]->id,
                'label' => $list[$i]->bentuk_usaha. " " .$list[$i]->nama_perusahaan,
                'isApproved' => in_array('AdminVendorUmum', $userRoles) ? $list[$i]->status_verifikasi_umum === 'terverifikasi' : $list[$i]->status_verifikasi_scm === 'terverifikasi',
            ];
        }

        return response()->json([
            'status' => true,
            'total' => count($data),
            'type' => $query,
            'data' => $data
        ], 200);
    }

    public function getCompanyStatus($id)
    {
        $company = Perusahaan::select('status_verifikasi_umum', 'status_verifikasi_scm', 'umum_updated_at', 'scm_updated_at')->where('id', $id)->first();

        return response()->json([
            'status' => true,
            'data' => $company
        ], 200);
    }

}
