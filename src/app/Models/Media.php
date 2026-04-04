<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Media extends Model
{
    use HasFactory;
    protected $fillable = [
        'uuid',
        'filename',
        'disk',
        'path',
        'mime_type',
        'size',
        'width',
        'height',
        'variants',
        'alt',
    ];

    protected $casts = [
        'variants' => 'array',
        'size' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
    ];

    public function getUrlAttribute(): string
    {
        return $this->buildMediaUrl($this->path);
    }

    public function variantUrl(string $size): ?string
    {
        $path = $this->variants[$size] ?? null;

        return $path ? $this->buildMediaUrl($path) : null;
    }

    private function buildMediaUrl(string $path): string
    {
        $cdnUrl = config('services.cdn.url');

        if ($cdnUrl) {
            return rtrim($cdnUrl, '/') . '/' . ltrim($path, '/');
        }

        return Storage::disk($this->disk)->url($path);
    }
}
