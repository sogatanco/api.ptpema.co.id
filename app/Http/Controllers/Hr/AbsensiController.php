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

    // Karyawan aktif yang ditampilkan di dashboard: tanpa komisaris, direktur (id_base 1-4), Sekretaris Komisaris (SEKKOM-01) dan Satpam
    private function dashboardEmployees()
    {
        return DB::table('employees_hr as e')
            ->leftJoin('struktur_lengkap_oke as s', 's.employe_id', '=', 'e.employe_id')
            ->where('e.employe_active', 1)
            ->where(function ($q) {
                $q->whereNull('s.id_base')->orWhereNotIn('s.id_base', [1, 2, 3, 4]);
            })
            ->where('e.employe_id', '<>', 'SEKKOM-01')
            ->where(function ($q) {
                $q->whereNull('s.position_name')->orWhere('s.position_name', '<>', 'Satpam');
            })
            ->select('e.employe_id', 'e.first_name', 's.position_name', 's.organization_name')
            ->get();
    }

    // Statistik kehadiran: per hari (bulan ini) dan per bulan (tahun ini), dalam jumlah karyawan per hari kerja.
    //  tepat_waktu / terlambat : sudah clock in
    //  dengan_keterangan       : tidak clock in, tetapi ada cuti/dinas (disetujui atau menunggu) pada hari itu
    //  tanpa_keterangan        : tidak clock in dan tidak ada keterangan apa pun
    // Hanya hari kerja (Senin-Jumat, bukan libur HR); hari ini baru dihitung setelah pukul 17:00.
    // Seorang karyawan dihitung sejak hari pertama ia clock in.
    public function statistik()
    {
        $now = Carbon::now();
        $key = 'dashboard-absensi-statistik-' . $now->toDateString() . '-' . ($now->hour >= 17 ? 1 : 0);

        $payload = Cache::remember($key, 600, function () use ($now) {
            $hr = config('database.connections.hr.database');
            $upTo = $now->hour >= 17 ? $now->copy()->startOfDay() : $now->copy()->subDay()->startOfDay();
            $yearStart = $now->copy()->startOfYear();
            $yearEnd = $now->copy()->endOfYear();
            $monthStart = $now->copy()->startOfMonth();
            $monthEnd = $now->copy()->endOfMonth();
            $upToS = $upTo->toDateString();

            $ids = $this->dashboardEmployees()->pluck('employe_id')->all();
            $empty = ['tepat_waktu' => null, 'terlambat' => null, 'dengan_keterangan' => null, 'tanpa_keterangan' => null];

            // hari pertama tiap karyawan clock in (sebelum itu tidak dihitung)
            $first = Attandence::whereNotNull('time_in')->whereIn('employe_id', $ids)
                ->selectRaw('employe_id, MIN(date) as first_date')->groupBy('employe_id')->pluck('first_date', 'employe_id')->all();

            // data lama belum punya penanda is_late: sebelum tanggal pertama penanda terisi, terlambat dihitung dari jadwal kerja
            $firstFlag = Attandence::where('is_late', 1)->min('date') ?? '9999-12-31';
            $att = [];
            if ($upTo->gte($yearStart)) {
                $rows = DB::connection('hr')->table('attendances as a')
                    ->leftJoin('schedules as s', 's.id', '=', 'a.schedule_id')
                    ->whereBetween('a.date', [$yearStart->toDateString(), $upToS])
                    ->whereNotNull('a.time_in')
                    ->whereIn('a.employe_id', $ids)
                    ->selectRaw('a.date, a.employe_id, MAX(CASE WHEN a.is_late = 1 OR (a.date < ? AND s.id IS NOT NULL AND a.time_in > ADDTIME(s.time_in, SEC_TO_TIME(s.late_tolerance * 60))) THEN 1 ELSE 0 END) as late', [$firstFlag])
                    ->groupBy('a.date', 'a.employe_id')
                    ->get();
                foreach ($rows as $r) {
                    $att[$r->date][$r->employe_id] = (int) $r->late;
                }
            }

            // cuti/dinas yang mencakup tiap hari
            $leave = [];
            $lrows = DB::table($hr . '.request_time_offs')
                ->whereIn('status', ['approved', 'pending'])
                ->whereDate('date_start', '<=', $upToS)
                ->whereDate('date_end', '>=', $yearStart->toDateString())
                ->select('employe_id', 'date_start', 'date_end', 'excluded_dates')->get();
            foreach ($lrows as $r) {
                $excluded = json_decode($r->excluded_dates ?? '[]', true) ?: [];
                $d = Carbon::parse(max($r->date_start, $yearStart->toDateString()));
                $end = Carbon::parse(min($r->date_end, $upToS));
                for (; $d->lte($end); $d->addDay()) {
                    if (!in_array($d->toDateString(), $excluded, true)) {
                        $leave[$d->toDateString()][$r->employe_id] = true;
                    }
                }
            }

            $holidays = DB::table($hr . '.holidays')->where('is_deleted', 0)
                ->whereBetween('holiday_date', [$yearStart->toDateString(), $yearEnd->toDateString()])
                ->pluck('holiday_date')->flip()->all();

            $monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
            $daily = [];
            $monthly = [];
            for ($m = 1; $m <= 12; $m++) {
                $monthly[$m] = ['bulan' => $monthNames[$m - 1], 'hari_kerja' => 0] + $empty;
            }

            for ($d = $yearStart->copy(); $d->lte($yearEnd); $d->addDay()) {
                $ds = $d->toDateString();
                if ($d->isWeekend() || isset($holidays[$ds])) {
                    continue;
                }
                $inMonth = $d->gte($monthStart) && $d->lte($monthEnd);
                if ($d->gt($upTo)) {
                    if ($inMonth) {
                        $daily[] = ['tanggal' => $ds, 'hari' => (int) $d->format('j')] + $empty;
                    }
                    continue;
                }
                $c = ['tepat_waktu' => 0, 'terlambat' => 0, 'dengan_keterangan' => 0, 'tanpa_keterangan' => 0];
                foreach ($ids as $id) {
                    if (!isset($first[$id]) || $first[$id] > $ds) {
                        continue;
                    }
                    if (isset($att[$ds][$id])) {
                        $c[$att[$ds][$id] ? 'terlambat' : 'tepat_waktu']++;
                    } elseif (isset($leave[$ds][$id])) {
                        $c['dengan_keterangan']++;
                    } else {
                        $c['tanpa_keterangan']++;
                    }
                }
                $m = (int) $d->format('n');
                $monthly[$m]['hari_kerja']++;
                foreach ($c as $k => $v) {
                    $monthly[$m][$k] = ($monthly[$m][$k] ?? 0) + $v;
                }
                if ($inMonth) {
                    $daily[] = ['tanggal' => $ds, 'hari' => (int) $d->format('j')] + $c;
                }
            }

            return [
                'tahun' => (int) $now->format('Y'),
                'bulan' => $monthNames[(int) $now->format('n') - 1] . ' ' . $now->format('Y'),
                'sampai' => $upToS,
                'jumlah_karyawan' => count($ids),
                'harian' => $daily,
                'bulanan' => array_values($monthly),
            ];
        });

        return new PostResource(true, 'data', $payload);
    }

    // Dashboard absensi: karyawan aktif yang sudah clock in hari ini (urut clock in pertama) dan yang belum/tidak hadir
    // beserta keterangan cuti dari HR (approved = disetujui, pending = menunggu, none = tanpa keterangan).
    public function dashboard(Request $request)
    {
        $date = Carbon::now()->toDateString();
        $hr = config('database.connections.hr.database');

        $payload = Cache::remember('dashboard-absensi-' . $date, 20, function () use ($date, $hr) {
            $employees = $this->dashboardEmployees();

            $clock = Attandence::where('date', $date)
                ->whereNotNull('time_in')
                ->selectRaw('employe_id, MIN(time_in) as time_in, MAX(is_late) as is_late')
                ->groupBy('employe_id')
                ->get()
                ->keyBy('employe_id');

            // pengajuan absensi untuk clock in hari ini (pengajuan terlambat): disetujui didahulukan, lalu menunggu, lalu ditolak
            $pengajuan = [];
            $reqRows = DB::table($hr . '.request_attendances as r')
                ->leftJoin($hr . '.unplanned_reasons as u', 'u.id', '=', 'r.unplanned_reason_id')
                ->where('r.date', $date)
                ->where('r.request_type_id', 2)
                ->whereNotNull('r.clock_in')
                ->where('r.clock_in', '<>', '')
                ->whereIn('r.status', ['approved', 'pending', 'rejected'])
                ->select('r.employe_id', 'r.status', 'u.name as alasan')
                ->orderByRaw("FIELD(r.status, 'approved', 'pending', 'rejected')")
                ->get();
            foreach ($reqRows as $r) {
                if (!isset($pengajuan[$r->employe_id])) {
                    $pengajuan[$r->employe_id] = ['status' => $r->status, 'alasan' => $r->alasan];
                }
            }

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
                    $hadir[] = $base + ['clock_in' => substr($c->time_in, 0, 5), 'terlambat' => (bool) $c->is_late, 'pengajuan' => $pengajuan[$e->employe_id] ?? null, '_t' => $c->time_in];
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
