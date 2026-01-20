<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class OldStudent extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'email',
        'address',
        'dob',
        'gender',
        'nrc_number',
        'partner_name',
        'job',
        'photo',
    ];

    protected $casts = [
        'dob' => 'date',
    ];
}
