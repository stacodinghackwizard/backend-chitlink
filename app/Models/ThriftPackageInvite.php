<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ThriftPackageInvite extends Model
{
    use HasFactory;

    protected $fillable = [
        'thrift_package_id',
        'invited_user_id',
        'invited_by_id',
        'status',
        'responded_at',
    ];

    public function thriftPackage()
    {
        return $this->belongsTo(ThriftPackage::class);
    }

    public function invitedUser()
    {
        return $this->belongsTo(User::class, 'invited_user_id');
    }

    public function invitedBy()
    {
        return $this->belongsTo(User::class, 'invited_by_id');
    }

    public function invitedByMerchant()
    {
        return $this->belongsTo(Merchant::class, 'invited_by_merchant_id');
    }
} 