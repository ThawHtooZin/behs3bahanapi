<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ForumPost extends Model
{
    protected $fillable = [
        'user_id',
        'category',
        'title',
        'description',
        'views_count',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(ForumComment::class, 'post_id');
    }

    public function rootComments(): HasMany
    {
        return $this->hasMany(ForumComment::class, 'post_id')->whereNull('parent_id');
    }

    public function viewRecords(): HasMany
    {
        return $this->hasMany(ForumPostView::class, 'post_id');
    }
}
