<?php

namespace App\Http\Controllers\Adm;

use App\Http\Controllers\Controller;
use App\Http\Resources\PostResource;
use App\Models\AtasanTerkait;
use App\Models\Employe;
use App\Models\Organization;
use App\Models\Position;
use App\Models\Structure;
use Auth;
use Illuminate\Http\Request;
use PhpParser\Node\Expr\PostInc;

class StaticAdmController extends Controller
{
    public function getDivisi()
    {
        $data = Organization::where('is_division', 1)->where('board_id', Structure::where('employe_id', Employe::employeId())->first('board_id')->board_id)->get();
        $myData=Organization::where('organization_id', Structure::where('employe_id', Employe::employeId())->first('organization_id')->organization_id)->first();

        return new PostResource(true, "Data Divisi", ['other_divisis'=>$data, 'my_divisi'=>$myData]);
    }

    public function getSigner($id)
    {
        $data=Structure::where('employe_active', 1)->where(function($query){
            $query->where('id_base', 3)
            ->orWhere('id_base', 4);
        })->get();

        $mEks=AtasanTerkait::where('employe_id', Structure::where('employe_active', 1)->where('organization_id', $id)->first('employe_id')->employe_id)->first('manager_eksekutif')->manager_eksekutif;
        if($mEks){
            $data->push(Structure::where('employe_active',1 )->where('employe_id', $mEks)->first());
        }
        // 

        $manager=Structure::where('employe_active',1)->where('id_base', 7)->where('organization_id', $id)->first();
        $supervisor=Structure::where('employe_active',1)->where('id_base', 8)->where('organization_id', $id)->get();
        if($manager){
            $data->push($manager);
        }else{
            foreach($supervisor as $s){
                $data->push($s);
            }
        }
        

        return new PostResource(true, 'Pilihan signers', $data);
    }

    public function getDirektur(){
        $data=Position::where('id_base',3)->orWhere('id_base',4)->get();
        return new PostResource(true, 'Pilihan direktur', $data);
    }


    public function getDispo(){ 
        $data['direksi']=[];
        $data['manager_eks']=[];
        $data['divisions']=[];
        $base=Structure::where('employe_id', Employe::employeId())->first('id_base')->id_base;
        if($base==3){
            $data['direksi']=Structure::where('id_base',4)->get(); 
            foreach($data['direksi'] as $d){
                $d->value=$d->position_id;
                $d->type='position';
                $d->label=$d->position_name;
            }
        }

        if($base==4 ||$base==3){  
            $data['manager_eks']=Structure::where('id_base',6)->get();
            foreach($data['manager_eks'] as $d){
                $d->value=$d->position_id;
                $d->type='position';
                $d->label=$d->position_name;
            }
        } 

        if($base== 4||$base== 3 || $base== 6){
            $data['divisions']=Structure::getPukDivision();
            foreach($data['divisions'] as $d){
                $d->value=$d->organization_id;
                $d->type='division';
                $d->label=$d->position_name;
            }
        }
      
      
        return new PostResource(true,'Pilihan Direksi',  $data);
    }
}
