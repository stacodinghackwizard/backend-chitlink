<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ThriftContributor extends Model
{
    use HasFactory;

    protected $fillable = [
        'thrift_package_id',
        'contact_id',
        'status',
    ];

    public function thriftPackage()
    {
        return $this->belongsTo(ThriftPackage::class);
    }

    public function contact()
    {
        return $this->belongsTo(Contact::class);
    }

    public function slots()
    {
        return $this->hasMany(ThriftSlot::class, 'contributor_id');
    }

    public function transactions()
    {
        return $this->hasMany(ThriftTransaction::class, 'contributor_id');
    }

    public function getProfileImageUrlAttribute()
    {
        if ($this->profile_image) {
            return asset('storage/' . $this->profile_image);
        }
        return null;
    }
} 