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
        'user_id',
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

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function getProfileImageUrlAttribute()
    {
        if ($this->profile_image) {
            return asset('storage/' . $this->profile_image);
        }
        return null;
    }

    public function toArray()
    {
        $array = parent::toArray();
        if ($this->user && (property_exists($this->user, 'user_id') || array_key_exists('user_id', $this->user->getAttributes()))) {
            $array['user_id'] = $this->user->user_id;
            if (isset($array['user']) && is_array($array['user']) && array_key_exists('id', $array['user'])) {
                unset($array['user']['id']);
            }
        }
        if ($this->contact) {
            $array['contact_id'] = $this->contact->id;
        }
        if ($this->thriftPackage && $this->thriftPackage->merchant && isset($this->thriftPackage->merchant->mer_id)) {
            $array['merchant_id'] = $this->thriftPackage->merchant->mer_id;
        }
        return $array;
    }
} 