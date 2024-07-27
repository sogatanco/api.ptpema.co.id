<?php

namespace App\Http\Controllers\Da;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\DailyAct\DaActivit;
use App\Models\Employe;
use App\Models\Structure;
use App\Models\DailyAct\DaCatAct;
use App\Http\Resources\PostResource;


class DaActivitController extends Controller
{

    function index($filter)
    {
        if ($filter === 'today') {
            $data = DaActivit::whereDate('start', '=', date('Y-m-d'))->where('created_by', 'like', '%//' . Employe::employeId() . '//%')->latest()->get();
            foreach ($data as $d) {
                $member = explode(',', $d->created_by);
                $memberNew = [];
                for ($i = 0; $i < count($member); $i++) {
                    $m = str_replace('//', '', $member[$i]);
                    $memberNew[$i] = Structure::where('employe_id', $m)->first();
                }
                $d->member = $memberNew;
                $d->category_name = DaCatAct::where('id', $d->category)->get()->first()->category_name;
            }
        } else if ($filter === 'yesterday') {
            $data = DaActivit::whereDate('start', '=', date('Y-m-d',strtotime("-1 days")))->where('created_by', 'like', '%//' . Employe::employeId() . '//%')->latest()->get();
            foreach ($data as $d) {
                $member = explode(',', $d->created_by);
                $memberNew = [];
                for ($i = 0; $i < count($member); $i++) {
                    $m = str_replace('//', '', $member[$i]);
                    $memberNew[$i] = Structure::where('employe_id', $m)->first();
                }
                $d->member = $memberNew;
                $d->category_name = DaCatAct::where('id', $d->category)->get()->first()->category_name;
            }
        } else if ($filter === 'week') {
            $data = DaActivit::whereDate('start', '>=', date('Y-m-d',strtotime("-7 days")))->where('created_by', 'like', '%//' . Employe::employeId() . '//%')->latest()->get();
            foreach ($data as $d) {
                $member = explode(',', $d->created_by);
                $memberNew = [];
                for ($i = 0; $i < count($member); $i++) {
                    $m = str_replace('//', '', $member[$i]);
                    $memberNew[$i] = Structure::where('employe_id', $m)->first();
                }
                $d->member = $memberNew;
                $d->category_name = DaCatAct::where('id', $d->category)->get()->first()->category_name;
            }
        } else if ($filter === 'month') {
            $data = DaActivit::whereMonth('start', '=', (date('m')))->where('created_by', 'like', '%//' . Employe::employeId() . '//%')->latest()->get();
            foreach ($data as $d) {
                $member = explode(',', $d->created_by);
                $memberNew = [];
                for ($i = 0; $i < count($member); $i++) {
                    $m = str_replace('//', '', $member[$i]);
                    $memberNew[$i] = Structure::where('employe_id', $m)->first();
                }
                $d->member = $memberNew;
                $d->category_name = DaCatAct::where('id', $d->category)->get()->first()->category_name;
            }
        } else if ($filter === 'year') {
            $data = DaActivit::whereYear('start', '=', (date('Y')))->where('created_by', 'like', '%//' . Employe::employeId() . '//%')->latest()->get();
            foreach ($data as $d) {
                $member = explode(',', $d->created_by);
                $memberNew = [];
                for ($i = 0; $i < count($member); $i++) {
                    $m = str_replace('//', '', $member[$i]);
                    $memberNew[$i] = Structure::where('employe_id', $m)->first();
                }
                $d->member = $memberNew;
                $d->category_name = DaCatAct::where('id', $d->category)->get()->first()->category_name;
            }
        } else {
            $data = DaActivit::where('created_by', 'like', '%//' . Employe::employeId() . '//%')->latest()->get();
            foreach ($data as $d) {
                $member = explode(',', $d->created_by);
                $memberNew = [];
                for ($i = 0; $i < count($member); $i++) {
                    $m = str_replace('//', '', $member[$i]);
                    $memberNew[$i] = Structure::where('employe_id', $m)->first();
                }
                $d->member = $memberNew;
                $d->category_name = DaCatAct::where('id', $d->category)->get()->first()->category_name;
            }
        }


        return response()->json([
            'status' => true,
            'data' => $data
        ], 200);
    }
    function store(Request $request)
    {
        $db = new DaActivit();
        $db->activity = $request->activity;
        $db->poin = $request->poin;
        $db->progress = $request->progress;
        $db->status = 'submit';
        $db->category = $request->category;
        $db->start = date('Y-m-d H:i:s', strtotime($request->start));
        $db->end =  date('Y-m-d H:i:s', strtotime($request->end));
        $db->created_by = '//' . Employe::employeId() . '//';

        if ($db->save()) {
            return response()->json([
                "status" => true,
                "message" => 'Success',
            ], 200);
        }
    }

    function updateMember(Request $request)
    {
        $db = DaActivit::find($request->id);
        $db->created_by = $request->new_member;
        if ($db->save()) {
            return new PostResource(true, 'sgsdg', []);
        } else {
            return new PostResource(false, 'sgsdg', []);
        }
    }

    function updateProg(Request $request)
    {
        $db = DaActivit::find($request->id);
        $db->progress = $request->progress;
        $db->status = 'submit';
        if ($db->save()) {
            return new PostResource(true, 'sgsdg', []);
        } else {
            return new PostResource(false, 'sgsdg', []);
        }
    }

    function deleteProg(Request $request)
    {
        $db = DaActivit::find($request->id);
        if ($db->delete()) {
            return new PostResource(true, 'sgsdg', []);
        } else {
            return new PostResource(false, 'sgsdg', []);
        }
    }

    function getReview()
    {
        $data = Structure::where('direct_atasan', Employe::employeId())->get();
        $alldayli = [];
        for ($i = 0; $i < count($data); $i++) {
            $datateam = DaActivit::where('created_by', 'like', '//' . $data[$i]->employe_id . '//%')->where('progress', 100)->where('status', 'submit')->latest()->get();

            for ($j = 0; $j < count($datateam); $j++) {
                $member = explode(',', $datateam[$j]->created_by);
                $memberNew = [];
                for ($k = 0; $k < count($member); $k++) {
                    $m = str_replace('//', '', $member[$k]);
                    $memberNew[$k] = Structure::where('employe_id', $m)->first();
                }
                $datateam[$j]->category_name = DaCatAct::where('id', $datateam[$j]->category)->get()->first()->category_name;
                $datateam[$j]->member = $memberNew;
                array_push($alldayli, $datateam[$j]);
            }


        }


        return new PostResource(true, 'sgsdg', $alldayli);
    }

    function changeStatus(Request $request)
    {
        $db = DaActivit::find($request->id);

        if ($request->status === 'reject') {
            $db->status = 'reject';
            $db->progress = 90;
            $db->catatan = $request->catatan;
            if ($db->save()) {
                return new PostResource(true, 'sgsdg', []);
            }
        }

        if ($request->status === 'approve') {
            $db->status = 'approve';
            $db->activity = $request->activity;
            $db->category = $request->category;
            $db->poin = $request->poin;
            if ($db->save()) {
                $db = new DaActivit();
                $activity = '';

                if (str_starts_with(strtolower($request->activity), '#review')) {
                    $activity = '#Persetujuan ' . $request->activity;
                } else if (str_starts_with(strtolower($request->activity), '#persetujuan')) {
                    $activity = $request->activity;
                } else {
                    $activity = '#Review ' . $request->activity;
                }
                $db->activity = $activity;
                $poin = DaActivit::where('activity', $activity)->where('status', 'approve')->get()->first();
                if (is_null($poin)) {
                    $db->status = 'submit';
                    $db->poin = 0;
                } else {
                    $db->poin = 100;
                    $db->status = 'approve';
                }

                $db->progress = 100;
                $db->category = $request->category;
                $db->start = date('Y-m-d H:i:s');
                $db->end =  date('Y-m-d H:i:s');
                $db->created_by = '//' . Employe::employeId() . '//';
                if ($db->save()) {
                    return new PostResource(true, 'sdgsdg', []);
                }
            }
        }
    }

    function getTeamAct($filter){
        
        $myteam=[];
        $team=Structure::where('direct_atasan', Employe::employeId())->get();
        foreach($team as $t){

            array_push($myteam, $t->employe_id);
            $team1=Structure::where('direct_atasan', $t->employe_id)->get();
            if($team1->count()>0){
                foreach($team1 as $t1){
                    array_push($myteam, $t1->employe_id);
                    $team2=Structure::where('direct_atasan', $t1->employe_id)->get();
                    if($team2->count()>0){
                        foreach($team2 as $t2){
                            array_push($myteam, $t2->employe_id);
                            $team3=Structure::where('direct_atasan', $t2->employe_id)->get();
                            if($team3->count()>0){
                                foreach($team3 as $t3){
                                    array_push($myteam, $t3->employe_id);
                                    $team4=Structure::where('direct_atasan', $t3->employe_id)->get();
                                    if($team4->count()>0){
                                        foreach($team4 as $t4){
                                            array_push($myteam, $t4->employe_id);
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        $activities=[];

        if ($filter === 'today') {
            for ($i = 0; $i < count($myteam); $i++) {
                $datateam = DaActivit::whereDate('start', '=', date('Y-m-d'))->where('status', '!=', 'reject')->where('created_by', 'like', '%//' . $myteam[$i] . '//%')->latest()->get();
    
                for ($j = 0; $j < count($datateam); $j++) {
                    $member = explode(',', $datateam[$j]->created_by);
                    $memberNew = [];
                    for ($k = 0; $k < count($member); $k++) {
                        $m = str_replace('//', '', $member[$k]);
                        $memberNew[$k] = Structure::where('employe_id', $m)->first();
                    }
                    $datateam[$j]->category_name = DaCatAct::where('id', $datateam[$j]->category)->get()->first()->category_name;
                    $datateam[$j]->member = $memberNew;
                    array_push($activities, $datateam[$j]);
                }
    
            }
        }else if ($filter === 'yesterday') {
            for ($i = 0; $i < count($myteam); $i++) {
                $datateam = DaActivit::whereDate('start', '=', date('Y-m-d',strtotime("-1 days")))->where('status', '!=', 'reject')->where('created_by', 'like', '%//' . $myteam[$i] . '//%')->latest()->get();
    
                for ($j = 0; $j < count($datateam); $j++) {
                    $member = explode(',', $datateam[$j]->created_by);
                    $memberNew = [];
                    for ($k = 0; $k < count($member); $k++) {
                        $m = str_replace('//', '', $member[$k]);
                        $memberNew[$k] = Structure::where('employe_id', $m)->first();
                    }
                    $datateam[$j]->category_name = DaCatAct::where('id', $datateam[$j]->category)->get()->first()->category_name;
                    $datateam[$j]->member = $memberNew;
                    array_push($activities, $datateam[$j]);
                }
    
            }
        }else if ($filter === 'week') {
            for ($i = 0; $i < count($myteam); $i++) {
                $datateam = DaActivit::whereDate('start', '>=', date('Y-m-d',strtotime("-7 days")))->where('status', '!=', 'reject')->where('created_by', 'like', '%//' . $myteam[$i] . '//%')->latest()->get();
    
                for ($j = 0; $j < count($datateam); $j++) {
                    $member = explode(',', $datateam[$j]->created_by);
                    $memberNew = [];
                    for ($k = 0; $k < count($member); $k++) {
                        $m = str_replace('//', '', $member[$k]);
                        $memberNew[$k] = Structure::where('employe_id', $m)->first();
                    }
                    $datateam[$j]->category_name = DaCatAct::where('id', $datateam[$j]->category)->get()->first()->category_name;
                    $datateam[$j]->member = $memberNew;
                    array_push($activities, $datateam[$j]);
                }
    
            }
        }else if ($filter === 'month') {
            for ($i = 0; $i < count($myteam); $i++) {
                $datateam = DaActivit::whereMonth('start', '=', (date('m')))->where('status', '!=', 'reject')->where('created_by', 'like', '%//' . $myteam[$i] . '//%')->latest()->get();
    
                for ($j = 0; $j < count($datateam); $j++) {
                    $member = explode(',', $datateam[$j]->created_by);
                    $memberNew = [];
                    for ($k = 0; $k < count($member); $k++) {
                        $m = str_replace('//', '', $member[$k]);
                        $memberNew[$k] = Structure::where('employe_id', $m)->first();
                    }
                    $datateam[$j]->category_name = DaCatAct::where('id', $datateam[$j]->category)->get()->first()->category_name;
                    $datateam[$j]->member = $memberNew;
                    array_push($activities, $datateam[$j]);
                }
    
            }
        }else if ($filter === 'year') {
            for ($i = 0; $i < count($myteam); $i++) {
                $datateam = DaActivit::whereYear('start', '=', (date('Y')))->where('status', '!=', 'reject')->where('created_by', 'like', '%//' . $myteam[$i] . '//%')->latest()->get();
    
                for ($j = 0; $j < count($datateam); $j++) {
                    $member = explode(',', $datateam[$j]->created_by);
                    $memberNew = [];
                    for ($k = 0; $k < count($member); $k++) {
                        $m = str_replace('//', '', $member[$k]);
                        $memberNew[$k] = Structure::where('employe_id', $m)->first();
                    }
                    $datateam[$j]->category_name = DaCatAct::where('id', $datateam[$j]->category)->get()->first()->category_name;
                    $datateam[$j]->member = $memberNew;
                    array_push($activities, $datateam[$j]);
                }
    
            }
        }else{
            for ($i = 0; $i < count($myteam); $i++) {
                $datateam = DaActivit::where('status', '!=', 'reject')->where('created_by', 'like', '%//' . $myteam[$i] . '//%')->latest()->get();
    
                for ($j = 0; $j < count($datateam); $j++) {
                    $member = explode(',', $datateam[$j]->created_by);
                    $memberNew = [];
                    for ($k = 0; $k < count($member); $k++) {
                        $m = str_replace('//', '', $member[$k]);
                        $memberNew[$k] = Structure::where('employe_id', $m)->first();
                    }
                    $datateam[$j]->category_name = DaCatAct::where('id', $datateam[$j]->category)->get()->first()->category_name;
                    $datateam[$j]->member = $memberNew;
                    array_push($activities, $datateam[$j]);
                }
    
            }
        }

        return new PostResource(true, ''.count(array_unique($activities)), array_unique($activities));
    }
    
    function allActivit($filter){
        $activities=[];

        if ($filter === 'today') {
           
                $datateam = DaActivit::whereDate('start', '=', date('Y-m-d'))->where('status', '!=', 'reject')->latest()->get();
    
                for ($j = 0; $j < count($datateam); $j++) {
                    $member = explode(',', $datateam[$j]->created_by);
                    $memberNew = [];
                    for ($k = 0; $k < count($member); $k++) {
                        $m = str_replace('//', '', $member[$k]);
                        $memberNew[$k] = Structure::where('employe_id', $m)->first();
                    }
                    $datateam[$j]->category_name = DaCatAct::where('id', $datateam[$j]->category)->get()->first()->category_name;
                    $datateam[$j]->member = $memberNew;
                    array_push($activities, $datateam[$j]);
                }
    

        }else if ($filter === 'yesterday'){
             $datateam = DaActivit::whereDate('start', '=', date('Y-m-d',strtotime("-1 days")))->where('status', '!=', 'reject')->latest()->get();
    
                for ($j = 0; $j < count($datateam); $j++) {
                    $member = explode(',', $datateam[$j]->created_by);
                    $memberNew = [];
                    for ($k = 0; $k < count($member); $k++) {
                        $m = str_replace('//', '', $member[$k]);
                        $memberNew[$k] = Structure::where('employe_id', $m)->first();
                    }
                    $datateam[$j]->category_name = DaCatAct::where('id', $datateam[$j]->category)->get()->first()->category_name;
                    $datateam[$j]->member = $memberNew;
                    array_push($activities, $datateam[$j]);
                }
        }else if ($filter === 'week'){
             $datateam = DaActivit::whereDate('start', '>=', date('Y-m-d',strtotime("-7 days")))->where('status', '!=', 'reject')->latest()->get();
    
                for ($j = 0; $j < count($datateam); $j++) {
                    $member = explode(',', $datateam[$j]->created_by);
                    $memberNew = [];
                    for ($k = 0; $k < count($member); $k++) {
                        $m = str_replace('//', '', $member[$k]);
                        $memberNew[$k] = Structure::where('employe_id', $m)->first();
                    }
                    $datateam[$j]->category_name = DaCatAct::where('id', $datateam[$j]->category)->get()->first()->category_name;
                    $datateam[$j]->member = $memberNew;
                    array_push($activities, $datateam[$j]);
                }
        }else if ($filter === 'month'){
             $datateam = DaActivit::whereMonth('start', '=', (date('m')))->where('status', '!=', 'reject')->latest()->get();
    
                for ($j = 0; $j < count($datateam); $j++) {
                    $member = explode(',', $datateam[$j]->created_by);
                    $memberNew = [];
                    for ($k = 0; $k < count($member); $k++) {
                        $m = str_replace('//', '', $member[$k]);
                        $memberNew[$k] = Structure::where('employe_id', $m)->first();
                    }
                    $datateam[$j]->category_name = DaCatAct::where('id', $datateam[$j]->category)->get()->first()->category_name;
                    $datateam[$j]->member = $memberNew;
                    array_push($activities, $datateam[$j]);
                }
        }else if ($filter === 'year'){
             $datateam = DaActivit::whereYear('start', '=', (date('Y')))->where('status', '!=', 'reject')->latest()->get();
    
                for ($j = 0; $j < count($datateam); $j++) {
                    $member = explode(',', $datateam[$j]->created_by);
                    $memberNew = [];
                    for ($k = 0; $k < count($member); $k++) {
                        $m = str_replace('//', '', $member[$k]);
                        $memberNew[$k] = Structure::where('employe_id', $m)->first();
                    }
                    $datateam[$j]->category_name = DaCatAct::where('id', $datateam[$j]->category)->get()->first()->category_name;
                    $datateam[$j]->member = $memberNew;
                    array_push($activities, $datateam[$j]);
                }
        }else{
            $datateam = DaActivit::where('status', '!=', 'reject')->latest()->get();
    
                for ($j = 0; $j < count($datateam); $j++) {
                    $member = explode(',', $datateam[$j]->created_by);
                    $memberNew = [];
                    for ($k = 0; $k < count($member); $k++) {
                        $m = str_replace('//', '', $member[$k]);
                        $memberNew[$k] = Structure::where('employe_id', $m)->first();
                    }
                    $datateam[$j]->category_name = DaCatAct::where('id', $datateam[$j]->category)->get()->first()->category_name;
                    $datateam[$j]->member = $memberNew;
                    array_push($activities, $datateam[$j]);
                } 
        }


        return new PostResource(true, ''.count(array_unique($activities)), array_unique($activities));
    }
}
