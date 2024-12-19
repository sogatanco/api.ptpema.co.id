<?php

namespace App\Http\Controllers\Adm;

use App\Http\Controllers\Controller;
use App\Http\Resources\PostResource;
use App\Models\Adm\DataDivisi;
use App\Models\Adm\ListSurat;
use App\Models\Adm\ListSuratMasuk;
use App\Models\Adm\Surat;
use App\Models\AtasanTerkait;
use App\Models\Employe;
use App\Models\Organization;
use App\Models\Position;
use App\Models\Structure;
use App\Models\Adm\SuratMasuk as SM;
use Auth;
use Illuminate\Http\Request;
use PhpParser\Node\Expr\PostInc;

class StaticAdmController extends Controller
{
    public function getDivisi()
    {
        $data = Organization::where('is_division', 1)->where('board_id', Structure::where('employe_id', Employe::employeId())->first('board_id')->board_id)->get();
        $myData = Organization::where('organization_id', Structure::where('employe_id', Employe::employeId())->first('organization_id')->organization_id)->first();

        return new PostResource(true, "Data Divisi", ['other_divisis' => $data, 'my_divisi' => $myData]);
    }

    public function getSigner($id)
    {
        $data = Structure::where('employe_active', 1)->where(function ($query) {
            $query->where('id_base', 3)
                ->orWhere('id_base', 4);
        })->get();

        $mEks = AtasanTerkait::where('employe_id', Structure::where('employe_active', 1)->where('organization_id', $id)->first('employe_id')->employe_id)->first('manager_eksekutif')->manager_eksekutif;
        if ($mEks) {
            $data->push(Structure::where('employe_active', 1)->where('employe_id', $mEks)->first());
        }
        // 

        $manager = Structure::where('employe_active', 1)->where('id_base', 7)->where('organization_id', $id)->first();
        $supervisor = Structure::where('employe_active', 1)->where('id_base', 8)->where('organization_id', $id)->get();
        if ($manager) {
            $data->push($manager);
        } else {
            foreach ($supervisor as $s) {
                $data->push($s);
            }
        }


        return new PostResource(true, 'Pilihan signers', $data);
    }

    public function getDirektur()
    {
        $data = Position::where('id_base', 3)->orWhere('id_base', 4)->get();
        return new PostResource(true, 'Pilihan direktur', $data);
    }


    public function getDispo()
    {
        $data['direksi'] = [];
        $data['manager_eks'] = [];
        $data['divisions'] = [];
        $base = Structure::where('employe_id', Employe::employeId())->first('id_base')->id_base;
        if ($base == 3) {
            $data['direksi'] = Structure::where('id_base', 4)->get();
            foreach ($data['direksi'] as $d) {
                $d->value = $d->position_id;
                $d->type = 'position';
                $d->label = $d->position_name;
            }
        }

        if ($base == 4 || $base == 3) {
            $data['manager_eks'] = Structure::where('id_base', 6)->get();
            foreach ($data['manager_eks'] as $d) {
                $d->value = $d->position_id;
                $d->type = 'position';
                $d->label = $d->position_name;
            }
            $data['divisions'] = Structure::getPukDivision();
            foreach ($data['divisions'] as $d) {
                $d->value = $d->organization_id;
                $d->type = 'division';
                $d->label = $d->position_name;
            }
        }

        if ($base == 6) {
            $data['divisions'] = Structure::getPukDivisionUnderMe(Employe::employeId());
            foreach ($data['divisions'] as $d) {
                $d->value = $d->organization_id;
                $d->type = 'division';
                $d->label = $d->position_name;
            }
        }

        $all['disposisi'] = $data;
        $all['cc'] = collect($data['direksi'])
            ->merge($data['manager_eks'])
            ->merge($data['divisions']);


        return new PostResource(true, 'Pilihan Direksi', $all);
    }

    public function dashboard()
    {
        $collection = collect();
        $collection->put(
            'dataDash',
            [
                [
                    'title' => 'Surat Keluar',
                    'sub' => 'Tahun ' . date("Y"),
                    'type' => 'scatter',
                    'color' => 'bg-warning',
                    'value' => count(ListSurat::where('status', 'signed')->get()),
                ],
                [
                    'title' => 'Surat Masuk',
                    'sub' => 'Tahun ' . date("Y"),
                    'type' => 'bar',
                    'color' => 'bg-success',
                    'value' => count(SM::get()),
                ],
                [
                    'title' => 'Document',
                    'type' => 'radar',
                    'color' => 'bg-secondary',
                    'sub' => (in_array('AdminAdm', Auth::user()->roles))
                        ? 'Created by me'
                        : (!empty(array_intersect(['Manager', 'ManagerEks', 'Director', 'Presdir'], Auth::user()->roles))
                            ? 'Sign by me'
                            : 'Divisi terkait'),
                    'value' => (in_array('AdminAdm', Auth::user()->roles))
                        ? count(Surat::where('submitted_by', Employe::employeId())->get())
                        : (!empty(array_intersect(['Manager', 'ManagerEks', 'Director', 'Presdir'], Auth::user()->roles))
                            ? count(ListSurat::where('status', 'signed')->where('sign_by', Employe::employeId())->get())
                            : count(ListSurat::where('status', 'signed')->where('divisi', Structure::where('employe_id', Employe::employeId())->first('organization_id')->organization_id)->get())),
                ],
                [
                    'title' => 'Document',
                    'sub' => (in_array('Presdir', Auth::user()->roles) ? 'Submitted to me' : 'Disposed to me'),
                    'type' => 'line',
                    'color' => 'bg-primary',
                    'value' => count(ListSuratMasuk::where('live_receiver', Employe::employeId())->get())
                ]

            ]
        );
        $dv = DataDivisi::orderBy('divisi', 'ASC')->get();
        $collection = collect([
            "chart" => [
                "divisi" => collect() // Gunakan Collection di dalam 'divisi'
            ]
        ]);
        foreach ($dv as $d) {

            $collection['chart']['divisi']->push($d->divisi); 
            // $collection['chart']['value'][] = $d->jumlah_surat;
        }

        if (!empty(array_intersect(['ManagerEks', 'Director', 'Presdir', 'SuperAdminAdm'], Auth::user()->roles))) {
            $collection->put('latest_surat', ListSurat::where('status', 'signed')->get());
        } else {
            $collection->put('latest_surat', ListSurat::where('status', 'signed')->where('divisi', Structure::where('employe_id', Employe::employeId())->first('organization_id')->organization_id)->get());
        }

        return new PostResource(true, 'Dashboard', $collection->toArray());
    }


}
