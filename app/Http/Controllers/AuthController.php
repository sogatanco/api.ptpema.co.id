<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use JWTAuth;
use App\Http\Resources\UserResource;
use App\Http\Requests\UserRegisterRequest;
use App\Models\User;
use App\Models\ForgotPassword;
use App\Models\Employe;
use App\Mail\ForgotPasswordMail;
use Carbon\Carbon;
use Mail;

class AuthController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api', ['except' => ['login', 'register', 'welcome', 'refresh', 'forgotPassword', 'checkToken', 'newPassword']]);
    }

    public function register(userRegisterRequest $request): userResource
    {
        $data = $request->validated();

        if(User::where('email', $data['email'])->count() == 1){
            // user already exist
            throw new HttpResponseException(response([
                "errors" => [
                    "email" => [
                        "Email already registered."
                    ]
                ]
            ], 409));
        }

        $defaultRole = ["Employee"];

        $user = new User($data);
        $user->roles = $defaultRole;
        $user->password = Hash::make($data['password']);

        $user->save();

        return new UserResource($user);

        // $validator = Validator::make($request->all(), [
        //     'email' => ['required' , 'email' , 'unique:users'],
        //     'password' => ['required' , 'min:8' , 'confirmed']
        // ]);

        // if($validator->fails()){
        //     return response()->json([
        //         "status" => false,
        //         "message" => $validator->errors()
        //     ], 400);
        // }

        // $defaultRole = array("Employee");

        // $user = User::create([
        //     'email' => $request->email,
        //     'password' => Hash::make($request->password),
        //     'roles' => $defaultRole
        // ]);

        // return response()->json([
        //     "message" => "User has been registered.",
        //     "data" => $user
        // ], 201);
    }

    public function login(Request $request) 
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email'],
            'password' => ['required']
        ]);

        if($validator->fails()){
            throw new HttpResponseException(response([
                "message" => $validator->errors()
            ], 400));
        }

        // checking & generate token
        $token = Auth::attempt(['email' => $request->email, 'password' => $request->password]);

        if(!$token){
            throw new HttpResponseException(response([
                "status" => false,
                "message" => "Email or password is invalid."
            ], 400));
        }

        $user = Auth::user();
        $userData = Employe::select('employe_id', 'first_name', 'employe_active')
                    ->where("user_id", $user->id)->first();

        if($userData->employe_active == 0){
            throw new HttpResponseException(response([
                "status" => false,
                "message" => "Your account has been deactivated."
            ], 400));
        }

        $user->employe_id = $userData->employe_id;
        $user->first_name = $userData->first_name;
        $user->roles = $user->roles;
        $user = $user->makeHidden(["id", "email_verified_at", "created_at", "updated_at"]);

        // get payload data
        // $payload = Auth::payload();

        return response()->json([
            "status" => true,
            "message" => "Login success.",
            "auth" => [
                "user" => $user,
                "token" => $token,
            ]
        ], 200);
    }

    public function loginSso(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email'],
        ]);

        if($validator->fails()){
            throw new HttpResponseException(response([
                "message" => $validator->errors()
            ], 400));
        }

        // checking & generate token
        $token = Auth::attempt(['email' => $request->email]);

        if(!$token){
            throw new HttpResponseException(response([
                "status" => false,
                "message" => "Email or password is invalid."
            ], 400));
        }

        $user = Auth::user();
        $userData = Employe::select('employe_id', 'first_name', 'employe_active')
                    ->where("user_id", $user->id)->first();

        if($userData->employe_active == 0){
            throw new HttpResponseException(response([
                "status" => false,
                "message" => "Your account has been deactivated."
            ], 400));
        }

        $user->employe_id = $userData->employe_id;
        $user->first_name = $userData->first_name;
        $user->roles = $user->roles;
        $user = $user->makeHidden(["id", "email_verified_at", "created_at", "updated_at"]);

        return response()->json([
            "status" => true,
            "message" => "Login success.",
            "auth" => [
                "user" => $user,
                "token" => $token,
            ]
        ], 200);
    }

    public function logout()
    {
        Auth::logout();

        return response()->json([
            "status" => true,
            "message" => "Logout success."
        ], 200);
    }

    public function refresh()
    {
        $token = Auth::refresh();
        $user = Auth::user();

        return response()->json([
            "status" => true,
            "message" => "Refresh token success.",
            "auth" => [
                'user' => $user,
                'token' => $token
            ]
        ], 200);
    }

    public function welcome()
    {
        return response()->json([
            "messsage" => "Hello world!"
        ], 200);
    }

    public function changePas(Request $request){
        $u=User::find(Auth::user()->id);
        $u->password = Hash::make($request->newpas);
        if($u->save()){
            return response()->json([
                "messsage" => "Password updated succesfully"
            ], 200);
        }
    }

    public function forgotPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $emailIsExist = User::where('email', $request->email)->count();

        if ($emailIsExist == 0) {
            throw new HttpResponseException(response([
                "status" => false,
                "message" => "Email tidak terdaftar"
            ], 404));
        }
        
        $requestIsExist = ForgotPassword::where('email', $request->email)->count();
        
        if ($requestIsExist > 0) {
            ForgotPassword::where('email', $request->email)->delete();
        }

        $hashedKey = Hash::make($request->email);

        $key = str_replace('/', '', $hashedKey);;

        $newForgotPassword = new ForgotPassword();
        $newForgotPassword->email = $request->email;
        $newForgotPassword->token = $key;
        $newForgotPassword->save();

        $mailData = [
            'site_name' => 'Pema Information System',
            'link' => 'https://sys.ptpema.co.id/auth/new-password?&key=' . $key,
            'email' => $request->email
        ];

        $emailSent = Mail::to($request->email)->send(new ForgotPasswordMail($mailData));

        if (!$emailSent) {
            throw new HttpResponseException(response([
                "status" => false,
                "message" => "Gagal mengirim email"
            ], 500));
        }
        
        return response()->json([
            "messsage" => "Email sent succesfully",
        ], 200);
    }

    public function checkToken($token)
    {
        $reqData = ForgotPassword::where('token', $token)->first();

        if ($reqData == null) {
            throw new HttpResponseException(response([
                "status" => false,
                "message" => "Invalid token"
            ], 404));
        }

        $createdAt = $reqData->created_at;
        $expirationTime = $createdAt->copy()->addHour();
        $currentTimestamp = Carbon::now();

        if ($currentTimestamp->greaterThan($expirationTime)) {
            throw new HttpResponseException(response([
                "status" => false,
                "message" => "Invalid token",
                "isExpired" => true,
            ], 404));
        } 

        return response()->json([
            "status" => true,
        ], 200);
    }

    public function newPassword(Request $request)
    {
        $request->validate([
            'password' => 'required|min:8',
            'confirmPassword' => 'required',
            'token' => 'required'
        ]);

        $email = ForgotPassword::where('token', $request->token)->first()->email;

        $user = User::where('email', $email)->first();
        $user->password = Hash::make($request->password);
        $isSaved = $user->save();

        if (!$isSaved) {
            throw new HttpResponseException(response([
                "status" => false,
                "message" => "Gagal merubah password"
            ], 500));
        }

        ForgotPassword::where('token', $request->token)->delete();

        return response()->json([
            "message" => "Password changed successfully"
        ], 200);
    }
}
