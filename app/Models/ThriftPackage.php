<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ThriftPackage extends Model
{
    use HasFactory;

    protected $fillable = [
        'merchant_id',
        'user_id',
        'name',
        'total_amount',
        'duration_days',
        'slots',
        'terms',
        'terms_accepted',
        'status',
        'created_by_type',
        'created_by_id',
    ];

    public function merchant()
    {
        return $this->belongsTo(Merchant::class);
    }

    public function contributors()
    {
        return $this->hasMany(ThriftContributor::class);
    }

    public function slots()
    {
        return $this->hasMany(ThriftSlot::class);
    }

    public function transactions()
    {
        return $this->hasMany(ThriftTransaction::class);
    }

    public function userAdmins()
    {
        return $this->belongsToMany(User::class, 'thrift_admins', 'thrift_package_id', 'user_id');
    }

    public function merchantAdmins()
    {
        return $this->belongsToMany(Merchant::class, 'thrift_admins', 'thrift_package_id', 'merchant_id');
    }

    public function invites()
    {
        return $this->hasMany(ThriftPackageInvite::class);
    }

    public function applications()
    {
        return $this->hasMany(ThriftPackageApplication::class);
    }

    public function getProfileImageUrlAttribute()
    {
        if ($this->profile_image) {
            return asset('storage/' . $this->profile_image);
        }
        return null;
    }

    public function getUserIdAttribute()
    {
        return $this->created_by_type === 'user' ? $this->created_by_id : null;
    }
}