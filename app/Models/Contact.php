<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Contact extends Model
{
    protected $fillable = [
        'merchant_id',
        'name',
        'email',
        'phone_number',
    ];
}
