<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'Tag',
    title: 'Tag',
    description: 'Blog tag resource',
    type: 'object',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'name', type: 'string', example: 'Laravel'),
        new OA\Property(property: 'slug', type: 'string', example: 'laravel'),
        new OA\Property(property: 'posts_count', type: 'integer', example: 15),
    ]
)]
class TagResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'posts_count' => $this->whenCounted('posts'),
        ];
    }
}
