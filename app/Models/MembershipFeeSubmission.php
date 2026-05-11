<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MembershipFeeSubmission extends Model
{
    protected $fillable = [
        'member_id',
        'fee_year',
        'fee_month',
        'slip_image',
        'claimed_payment_date',
        'amount_mmk',
        'status',
        'was_late',
        'reviewed_at',
        'reviewed_by',
        'rejection_reason',
    ];

    protected $casts = [
        'claimed_payment_date' => 'date',
        'reviewed_at' => 'datetime',
        'was_late' => 'boolean',
    ];

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
