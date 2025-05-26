<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Merchant extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, HasApiTokens, Notifiable;

    protected $fillable = [
        
        'business_name',
        'email',
        'phone_number',
        'address',
        'reg_number',
        'cac_certificate',
        'password',
        'nin',
        'bvn',
        'utility_bill_path',
        'password_reset_code',
        'password_reset_expires_at',
        'password_reset_token',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];
}
