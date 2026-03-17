<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeaturedPost extends Model
{
    protected $fillable = ['post_id', 'position'];

    protected $casts = ['position' => 'integer'];

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('position');
    }
}
