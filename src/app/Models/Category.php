<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Laravel\Scout\Searchable;

class Category extends Model
{
    use HasFactory, Searchable;

    public $timestamps = false;

    protected $fillable = [
        'name',
        'slug',
        'color',
        'icon',
        'parent_id',
    ];

    public function parent()
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    public function posts()
    {
        return $this->belongsToMany(Post::class);
    }

    public function toSearchableArray(): array
    {
        return [
            'id'    => $this->id,
            'name'  => $this->name,
            'slug'  => $this->slug,
            'color' => $this->color,
            'icon'  => $this->icon,
        ];
    }
}
