<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganizationMember extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'nrc_number',
        'gender',
        'image',
        'dob',
        'address',
        'contact_number',
        'email',
        'agreed_to_rules',
        'status',
        'approved_at',
        'approved_by',
    ];

    protected $casts = [
        'dob' => 'date',
        'agreed_to_rules' => 'boolean',
        'approved_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
