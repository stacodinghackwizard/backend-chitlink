<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ThriftPackageApplication extends Model
{
    use HasFactory;

    protected $fillable = [
        'thrift_package_id',
        'user_id',
        'status',
        'responded_at',
    ];

    public function thriftPackage()
    {
        return $this->belongsTo(ThriftPackage::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
} 