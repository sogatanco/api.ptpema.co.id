<?php

namespace App\Http\Controllers\Employe;

use App\Http\Controllers\Controller;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use App\Models\Employe;
use App\Models\Structure;
use App\Models\Position;
use App\Models\Notification;
use Illuminate\Support\Facades\Auth;

class EmployeController extends Controller
{
    // public function __construct()
    // {
    //     $this->middleware('auth:api');
    // }

    public function index(){
        $list = Employe::get();
        $total = $list->count();

        $user = auth()->user();

        return response()->json([
            "status" => true,
            "total" => $total,
            "data" => $list,
            "user" => $user
        ], 200);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'employe_id' => ['required', 'unique:employees'],
            'user_id' => ['required', 'unique:employees'],
            'first_name' => ['required', 'max:20'],
            'last_name' => ['required', 'max:20'],
            'gender' => ['required'],
            'religion' => ['required'],
            'birthday' => ['required'],
            'birthday_place' => ['required', "max:100"],
            'marital_status' => ['required'],
            'img' => ['required'],
        ]);

        if($validator->fails()){
            return response()->json([
                'status' => false,
                "errors" => $validator->errors()
            ]);
        }

        $employe = Employe::create($request->all());

        return response()->json([
            "status" => true,
            'message' => "Employe data has been created.",
            "data" => $employe
        ], 201);
    }

    public function update(Request $request, $employe_id)
    {
        // validating
        $validator = Validator::make($request->all(), [
            "first_name" => ["required", "max:20"],
            "last_name" => ["required", "max:20"],
            "gender" => ["required"],
            "religion" => ["required"],
            "birthday" => ["required"],
            "birthday_place" => ["required"],
            "marital_status" => ["required"],
            "img" => ["required"]
        ]);

        if($validator->fails()){
            return response()->json([
                "status" => false,
                "errors" => $validator->errors()
            ]);
        }

        $savedUpdate = Employe::where('employe_id', $employe_id)->update($request->all());

        if($savedUpdate){
            $newData = Employe::where('employe_id', $employe_id)->first();
            return response()->json([
               "status" => true,
               "message" => "Employe has been updated.",
               "data" => $newData
            ], 200);
        } else {
            return response()->json([
               "status" => false,
               "message" => "Data update failed."
            ], 500);
        }
    }

    public function show($employe_id)
    {
        $emp = Structure::where('employe_id', $employe_id)->first();

        if($emp){
            return response()->json([
                "status" => true,
                "data" => $emp
            ], 200);
        }else{
            return response()->json([
                "status" => false,
                "message" => "Employe not found"
            ], 404);
        }
    }

    public function destroy($employe_id)
    {
        $deletedEmploye = Employe::where("employe_id", $employe_id)->delete();

        if($deletedEmploye){
            return response()->json([
                "status" => true,
                "message" => "Employe has been deleted."
            ], 200);
        }else{
            return response()->json([
                "Status" => false,
                "message" => "Delete data failed."
            ], 500);
        }
    }

    public function getEmployeDivision($employe_id)
    {
        $position = Employe::where('employees.employe_id', '=' , $employe_id)
                    ->leftJoin('positions', 'employees.position_id', '=', 'positions.position_id')
                    ->leftJoin('organizations', 'organizations.organization_id', '=', 'positions.organization_id')
                    ->select('positions.position_id', 'positions.position_name', 'organizations.organization_id', 'organizations.organization_name')
                    ->first();

        if(!$position){
            throw new HttpResponseException(response([
                "errors" => [
                    "message" => [
                        "Position not found."
                    ]
                ]
            ], 404));
        }

        return response()->json([
            "status" => true,
            "employee id" => $employe_id,
            "division" => json_decode($position)
        ], 200);
    }

    public function assignmentList()
    {

        $userRoles = Auth::user()->roles;

        if(in_array("Manager", $userRoles)){
            // JIKA USER ADALAH MANAGER
            $list = Employe::select('employe_id', 'first_name', 'users.roles')
                    ->join('users', 'users.id','=', 'employees.user_id')
                    ->like('users.roles', '%Manager%')
                    ->get();
        }else{
            $list = Employe::select('employe_id', 'first_name', 'users.roles')
                ->join('users', 'users.id','=', 'employees.user_id')
                ->get();
        }

        $total = $list->count();
        $assignment = [];

        for ($i=0; $i < $total; $i++) { 
            $assignment[$i] = [
                "value" => $list[$i]->employe_id,
                "label" => $list[$i]->first_name,
                "roles" => $list[$i]->roles
            ];
        }

        return response()->json([
            "status" => true,
            "total" => $total,
            "data" => $assignment,
        ], 200);
    }

    public function notification()
    {
        
        $employeId = Employe::employeId();
        $nots = Notification::select(
                    'id', 
                    'project_notifications.from_employe', 
                    'project_notifications.to_employe', 
                    'project_id', 
                    'title', 
                    'desc',
                    'category', 
                    'employees.first_name', 
                    'project_notifications.created_at'
                )
                ->where(['project_notifications.to_employe' => $employeId, 'read_status' => 0])
                ->join('employees', 'employees.employe_id', '=', 'project_notifications.from_employe')
                ->orderBy('project_notifications.created_at', 'desc')
                ->get();

        return response()->json([
            "status" => true,
            "data" => $nots
        ], 200);
    }

    public function updateNotif($notifId)
    {
        $notif = Notification::find($notifId);
        $notif->read_status = 1;
        $saved = $notif->save();

        if($saved){
            return response()->json([
                "status" => true,
                "data" => $notif
            ], 200);
        }else{
            throw new HttpResponseException(response([
                "status" => false
            ], 400));
        }
    }


    public function checkStructure($emplo)
    {
        $atasan = Structure::select('level_1', 'level_2', 'level_3', 'level_4', 'level_5')
                ->where('employe_id', $emplo)
                ->first();

        return response()->json([
            "ata" => $atasan,
            "atasan" => $atasan->level_1 === null ? 
                        $atasan->level_2 : $atasan->level_1
        ], 200);
    }

    public function employeesByDivision($managerId)
    {
        $managerDivision = Employe::getEmployeDivision($managerId);

        $memberOfDivision = Structure::select('employe_id', 'first_name')
                            ->where('organization_id', $managerDivision->organization_id)
                            ->get();

        $total = $memberOfDivision->count();
        $assignment = [];
                    
        for ($i=0; $i < $total; $i++) { 
            $assignment[$i] = [
                "value" => $memberOfDivision[$i]->employe_id,
                "label" => $memberOfDivision[$i]->first_name,
            ];
        }

        return response()->json([
            "status" =>true,
            "data" => $assignment
        ], 200);
    }

}
