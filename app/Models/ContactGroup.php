<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContactGroup extends Model
{
    use HasFactory;

    protected $fillable = [
        'merchant_id',
        'name',
        'description',
        'color'
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the merchant that owns the contact group.
     */
    public function merchant()
    {
        return $this->belongsTo(Merchant::class);
    }

    /**
     * Get the contacts that belong to this group.
     */
    public function contacts()
    {
        return $this->belongsToMany(Contact::class, 'contact_group_members', 'contact_group_id', 'contact_id')
                    ->withTimestamps();
    }

    /**
     * Get the count of contacts in this group.
     */
    public function getContactsCountAttribute()
    {
        return $this->contacts()->count();
    }

    /**
     * Scope to filter by merchant
     */
    public function scopeForMerchant($query, $merchantId)
    {
        return $query->where('merchant_id', $merchantId);
    }
}