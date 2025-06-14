<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Contact extends Model
{
    use HasFactory;

    protected $fillable = [
        'merchant_id',
        'name',
        'email',
        'phone_number',
        'profile_image'
    ];

    protected $appends = ['profile_image_url'];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the merchant that owns the contact.
     */
    public function merchant()
    {
        return $this->belongsTo(Merchant::class);
    }

    /**
     * Get the groups that this contact belongs to.
     */
    public function groups()
    {
        return $this->belongsToMany(ContactGroup::class, 'contact_group_members', 'contact_id', 'contact_group_id')
                    ->withTimestamps();
    }

    /**
     * Get the group colors for this contact (for display)
     */
    public function getGroupColorsAttribute()
    {
        return $this->groups()->pluck('color')->toArray();
    }

    /**
     * Get the group names for this contact
     */
    public function getGroupNamesAttribute()
    {
        return $this->groups()->pluck('name')->toArray();
    }

    /**
     * Scope to filter by merchant
     */
    public function scopeForMerchant($query, $merchantId)
    {
        return $query->where('merchant_id', $merchantId);
    }

    public function getProfileImageUrlAttribute()
    {
        if ($this->profile_image) {
            return asset('storage/' . $this->profile_image);
        }
        return null;
    }
}