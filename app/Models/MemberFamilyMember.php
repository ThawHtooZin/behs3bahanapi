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
        'nrc_number',
        'occupation',
    ];

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }
}
