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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

use Carbon\Carbon;

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
                        'date'=>now()->toDateString(),],[
                        'schedule_id' => (Carbon::now()->isFriday()) ? 8 : 7,
                        'type'=>'normal',
                        'time_in'=>date('H:i:s'),
                        'latlong_in'=>$request->latlong,
                        'created_at' => DB::raw('IFNULL(created_at, NOW())'), // Jika belum ada, isi dengan NOW()
                        'updated_at' => now() // Selalu di-update

                    ]);
                }else if($request->jenis == 'out'){
                    Attandence::updateOrInsert([
                        'employe_id' => Employe::employeId(),
                        'date'=>now()->toDateString()],[

                        'schedule_id' => (Carbon::now()->isFriday()) ? 8 : 7,
                        'type'=>'normal',
                        'time_out'=>date('H:i:s'),
                        'latlong_out'=>$request->latlong,
                        'created_at' => DB::raw('IFNULL(created_at, NOW())'), // Jika belum ada, isi dengan NOW()
                        'updated_at' => now() // Selalu di-update
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
        $landing['office']=Offices::find($idOffice);
        $landing['profil']=$this->getImage(Employe::employeId());
        $landing['absen']=Attandence::where('employe_id', Employe::employeId())->where('date', now()->toDateString())->first();
        return new PostResource(true, 'data', $landing);
    }

    // Dashboard absensi: karyawan aktif yang sudah clock in hari ini (urut clock in pertama) dan yang belum/tidak hadir
    // beserta keterangan cuti dari HR (approved = disetujui, pending = menunggu, none = tanpa keterangan).
    public function dashboard(Request $request)
    {
        $date = Carbon::now()->toDateString();
        $hr = config('database.connections.hr.database');

        $payload = Cache::remember('dashboard-absensi-' . $date, 20, function () use ($date, $hr) {
            $employees = DB::table('employees_hr as e')
                ->leftJoin('struktur_lengkap_oke as s', 's.employe_id', '=', 'e.employe_id')
                ->where('e.employe_active', 1)
                // komisaris, direktur (id_base 1-4), Sekretaris Komisaris (SEKKOM-01) dan Satpam tidak ditampilkan
                ->where(function ($q) {
                    $q->whereNull('s.id_base')->orWhereNotIn('s.id_base', [1, 2, 3, 4]);
                })
                ->where('e.employe_id', '<>', 'SEKKOM-01')
                // jabatan Satpam tidak ditampilkan
                ->where(function ($q) {
                    $q->whereNull('s.position_name')->orWhere('s.position_name', '<>', 'Satpam');
                })
                ->select('e.employe_id', 'e.first_name', 's.position_name', 's.organization_name')
                ->get();

            $clock = Attandence::where('date', $date)
                ->whereNotNull('time_in')
                ->selectRaw('employe_id, MIN(time_in) as time_in, MAX(is_late) as is_late')
                ->groupBy('employe_id')
                ->get()
                ->keyBy('employe_id');

            // cuti yang mencakup hari ini (approved didahulukan dari pending)
            $leaves = [];
            $rows = DB::table($hr . '.request_time_offs as r')
                ->join($hr . '.time_off_masters as m', 'm.id', '=', 'r.master_id')
                ->leftJoin($hr . '.time_off_sub_masters as sm', 'sm.id', '=', 'r.sub_master_id')
                ->whereIn('r.status', ['approved', 'pending'])
                ->whereDate('r.date_start', '<=', $date)
                ->whereDate('r.date_end', '>=', $date)
                ->select('r.employe_id', 'r.status', 'r.excluded_dates', 'm.type as jenis', 'sm.name as sub')
                ->orderByRaw("r.status = 'approved' DESC")
                ->get();
            foreach ($rows as $r) {
                $excluded = json_decode($r->excluded_dates ?? '[]', true) ?: [];
                if (in_array($date, $excluded, true) || isset($leaves[$r->employe_id])) {
                    continue;
                }
                $leaves[$r->employe_id] = ['status' => $r->status, 'jenis' => trim($r->jenis . ($r->sub ? ' - ' . $r->sub : ''))];
            }

            // hari libur = Sabtu/Minggu atau tercatat di tabel holidays HR
            $liburKet = function ($d) use ($hr) {
                $holiday = DB::table($hr . '.holidays')->where('holiday_date', $d)->where('is_deleted', 0)->value('description');
                if ($holiday !== null) {
                    return $holiday !== '' ? $holiday : 'Hari libur';
                }
                return Carbon::parse($d)->isWeekend() ? 'Akhir pekan' : null;
            };

            // karyawan yang kemarin sudah clock in tetapi belum clock out; tidak ditampilkan bila kemarin hari libur
            $kemarin = Carbon::parse($date)->subDay()->toDateString();
            $noOut = collect();
            if ($liburKet($kemarin) === null) {
                $noOut = Attandence::where('date', $kemarin)
                    ->whereNotNull('time_in')
                    ->whereNull('time_out')
                    ->selectRaw('employe_id, MIN(time_in) as time_in')
                    ->groupBy('employe_id')
                    ->get()
                    ->keyBy('employe_id');
            }

            $liburHariIni = $liburKet($date);
            $libur = $liburHariIni !== null;

            $hadir = [];
            $tidakHadir = [];
            $belumOut = [];
            foreach ($employees as $e) {
                $base = [
                    'employe_id' => $e->employe_id,
                    'nama' => $e->first_name,
                    'jabatan' => $e->position_name,
                    'divisi' => $e->organization_name,
                ];
                if (isset($noOut[$e->employe_id])) {
                    $belumOut[] = $base + ['clock_in' => substr($noOut[$e->employe_id]->time_in, 0, 5), '_t' => $noOut[$e->employe_id]->time_in];
                }
                if (isset($clock[$e->employe_id])) {
                    $c = $clock[$e->employe_id];
                    $hadir[] = $base + ['clock_in' => substr($c->time_in, 0, 5), 'terlambat' => (bool) $c->is_late, '_t' => $c->time_in];
                } elseif (!$libur) {
                    $l = $leaves[$e->employe_id] ?? null;
                    $tidakHadir[] = $base + [
                        'status' => $l['status'] ?? 'none',
                        'jenis' => $l['jenis'] ?? null,
                    ];
                }
            }

            usort($hadir, fn ($a, $b) => strcmp($a['_t'], $b['_t']) ?: strcmp($a['nama'], $b['nama']));
            $hadir = array_map(function ($h) { unset($h['_t']); return $h; }, $hadir);

            usort($belumOut, fn ($a, $b) => strcmp($a['_t'], $b['_t']) ?: strcmp($a['nama'], $b['nama']));
            $belumOut = array_map(function ($h) { unset($h['_t']); return $h; }, $belumOut);

            $order = ['approved' => 0, 'pending' => 1, 'none' => 2];
            usort($tidakHadir, fn ($a, $b) => ($order[$a['status']] <=> $order[$b['status']]) ?: strcmp($a['nama'], $b['nama']));

            return [
                'tanggal' => $date,
                'diperbarui' => Carbon::now()->format('H:i:s'),
                'libur' => $liburHariIni,
                'ringkasan' => [
                    'total' => count($employees),
                    'hadir' => count($hadir),
                    'tidak_hadir' => count($tidakHadir),
                    'cuti_disetujui' => count(array_filter($tidakHadir, fn ($x) => $x['status'] === 'approved')),
                    'cuti_menunggu' => count(array_filter($tidakHadir, fn ($x) => $x['status'] === 'pending')),
                    'tanpa_keterangan' => count(array_filter($tidakHadir, fn ($x) => $x['status'] === 'none')),
                    'belum_clock_out_kemarin' => count($belumOut),
                ],
                'hadir' => $hadir,
                'tidak_hadir' => $tidakHadir,
                'tanggal_kemarin' => $kemarin,
                'belum_clock_out' => $belumOut,
            ];
        });

        return new PostResource(true, 'data', $payload);
    }
}
