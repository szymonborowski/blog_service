<?php

namespace Database\Factories;

use App\Models\Media;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class MediaFactory extends Factory
{
    protected $model = Media::class;

    public function definition(): array
    {
        $uuid = (string) Str::uuid();

        return [
            'uuid' => $uuid,
            'filename' => $this->faker->word() . '.jpg',
            'disk' => 'public',
            'path' => 'media/2026/04/' . $uuid . '.jpg',
            'mime_type' => 'image/jpeg',
            'size' => $this->faker->numberBetween(10000, 5000000),
            'width' => 1920,
            'height' => 1080,
            'variants' => [
                'thumbnail' => 'media/2026/04/' . $uuid . '_thumbnail.webp',
                'medium' => 'media/2026/04/' . $uuid . '_medium.webp',
                'large' => 'media/2026/04/' . $uuid . '_large.webp',
            ],
            'alt' => $this->faker->sentence(3),
        ];
    }

    public function svg(): static
    {
        return $this->state([
            'mime_type' => 'image/svg+xml',
            'width' => null,
            'height' => null,
            'variants' => null,
        ]);
    }
}
