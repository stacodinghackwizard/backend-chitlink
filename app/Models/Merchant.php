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
        'name',
        'business_name',
        'email',
        'phone_number',
        'address',
        'reg_number',
        'cac_certificate',
        'password',
        'mer_id', // Add this new field
        'nin',
        'bvn',
        'utility_bill_path',
        'profile_image',
        'password_reset_code',
        'password_reset_expires_at',
        'password_reset_token',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Boot method to generate unique merchant ID
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($merchant) {
            if (empty($merchant->mer_id)) {
                $merchant->mer_id = self::generateUniqueMerchantId();
            }
        });
    }

    /**
     * Generate a unique merchant ID
     */
    private static function generateUniqueMerchantId()
    {
        do {
            $merchantId = 'MER' . str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
        } while (self::where('mer_id', $merchantId)->exists());

        return $merchantId;
    }
}