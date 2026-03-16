<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'Slide',
    title: 'Slide',
    description: 'Hero slider slide resource',
    type: 'object',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'title', type: 'string', example: 'Welcome to my portfolio'),
        new OA\Property(property: 'type', type: 'string', enum: ['image', 'html'], example: 'image'),
        new OA\Property(property: 'image_url', type: 'string', nullable: true, example: 'https://example.com/slide.jpg'),
        new OA\Property(property: 'html_content', type: 'string', nullable: true),
        new OA\Property(property: 'link_url', type: 'string', nullable: true, example: 'https://example.com'),
        new OA\Property(property: 'link_text', type: 'string', nullable: true, example: 'Learn more'),
        new OA\Property(property: 'position', type: 'integer', example: 0),
        new OA\Property(property: 'is_active', type: 'boolean', example: true),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
    ]
)]
class SlideResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'type' => $this->type,
            'image_url' => $this->image_url,
            'html_content' => $this->html_content,
            'link_url' => $this->link_url,
            'link_text' => $this->link_text,
            'position' => $this->position,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
