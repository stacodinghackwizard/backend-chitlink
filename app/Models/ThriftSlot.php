<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ThriftSlot extends Model
{
    use HasFactory;

    protected $fillable = [
        'thrift_package_id',
        'contributor_id',
        'slot_no',
        'status',
    ];

    public function thriftPackage()
    {
        return $this->belongsTo(ThriftPackage::class);
    }

    public function contributor()
    {
        return $this->belongsTo(ThriftContributor::class);
    }

    public function transactions()
    {
        return $this->hasMany(ThriftTransaction::class, 'slot_id');
    }

    public function getProfileImageUrlAttribute()
    {
        if ($this->profile_image) {
            return asset('storage/' . $this->profile_image);
        }
        return null;
    }
} 