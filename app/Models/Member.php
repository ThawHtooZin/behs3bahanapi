<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Member extends Model
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
        'parent_name',
        'parent_occupation',
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

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function feeSubmissions(): HasMany
    {
        return $this->hasMany(MembershipFeeSubmission::class);
    }

    public function familyMembers(): HasMany
    {
        return $this->hasMany(MemberFamilyMember::class);
    }
}
