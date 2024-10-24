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
use App\Models\UserVendor;
use App\Models\User;
use App\Models\Vendor\ViewPerusahaan;
use App\Models\Vendor\Perusahaan;
use App\Models\Vendor\ForgotPassword;
use Mail;
use App\Mail\VendorMail;
use App\Mail\ForgotPasswordMail;
use App\Http\Resources\PostResource;
use Config;
use App\Http\Controllers\Notification\NotificationController;


class Auth2Controller extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api_vendor', ['except' => ['login', 'register', 'welcome', 'kirimEmail', 'verifEmail', 'forgotPassword']]);
    }

    public function register(userRegisterRequest $request): userResource
    {
        $data = $request->validated();

        if (UserVendor::where('email', $data['email'])->count() >= 1) {
            throw new HttpResponseException(response([
                "message" => "Email sudah terdaftar, silakan Login !"
            ], 409));
        }

        $defaultRole = ["Vendor"];

        $user = new UserVendor($data);
        $user->roles = $defaultRole;
        $user->password = Hash::make($data['password']);

        if ($user->save()) {
            $p = new Perusahaan();
            $p->user_id = $user->id;
            $p->bentuk_usaha = $data['bentuk_usaha'];
            $p->nama_perusahaan = $data['nama_perusahaan'];
            $p->tipe = $data['tipe'];

            if($request->pilihan_pengadaan === 'umum'){
                $p->status_verifikasi_umum = 'register';
                $p->umum_updated_at = date('Y-m-d H:i:s');
                $p->status_verifikasi_by = 'umum';
                $whereAdminRole = 'AdminVendorUmum';
            }else{
                $p->status_verifikasi_scm = 'register';
                $p->scm_updated_at = date('Y-m-d H:i:s');
                $p->status_verifikasi_by = 'scm';
                $whereAdminRole = 'AdminVendorScm';
            }

            $p->nomor_registrasi= 'PEMA-VEND-'.date('Y').'-'.date('m').'-'.rand(1000,9999);
            
            if ($p->save()) {
                if($this->kirimEmail($user->id)){

                    // create notification
                    $recipient = User::select('employees.employe_id')
                                ->join('employees', 'employees.user_id', '=', 'users.id')
                                ->where('roles', 'like', '%'.$whereAdminRole.'%')
                                ->first()
                                ->employe_id;

                    NotificationController::new('VENDOR_REGISTERED', $recipient, $p->id);

                    return new UserResource($user);
                }else{
                    throw new HttpResponseException(response([
                        "status"=>false,
                        "message" => "Failed To Register"
                    ], 409));
                }
            }else{
                throw new HttpResponseException(response([
                    "status"=>false,
                    "message" => "Failed To Register"
                ], 409));
            }
        }else{
            throw new HttpResponseException(response([
                "status"=>false,
                "message" => "Failed To Register"
            ], 409));
        }
    }

    public function login(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email'],
            'password' => ['required']
        ]);

        if ($validator->fails()) {
            throw new HttpResponseException(response([
                "message" => $validator->errors()
            ], 400));
        }
        $token = Auth::guard('api_vendor')->attempt(['email' => $request->email, 'password' => $request->password]);
        if (!$token) {
            throw new HttpResponseException(response([
                "status" => false,
                "message" => "Email or password is invalid."
            ], 400));
        }
        $user = Auth::guard('api_vendor')->user();

        $userCompany = Perusahaan::select('id')->where('user_id', $user->id)->first();
        $user['company_id'] = $userCompany->id;

        if ($user->is_email_verified === 0) {
            throw new HttpResponseException(response([
                "status" => false,
                "message" => "User not verified, Please Check your email for verification"
            ], 400));
        } else {
            return response()->json([
                "status" => true,
                "message" => "Login success.",
                "auth" => [
                    "user" => $user,
                    "token" => $token,
                ]
            ], 200);
        }
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
            "token" => $token
        ], 200);
    }

    public function welcome()
    {
        return response()->json([
            "messsage" => "Hello world!"
        ], 200);
    }

    function kirimEmail($id)
    {
        $per = ViewPerusahaan::where('id_user', $id)->get()->first();
        $digits = 10;
        $uniq = base64_encode((rand(pow(10, $digits - 1), pow(10, $digits) - 1)) . ($id + 45) . '-' . strtotime(now()));
        $mailData = [
            'link' => Config::get('app.url') . 'api/auth2/verif/' . $uniq,
            'company_name' => $per['bentuk_usaha'] . ' ' . $per['nama_perusahaan']
        ];
        if (Mail::to($per['email'])->send(new VendorMail($mailData))) {
            return view('emails.sentEmail')->with('email', $per['email']);
        }
    }

    /**
     * Handle verification email
     * 
     * @param string $id_token token sent via email
     * 
     * @return \Illuminate\Http\Response
     */
    function verifEmail($id_token)
    {
        $token_explode = explode("-", base64_decode($id_token));
        $id = substr($token_explode[0], 10);
        $timeRequest = $token_explode[1];
        if (round(abs(strtotime(now()) - $timeRequest) / 60, 2) > 10) {
            return view('emails.expiredToken')->with('link', Config::get('app.url') . 'api/auth2/resend/' . ($id - 45));
        } else {
            $uv = UserVendor::find($id - 45);
            $uv->is_email_verified = 1;
            if ($uv->save()) {
                return view('emails.verificationSuccess');
            }
        }
    }

    public function forgotPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $emailIsExist = UserVendor::where('email', $request->email)->count();

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

        $uniq = base64_encode((rand(pow(10, 5 - 1), pow(10, 5) - 1)) . '-' . strtotime(now()));

        $newForgotPassword = new ForgotPassword();
        $newForgotPassword->email = $request->email;
        $newForgotPassword->key = $uniq;
        $newForgotPassword->save();

        $mailData = [
            'site_name' => 'Integrated Vendor Database System (IVDS)',
            'link' => 'https://ivds.ptpema.co.id/auth?action=forgotpassword&key='.$uniq,
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
}
