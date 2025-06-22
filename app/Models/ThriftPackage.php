<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ThriftPackage extends Model
{
    use HasFactory;

    protected $fillable = [
        'merchant_id',
        'name',
        'total_amount',
        'duration_days',
        'slots',
        'terms',
        'terms_accepted',
        'status',
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

    public function admins()
    {
        return $this->belongsToMany(User::class, 'thrift_admins');
    }

    public function getProfileImageUrlAttribute()
    {
        if ($this->profile_image) {
            return asset('storage/' . $this->profile_image);
        }
        return null;
    }
} 