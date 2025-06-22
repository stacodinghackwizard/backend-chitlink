<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ThriftTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'thrift_package_id',
        'slot_id',
        'contributor_id',
        'amount',
        'type',
        'transacted_at',
    ];

    public function thriftPackage()
    {
        return $this->belongsTo(ThriftPackage::class);
    }

    public function slot()
    {
        return $this->belongsTo(ThriftSlot::class);
    }

    public function contributor()
    {
        return $this->belongsTo(ThriftContributor::class);
    }
} 