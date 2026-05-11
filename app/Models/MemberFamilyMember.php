<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemberFamilyMember extends Model
{
    protected $fillable = [
        'member_id',
        'name',
        'relation',
        'dob',
        'nrc_number',
    ];

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }
}
