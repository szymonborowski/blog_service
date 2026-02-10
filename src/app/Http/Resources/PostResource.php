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
        new OA\Property(property: 'uuid', type: 'string', format: 'uuid', example: '550e8400-e29b-41d4-a716-446655440000'),
        new OA\Property(property: 'author_id', type: 'integer', example: 1),
        new OA\Property(property: 'author', type: 'object', properties: [
            new OA\Property(property: 'id', type: 'integer'),
            new OA\Property(property: 'name', type: 'string'),
            new OA\Property(property: 'email', type: 'string'),
        ]),
        new OA\Property(property: 'title', type: 'string', example: 'My First Post'),
        new OA\Property(property: 'slug', type: 'string', example: 'my-first-post'),
        new OA\Property(property: 'excerpt', type: 'string', nullable: true, example: 'Short summary...'),
        new OA\Property(property: 'content', type: 'string', example: 'Full post content...'),
        new OA\Property(property: 'status', type: 'string', enum: ['draft', 'published', 'archived'], example: 'published'),
        new OA\Property(property: 'published_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'categories', type: 'array', items: new OA\Items(properties: [
            new OA\Property(property: 'id', type: 'integer'),
            new OA\Property(property: 'name', type: 'string'),
            new OA\Property(property: 'slug', type: 'string'),
        ])),
        new OA\Property(property: 'tags', type: 'array', items: new OA\Items(properties: [
            new OA\Property(property: 'id', type: 'integer'),
            new OA\Property(property: 'name', type: 'string'),
            new OA\Property(property: 'slug', type: 'string'),
        ])),
        new OA\Property(property: 'comments_count', type: 'integer', example: 5),
    ]
)]
class PostResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'author_id' => $this->author_id,
            'author' => $this->whenLoaded('author', function () {
                return [
                    'id' => $this->author->user_id,
                    'name' => $this->author->name,
                    'email' => $this->author->email,
                ];
            }),
            'title' => $this->title,
            'slug' => $this->slug,
            'excerpt' => $this->excerpt,
            'content' => $this->content,
            'status' => $this->status,
            'published_at' => $this->published_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
            'categories' => $this->whenLoaded('categories', function () {
                return $this->categories->map(fn($cat) => [
                    'id' => $cat->id,
                    'name' => $cat->name,
                    'slug' => $cat->slug,
                ]);
            }),
            'tags' => $this->whenLoaded('tags', function () {
                return $this->tags->map(fn($tag) => [
                    'id' => $tag->id,
                    'name' => $tag->name,
                    'slug' => $tag->slug,
                ]);
            }),
            'comments_count' => $this->whenCounted('comments'),
        ];
    }
}
