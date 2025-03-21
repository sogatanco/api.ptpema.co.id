<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Http\Resources\PostResource;
use App\Models\Employe;
use App\Models\Hr\Attandence;
use App\Models\Hr\Offices;
use App\Models\Hr\Profil;
use App\Models\Hr\Schedule;
use App\Models\Hr\Users;
use Illuminate\Http\Request;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class AbsensiController extends Controller
{
    public function clock_in(Request $request)
    {
        $client = new Client();

        $formData = [
            'api_key' => 'v9kCmXvvpcb15_QdXr5cVWnq8GWgHCsP',
            'api_secret' => '5RFVIMNXw1KwHP5dF08cM3GWErUZIckL',
            'image_url1' => $this->getImage(Employe::employeId()),
            'image_base64_2' => $request->poto,
        ];

        try {
            $response = $client->request('POST', 'https://api-us.faceplusplus.com/facepp/v3/compare', [
                'form_params' => $formData
            ]);

            $result = json_decode($response->getBody(), true);
            // cek confidence
            if (isset($result['confidence']) && $result['confidence'] > 80) {
                if($request->jenis == 'in'){
                    Attandence::updateOrInsert([
                        'employe_id' => Employe::employeId(),
                        'schedule_id' => Schedule::where('active', 1)->get()->first()->id,
                        'type'=>'normal',
                        'date'=>now()->toDateString(),
                        'time_in'=>date('H:i:s'),
                        'latlong_in'=>$request->latlong
    
                    ]);
                }else if($request->jenis == 'out'){
                    Attandence::updateOrInsert([
                        'employe_id' => Employe::employeId(),
                    ]);
                }
                
                return new PostResource(true, 'Clock '.$request->jenis.' Berhasil !', $result['confidence']);
            } else {
                return new PostResource(false, 'Wajah Tidak Sesuai', $result['confidence'] ?? null);
            }
        } catch (RequestException $e) {
            return response()->json([
                'error' => 'Request error: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getImage($employe_id)
    {
        $p = Profil::where('employe_id', $employe_id)->get()->first();
        $data = 'https://hr-api.ptpema.co.id/storage/photo/employee-photo/' . $p->photo;
        return $data;
    }

    public function getOffice(){
        $idOffice=Users::where('employe_id', Employe::employeId())->get()->first()->office_id;
        $office=Offices::find($idOffice);
        return new PostResource(true, 'data office', $office);
    }
}
