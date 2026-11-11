<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Models\Employe;


class User extends Authenticatable implements JWTSubject
{
    protected $table = "users";
    protected $primaryKey = "id";
    protected $keyType = "int";
    public $timestamps = true;
    public $incrementing = true;

    protected $fillable = [
        'email',
        'employe_id',
        'password',
        'roles',

    ];

    protected $casts = [
        'roles' => 'array',
        'password' => 'hashed',
    ];
    
    // protected $attributes = [
    //     'roles' => [],
    // ];   

    protected $hidden = [
        'password',
    ];

    protected static function booted(): void
    {
        // password diubah (ganti password, lupa password): dicatat untuk pengingat keamanan akun
        static::saved(function (User $u) {
            if (!$u->wasRecentlyCreated && $u->wasChanged('password')) {
                \App\Services\PengingatKeamanan::catatPasswordDiubah($u->employe_id);
            }
        });
    }

    /** pegawai pemilik akun masih aktif; hasilnya disimpan semenit agar tidak menambah query di setiap request */
    public static function masihAktif(int $userId): bool
    {
        return \Illuminate\Support\Facades\Cache::remember('akun_aktif:' . $userId, 60, function () use ($userId) {
            return (int) \Illuminate\Support\Facades\DB::table('employees_hr')->where('user_id', $userId)->value('employe_active') === 1;
        });
    }

    public function employe(): HasOne
    {
        return $this->hasOne(Employe::class, 'user_id', 'id ');
    }

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            "roles" => $this->roles
        ];
    }
 }
