<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;


class UserVendor extends Authenticatable implements JWTSubject
{
    protected $connection ='mysql2';
    protected $table = "users";
    protected $primaryKey = "id";
    protected $keyType = "int";
    public $timestamps = true;
    public $incrementing = true;

    protected $fillable = [
        'email',
        'password',
        'roles',
        'is_email_verified'

    ];

    protected $casts = [
        'roles' => 'array',
        'password' => 'hashed',
    ];
    
    protected $hidden = [
        'password',
    ];


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
