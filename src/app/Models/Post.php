<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Post extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'author_id',
        'title',
        'slug',
        'excerpt',
        'content',
        'cover_image',
        'status',
        'locale',
        'version',
        'published_at',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'version'      => 'integer',
    ];

    protected static function booted(): void
    {
        static::updating(function (Post $post) {
            $post->version = $post->getOriginal('version', 1) + 1;
        });
    }

    public function author()
    {
        return $this->belongsTo(Author::class, 'author_id', 'user_id');
    }

    public function tags()
    {
        return $this->belongsToMany(Tag::class);
    }

    public function categories()
    {
        return $this->belongsToMany(Category::class);
    }

    public function comments()
    {
        return $this->hasMany(Comment::class);
    }

    public function scopePublished($query)
    {
        return $query
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }
}
