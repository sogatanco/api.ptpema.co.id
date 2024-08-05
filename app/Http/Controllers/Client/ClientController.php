<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Employe;
use App\Models\User;
use App\Models\Position;
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
        $list = Employe::select('employees.*', 'users.email')
                ->join('users', 'users.id', '=', 'employees.user_id')
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
        $employeIsExist = Employe::where('employe_id', $request->employe_id)->count();

        if($userIsExist > 0){
            throw new HttpResponseException(response([
                "status" => false,
                "message" => "Email already registered."
            ], 409));
        }
        
        if($employeIsExist > 0){
            throw new HttpResponseException(response([
                "status" => false,
                "message" => "Employe ID already registered."
            ], 409));
        }

        // create employe as user
        $newUser = new User();
        $newUser->email = $request->email;
        $newUser->password = Hash::make('asdasdasd');
        $newUser->roles = ["Employee"];

        $fileName = null;
        if($request->hasFile('img')){
            $file = $request->file('img');
            $file->storeAs('public/profile', $file->hashName());

            $fileName = $file->hashName();
        }

        if($newUser->save()){
            $newEmploye = new Employe();
            $newEmploye->employe_id = $request->employe_id;
            $newEmploye->user_id = $newUser->id;
            $newEmploye->as_pic = $request->as_pic;
            $newEmploye->position_id = $request->position_id;
            $newEmploye->first_name = $request->first_name;
            $newEmploye->last_name = $request->last_name;
            $newEmploye->gender = $request->gender;
            $newEmploye->religion = $request->religion;
            $newEmploye->birthday = $request->birthday;
            $newEmploye->birthday_place = $request->birthday_place;
            $newEmploye->marital_status = $request->marital_status;
            $newEmploye->img = $fileName;
            $newEmploye->employe_active = 1;

            if($newEmploye->save()){
                return response()->json([
                    "status" => true,
                    "data" => "Success to create new employe.",
                ], 200);
            }else{
                throw new HttpResponseException(response([
                    "status" => false,
                    "message" => "Failed to create new employe."
                ], 500));
            }
            
        }else{
            throw new HttpResponseException(response([
                "status" => false,
                "message" => "Failed to create new user."
            ], 500));
        }
    }

    public function positions()
    {
        $list = Position::select('position_id', 'position_name')
                ->get();
        $total = $list->count();
        return response()->json([
            "status" => true,
            "total" => $total,
            "data" => $list
        ], 200);
    }
}
