<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ForumCommentMention extends Model
{
    protected $fillable = [
        'comment_id',
        'mentioned_user_id',
    ];

    public function comment(): BelongsTo
    {
        return $this->belongsTo(ForumComment::class, 'comment_id');
    }

    public function mentionedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mentioned_user_id');
    }
}
