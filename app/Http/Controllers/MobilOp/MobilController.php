<?php

namespace App\Http\Controllers\MobilOp;

use App\Http\Controllers\Controller;
use App\Http\Resources\PostResource;
use App\Models\Employe;
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
}
