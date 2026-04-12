<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;
use Laravel\Scout\Searchable;

class Post extends Model
{
    use HasFactory, SoftDeletes, Searchable;

    protected $fillable = [
        'uuid',
        'author_id',
        'slug',
        'cover_image',
        'status',
        'published_at',
    ];

    protected $casts = [
        'published_at' => 'datetime',
    ];

    public function translations(): HasMany
    {
        return $this->hasMany(PostTranslation::class);
    }

    public function translation(string $locale): ?PostTranslation
    {
        return $this->translations->firstWhere('locale', $locale);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(Author::class, 'author_id', 'user_id');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class);
    }

    public function comments(): HasMany
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

    // -------------------------------------------------------------------------
    // Laravel Scout — Meilisearch
    // -------------------------------------------------------------------------

    public function toSearchableArray(): array
    {
        $this->loadMissing(['translations', 'categories', 'tags']);

        return [
            'id'           => $this->id,
            'slug'         => $this->slug,
            'title'        => $this->translations->pluck('title')->join(' | '),
            'excerpt'      => $this->translations
                ->map(fn ($t) => Str::limit(strip_tags($t->excerpt ?? ''), 300))
                ->join(' | '),
            'content'      => Str::limit(
                strip_tags($this->translations->pluck('content')->join(' ')),
                3000
            ),
            'categories'   => $this->categories->pluck('name')->toArray(),
            'tags'         => $this->tags->pluck('name')->toArray(),
            'cover_image'  => $this->cover_image,
            'published_at' => $this->published_at?->timestamp,
        ];
    }

    public function shouldBeSearchable(): bool
    {
        return $this->status === 'published'
            && $this->published_at !== null
            && $this->published_at <= now()
            && is_null($this->deleted_at);
    }

    protected function makeAllSearchableUsing(Builder $query): Builder
    {
        return $query->with(['translations', 'categories', 'tags']);
    }
}
