<?php

namespace App\Http\Controllers\MobilOp;

use App\Http\Controllers\Controller;
use App\Http\Resources\PostResource;
use App\Models\Employe;
use App\Models\Mobil\Bbm;
use App\Models\Mobil\Mobil;
use Illuminate\Http\Request;
use App\Models\Mobil\Permintaan;
use App\Models\Mobil\Pengambilan;
use Carbon\Carbon;

class MobilController extends Controller
{
    public function insert(Request $request)
    {
        $mobil = new Mobil();

        $mobil->brand = $request->brand;
        $mobil->plat = $request->plat;
        $mobil->status = $request->status;

        if ($mobil->save()) {
            return new PostResource(true, 'success', []);
        }
    }

    public function getMobil()
    {
        $data = Mobil::where('deleted', 0)->get();
        return new PostResource(true, 'success', $data);
    }

    public function delete($id)
    {
        $mobil = Mobil::find($id);
        if ($mobil) {
            $mobil->deleted = 1;
            $mobil->save();
            return new PostResource(true, 'success', []);
        } else {
            return new PostResource(false, 'Mobil not found', []);
        }
    }

    public function update(Request $request, $id)
    {
        $mobil = Mobil::find($id);
        if ($mobil) {
            $mobil->status = $request->status;
            if ($mobil->save()) {
                return new PostResource(true, 'success', []);
            }
        } else {
            return new PostResource(false, 'Mobil not found', []);
        }
    }

    public function insertPermintaan(Request $request)
    {
        $permintaan = new Permintaan();
        $permintaan->keperluan = $request->keperluan;
        $permintaan->created_by = Employe::employeId();
        $permintaan->mulai = $request->dari;
        $permintaan->hingga = $request->sampai;
        $permintaan->sopir = $request->perluSopir;
        if ($permintaan->save()) {
            return new PostResource(true, 'success', []);
        }

    }

    public function getPermintaan()
    {
        $data = Permintaan::where('created_by', Employe::employeId())
            ->where('deleted_at', null)
            ->orderBy('created_at', 'desc')
            ->get();
        foreach ($data as $item) {
            $item->created_by_name = Employe::where('employe_id', $item->created_by)->first('first_name')->first_name;
        }
        return new PostResource(true, 'success', $data);
    }

    public function deletePermintaan($id)
    {
        $permintaan = Permintaan::find($id);
        if ($permintaan) {
            $permintaan->deleted_at = Carbon::now();
            if ($permintaan->save()) {
                return new PostResource(true, 'Permintaan deleted successfully', []);
            }
        }
        return new PostResource(false, 'Permintaan not found', []);
    }

    public function getPermintaanByStatus()
    {
        $today = Carbon::today();
        $data = Permintaan::where('status', 1)
                          ->where('mulai', '>=', $today)
                          ->where('deleted_at', null)
                          ->orderBy('mulai', 'asc')
                          ->get();
        foreach ($data as $item) {
            $item->created_by_name = Employe::where('employe_id', $item->created_by)->first('first_name')->first_name;
        }
        return new PostResource(true, 'success', $data);
    }

    public function getPermintaanAll()
    {
        $data = Permintaan::where('status', null)
            ->where('deleted_at', null)
            ->orderBy('created_at', 'desc')
            ->get();
        foreach ($data as $item) {
            $item->created_by_name = Employe::where('employe_id', $item->created_by)->first('first_name')->first_name;
        }
        return new PostResource(true, 'success', $data);
    }

     public function getPermintaanAfter()
    {
        $data = Permintaan::whereNotNull('status')
            ->where('deleted_at', null)
            ->orderBy('created_at', 'desc')
            ->get();
        foreach ($data as $item) {
            $item->review_by_name = Employe::where('employe_id', $item->review_by)->first('first_name')->first_name;
            $item->created_by_name = Employe::where('employe_id', $item->created_by)->first('first_name')->first_name;
        }
        return new PostResource(true, 'success', $data);
    }

    public function insertPengambilan(Request $request) {
        $pengambilan = new Pengambilan();

        if (!empty($request->booked)) {
            $permintaan = Permintaan::find($request->booked);
           
            if ($permintaan) {
                $pengambilan->employe_id = $permintaan->created_by;
                $pengambilan->keperluan = $permintaan->keperluan;
                $pengambilan->pengembalian = $permintaan->hingga;

                $permintaan->deleted_at = Carbon::now();
                $permintaan->save();
            }
        } else {
            $pengambilan->employe_id = $request->employe_id;
            $pengambilan->keperluan = $request->keperluan;
            $pengambilan->pengembalian = $request->pengembalian;
        }
        
        $pengambilan->id_mobil = $request->id_mobil;
        $pengambilan->booked = $request->booked;

        if ($pengambilan->save()) {
            return new PostResource(true, 'Pengambilan inserted successfully', []);
        }
        return new PostResource(false, 'Failed to insert Pengambilan', []);
    }

    public function getPengambilan()
    {
        $data = Pengambilan::where('deleted_at', null)
            ->orderBy('created_at', 'desc')
            ->get();
        foreach ($data as $item) {
            $item->employe_name = Employe::where('employe_id', $item->employe_id)->first('first_name')->first_name;
            $item->brand= Mobil::where('id', $item->id_mobil)->first('brand')->brand;
            $item->plat= Mobil::where('id', $item->id_mobil)->first('plat')->plat;
        }
        return new PostResource(true, 'success', $data);
    }

    public function pengembalian(Request $request, $id)
    {
        $pengambilan = Pengambilan::find($id);
        if ($pengambilan) {
            $pengambilan->real_pengembalian = Carbon::now();
            $pengambilan->last_km = $request->last_km;
            if ($pengambilan->save()) {
                return new PostResource(true, 'Pengembalian updated successfully', []);
            }
            return new PostResource(false, 'Failed to update Pengembalian', []);
        }
        return new PostResource(false, 'Pengambilan not found', []);
    }

    public function approvePermintaan($id)
    {
        $permintaan = Permintaan::find($id);
        if ($permintaan) {
            $permintaan->status = 1; 
            $permintaan->review_by= Employe::employeId(); // Approved
            if ($permintaan->save()) {
                return new PostResource(true, 'Permintaan approved successfully', []);
            }
            return new PostResource(false, 'Failed to approve Permintaan', []);
        }
        return new PostResource(false, 'Permintaan not found', []);
    }

    public function rejectPermintaan(Request $request, $id)
    {
        $permintaan = Permintaan::find($id);
        if ($permintaan) {
            $permintaan->status = 0; // Rejected
            $permintaan->ket = $request->ket; // Insert rejection reason
            $permintaan->review_by= Employe::employeId(); 
            if ($permintaan->save()) {
                return new PostResource(true, 'Permintaan rejected successfully', []);
            }
            return new PostResource(false, 'Failed to reject Permintaan', []);
        }
        return new PostResource(false, 'Permintaan not found', []);
    }

    public function insertBBM(Request $request)
    {
        $bbm = new Bbm();
        $bbm->id_mobil = $request->id_mobil;
        $bbm->jenis_bbm = $request->jenis_bbm;
        $bbm->jumlah = $request->jumlah;
        $bbm->w_pengisian = $request->w_pengisian;
        $bbm->oleh = $request->oleh;

        if ($bbm->save()) {
            return new PostResource(true, 'BBM data inserted successfully', []);
        }
        return new PostResource(false, 'Failed to insert BBM data', []);
    }

    public function getBBM()
    {
        $data = Bbm::where('deleted_at', null)
            ->orderBy('w_pengisian', 'desc')
            ->get();
        foreach ($data as $item) {
            $mobil = Mobil::find($item->id_mobil);
            $item->name = $mobil ? $mobil->brand . ' (' . $mobil->plat . ')' : null;

            $employe = Employe::where('employe_id', $item->oleh)->first();
            $item->oleh_name = $employe ? $employe->first_name : null;
        }
        return new PostResource(true, 'success', $data);
    }

    /**
     * Ambil daftar mobil yang status-nya 'aktif', tidak dihapus, dan tidak sedang dalam pemakaian.
     */
    public function getMobilAktifDanTidakDalamPemakaian()
    {
        $mobilDipakai = Pengambilan::whereNull('real_pengembalian')
            ->whereNull('deleted_at')
            ->pluck('id_mobil')
            ->toArray();

        $data = Mobil::where('status', 'aktif')
            ->where('deleted', 0)
            ->whereNotIn('id', $mobilDipakai)
            ->get();

        return new PostResource(true, 'success', $data);
    }

    /**
     * Ambil data pengambilan dan konversi ke format kalender.
     * id: id pengambilan,
     * group: id mobil,
     * title: first_name,
     * start_time: taken_time,
     * end_time: real_pengembalian,
     * bgColor: random rgba per id_mobil.
     */
    public function getPengambilanCalendar()
    {
        $data = Pengambilan::whereNull('deleted_at')->get();
        $result = [];
        $colorMap = [];

        foreach ($data as $item) {
            $employe = Employe::where('employe_id', $item->employe_id)->first();
            $firstName = $employe ? $employe->first_name : '';

            // Generate consistent bgColor per id_mobil
            if (!isset($colorMap[$item->id_mobil])) {
                // Simple hash to color
                $hash = crc32($item->id_mobil);
                $r = 100 + ($hash & 0xFF) % 156;
                $g = 100 + (($hash >> 8) & 0xFF) % 156;
                $b = 100 + (($hash >> 16) & 0xFF) % 156;
                $colorMap[$item->id_mobil] = "rgba($r, $g, $b, 0.6)";
            }
            $defaultBgColor = $colorMap[$item->id_mobil];

            // Logic untuk end_time dan bgColor
            $now = now();
            if ($item->real_pengembalian) {
                $endTime = $item->real_pengembalian;
                $bgColor = $defaultBgColor;
            } else {
                // Jika real_pengembalian null, gunakan pengembalian
                $pengembalian = $item->pengembalian;
                if ($pengembalian && Carbon::parse($pengembalian)->lt($now)) {
                    // Sudah lewat dari waktu pengembalian, set end_time = now dan bgColor merah
                    $endTime = $now->format('Y-m-d H:i:s');
                    $bgColor = "rgba(255,0,0,0.6)";
                } else {
                    $endTime = $pengembalian;
                    $bgColor = $defaultBgColor;
                }
            }

            $result[] = [
                'id' => $item->id,
                'group' => $item->id_mobil,
                'title' => $firstName,
                'start_time' => $item->taken_time,
                'end_time' => $endTime,
                'bgColor' => $bgColor,
            ];
        }

        return new PostResource(true, 'success', $result);
    }

    /**
     * Get laporan BBM per mobil untuk grafik.
     * Request: startdate, enddate (GET)
     * Response: categories (array nama & plat), data (array total jumlah per mobil)
     */
    public function getBBMLaporan(Request $request)
    {
        $startdate = $request->query('startdate');
        $enddate = $request->query('enddate');

        if (!$startdate || !$enddate) {
            return new PostResource(false, 'startdate dan enddate wajib diisi', []);
        }

        // Ambil semua mobil yang punya transaksi BBM di rentang waktu
        $bbmQuery = Bbm::where('deleted_at', null)
            ->whereDate('w_pengisian', '>=', $startdate)
            ->whereDate('w_pengisian', '<=', $enddate);

        // Group by id_mobil, ambil total jumlah per mobil
        $bbmPerMobil = $bbmQuery
            ->selectRaw('id_mobil, SUM(jumlah) as total_jumlah')
            ->groupBy('id_mobil')
            ->get();

        $categories = [];
        $data = [];
        $totalSemua = 0;

        foreach ($bbmPerMobil as $row) {
            $mobil = Mobil::find($row->id_mobil);
            if ($mobil) {
                $categories[] = [$mobil->brand, $mobil->plat];
                $jumlah = (float) $row->total_jumlah;
                $data[] = $jumlah;
                $totalSemua += $jumlah;
            }
        }

        $result = [
            'categories' => $categories,
            'data' => $data,
            'total_semua' => $totalSemua,
        ];

        return new PostResource(true, 'success', $result);
    }
}
