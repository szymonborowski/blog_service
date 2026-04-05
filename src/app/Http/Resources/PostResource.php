<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'Post',
    title: 'Post',
    description: 'Blog post resource',
    type: 'object',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'uuid', type: 'string', format: 'uuid'),
        new OA\Property(property: 'author_id', type: 'integer', example: 1),
        new OA\Property(property: 'author', type: 'object', properties: [
            new OA\Property(property: 'id', type: 'integer'),
            new OA\Property(property: 'name', type: 'string'),
            new OA\Property(property: 'email', type: 'string'),
        ]),
        new OA\Property(property: 'title', type: 'string', nullable: true),
        new OA\Property(property: 'slug', type: 'string', example: 'my-first-post'),
        new OA\Property(property: 'excerpt', type: 'string', nullable: true),
        new OA\Property(property: 'content', type: 'string', nullable: true),
        new OA\Property(property: 'cover_image', type: 'string', nullable: true),
        new OA\Property(property: 'status', type: 'string', enum: ['draft', 'published', 'archived']),
        new OA\Property(property: 'locale', type: 'string', nullable: true, enum: ['pl', 'en']),
        new OA\Property(property: 'version', type: 'integer', nullable: true),
        new OA\Property(property: 'published_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'categories', type: 'array', items: new OA\Items(properties: [
            new OA\Property(property: 'id', type: 'integer'),
            new OA\Property(property: 'name', type: 'string'),
            new OA\Property(property: 'slug', type: 'string'),
            new OA\Property(property: 'color', type: 'string', nullable: true),
        ])),
        new OA\Property(property: 'tags', type: 'array', items: new OA\Items(properties: [
            new OA\Property(property: 'id', type: 'integer'),
            new OA\Property(property: 'name', type: 'string'),
            new OA\Property(property: 'slug', type: 'string'),
        ])),
        new OA\Property(property: 'comments_count', type: 'integer'),
    ]
)]
class PostResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Determine locale: from additional data (set by controller) or request param
        $locale = $this->additional['locale'] ?? $request->get('locale');

        $translation = $this->whenLoaded('translations', function () use ($locale) {
            if ($locale) {
                return $this->translations->firstWhere('locale', $locale)
                    ?? $this->translations->first();
            }
            return $this->translations->first();
        });

        return [
            'id'       => $this->id,
            'uuid'     => $this->uuid,
            'author_id' => $this->author_id,
            'author'   => $this->whenLoaded('author', fn () => [
                'id'    => $this->author->user_id,
                'name'  => $this->author->name,
                'email' => $this->author->email,
            ]),
            'title'    => $translation?->title,
            'slug'     => $this->slug,
            'excerpt'  => $translation?->excerpt,
            'content'  => $translation?->content,
            'cover_image' => $this->cover_image,
            'status'   => $this->status,
            'locale'   => $translation?->locale,
            'version'  => $translation?->version,
            'published_at' => $this->published_at?->toIso8601String(),
            'created_at'   => $this->created_at->toIso8601String(),
            'updated_at'   => $this->updated_at->toIso8601String(),
            'categories' => $this->whenLoaded('categories', fn () =>
                $this->categories->map(fn ($cat) => [
                    'id'    => $cat->id,
                    'name'  => $cat->name,
                    'slug'  => $cat->slug,
                    'color' => $cat->color,
                ])
            ),
            'tags' => $this->whenLoaded('tags', fn () =>
                $this->tags->map(fn ($tag) => [
                    'id'   => $tag->id,
                    'name' => $tag->name,
                    'slug' => $tag->slug,
                ])
            ),
            'comments_count' => $this->whenCounted('comments'),
            'available_locales' => $this->whenLoaded('translations', fn () =>
                $this->translations->pluck('locale')->toArray()
            ),
            'all_translations' => $this->whenLoaded('translations', fn () =>
                $this->translations->map(fn ($t) => [
                    'locale'  => $t->locale,
                    'title'   => $t->title,
                    'excerpt' => $t->excerpt,
                    'content' => $t->content,
                    'version' => $t->version,
                ])
            ),
        ];
    }
}
