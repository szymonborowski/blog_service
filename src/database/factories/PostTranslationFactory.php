<?php

namespace Database\Factories;

use App\Models\PostTranslation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PostTranslation>
 */
class PostTranslationFactory extends Factory
{
    protected $model = PostTranslation::class;

    public function definition(): array
    {
        return [
            'locale'  => 'pl',
            'title'   => fake()->sentence(),
            'excerpt' => fake()->paragraph(2),
            'content' => fake()->paragraphs(5, true),
            'version' => 1,
        ];
    }

    public function locale(string $locale): static
    {
        return $this->state(fn (array $attributes) => ['locale' => $locale]);
    }
}
