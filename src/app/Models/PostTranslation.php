<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PostTranslation extends Model
{
    use HasFactory;

    protected $fillable = [
        'post_id',
        'locale',
        'title',
        'excerpt',
        'content',
        'version',
    ];

    protected $casts = [
        'version' => 'integer',
    ];

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }
}
