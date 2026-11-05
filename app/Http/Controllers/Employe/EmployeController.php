<?php

namespace App\Http\Controllers\Employe;

use App\Http\Controllers\Controller;
use App\Models\Hr\Division as HrDivision;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use App\Models\Employe;
use App\Models\Structure;
use App\Models\Notification;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

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

    // Karyawan dikelola di HR. Di apam hanya akun login (users) yang dibuat, ditautkan lewat users.employe_id.
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'employe_id' => ['required', 'unique:users,employe_id'],
            'email' => ['required', 'unique:users,email'],
        ]);

        if($validator->fails()){
            return response()->json([
                'status' => false,
                "errors" => $validator->errors()
            ]);
        }

        if(!Employe::where('employe_id', $request->employe_id)->exists()){
            return response()->json([
                "status" => false,
                "message" => "Employe ID not found in HR."
            ], 404);
        }

        $newUser = new User();
        $newUser->email = $request->email;
        $newUser->employe_id = $request->employe_id;
        $newUser->password = Hash::make(\Illuminate\Support\Str::random(40)); // no shared default password; first sign-in goes through forgot-password
        $newUser->roles = ["Employee"];

        if(!$newUser->save()){
            return response()->json([
                "status" => false,
                "message" => "Failed to create user."
            ], 500);
        }

        return response()->json([
            "status" => true,
            'message' => "Employe data has been created.",
        ], 200);
    }

    public function update(Request $request, $employe_id)
    {
        // Nama, jabatan, dan status karyawan dibaca langsung dari HR; tidak ada data karyawan di apam yang perlu diubah.
        return response()->json([
            "status" => true,
            "message" => "Data karyawan dikelola di HR; tidak ada perubahan di apam.",
         ], 200);
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
        // Karyawan dikelola di HR (nonaktif/hapus di HR); riwayat dan akun login di apam dibiarkan.
        return response()->json([
            "status" => true,
            "message" => "Data karyawan dikelola di HR; tidak ada perubahan di apam."
        ], 200);
    }

    public function getEmployeDivision($employe_id)
    {
        $position = Employe::where('employees_hr.employe_id', '=' , $employe_id)
                    ->leftJoin(config('database.connections.hr.database').'.positions as positions', 'positions.id', '=', 'employees_hr.position_id')
                    ->leftJoin(HrDivision::organizationsTable(), 'organizations.organization_id', '=', 'positions.division_id')
                    ->select('positions.id as position_id', 'positions.position_name', 'organizations.organization_id', 'organizations.organization_name')
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

    public function assignmentList(Request $request)
    {
        $userRoles = Auth::user()->roles;
        $query = $request->query('search');
        if($query === 'all'){

            $list = Employe::select('employees_hr.employe_id', 'first_name', 'users.roles')
                    ->join('users', 'users.id', '=', 'employees_hr.user_id')
                    ->where('employees_hr.employe_active', 1)
                    ->get();

        }elseif($query === 'subordinate'){
            // LIST ASSIGN UNTUK SUB AKTIFITAS
            // LIST BAWAHAN AJA
            $employeId = Employe::employeId();

            $list = Structure::select('struktur_lengkap_oke.employe_id', 'first_name', 'users.roles')
                    ->join('users', 'users.id', '=', 'struktur_lengkap_oke.user_id')
                    ->where('struktur_lengkap_oke.direct_atasan', $employeId)
                    ->get();

        }elseif($query === 'activity'){
            // LIST ASSIGN UNTUK AKTIFITAS
            if(in_array("Manager", $userRoles)){
                // JIKA USER ADALAH MANAGER
                // LIST MANAGER SUPERVISOR DAN STAFF
                $list = Employe::select('employees_hr.employe_id', 'first_name', 'users.roles')
                ->join('users', 'users.id', '=', 'employees_hr.user_id')
                ->where('users.roles', 'like' , '%Manager%')
                ->orWhere('users.roles', 'like' , '%Supervisor%')
                ->orWhere('users.roles', 'like' , '%Staff%')
                ->get();

            }elseif(in_array("Supervisor", $userRoles)){
                // JIKA USER ADALAH SUPERVISOR
                // LIST SUPERVISOR DAN STAFF
                $list = Employe::select('employees_hr.employe_id', 'first_name', 'users.roles')
                    ->join('users', 'users.id', '=', 'employees_hr.user_id')
                    ->where('users.roles', 'like' , '%Supervisor%')
                    ->orWhere('users.roles', 'like' , '%Staff%')
                    ->get();
            }

        }else{
            // LIST ASSIGN UNTUK SASARAN DAN TARGET
            if(in_array("Manager", $userRoles)){
                // JIKA USER ADALAH MANAGER
                // LIST SELEVEL MANAGER
                $list = Employe::select('employees_hr.employe_id', 'first_name', 'users.roles')
                        ->join('users', 'users.id', '=', 'employees_hr.user_id')
                        ->where('users.roles', 'like' , '%Manager%')
                        ->get();

            }elseif(in_array("Supervisor", $userRoles)){
                // JIKA USER ADALAH SUPERVISOR
                $list = Employe::select('employees_hr.employe_id', 'first_name', 'users.roles')
                    ->join('users', 'users.id', '=', 'employees_hr.user_id')
                    ->where('users.roles', 'like' , '%Supervisor%')
                    ->get();
            }
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
                    'employees_hr.first_name',
                    'project_notifications.created_at'
                )
                ->where(['project_notifications.to_employe' => $employeId, 'read_status' => 0])
                ->join('employees_hr', 'employees_hr.employe_id', '=', 'project_notifications.from_employe')
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
