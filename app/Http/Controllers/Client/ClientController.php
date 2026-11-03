<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Employe;
use App\Models\User;
use App\Models\Position;
use App\Models\Structure;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Exceptions\HttpResponseException;

class ClientController extends Controller
{
    public function index(Request $request)
    {
        return response()->json([
            "status" => true,
            "message" => "Hello World",
        ], 200);
    }

    public function employees()
    {

        $list = Employe::select('employees_hr.*', 'users.email', 'users.password', 'positions.position_name')
                ->join('users', 'users.id', '=', 'employees_hr.user_id')
                ->leftJoin(config('database.connections.hr.database').'.positions as positions', 'positions.id', '=', 'employees_hr.position_id')
                ->get();

        $total = $list->count();
        return response()->json([
            "status" => true,
            "total" => $total,
            "data" => $list
        ], 200);
    }

    public function store(Request $request)
    {

        $userIsExist = User::where('email', $request->email)->count();
        $employeInHr = Employe::where('employe_id', $request->employe_id)->count();
        $userForEmploye = User::where('employe_id', $request->employe_id)->count();

        if($userIsExist > 0){
            throw new HttpResponseException(response([
                "status" => false,
                "message" => "Email already registered."
            ], 409));
        }

        if($employeInHr == 0){
            throw new HttpResponseException(response([
                "status" => false,
                "message" => "Employe ID not found in HR."
            ], 404));
        }

        if($userForEmploye > 0){
            throw new HttpResponseException(response([
                "status" => false,
                "message" => "Employe ID already registered."
            ], 409));
        }

        // Karyawan sudah ada di HR; apam hanya membuat akun login yang ditautkan lewat users.employe_id
        $newUser = new User();
        $newUser->email = $request->email;
        $newUser->employe_id = $request->employe_id;
        $newUser->password = Hash::make(\Illuminate\Support\Str::random(40)); // no shared default password; first sign-in goes through forgot-password
        $newUser->roles = ["Employee"];

        if($newUser->save()){
            return response()->json([
                "status" => true,
                "data" => "Success to create new employe.",
            ], 200);
        }

        throw new HttpResponseException(response([
            "status" => false,
            "message" => "Failed to create new user."
        ], 500));
    }

    public function stucture()
    {
        $structure = Structure::all();

        return response()->json([
            "status" => true,
            'total' => count($structure),
            'data' => $structure 
        ], 200);
    }
}
