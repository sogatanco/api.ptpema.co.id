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
