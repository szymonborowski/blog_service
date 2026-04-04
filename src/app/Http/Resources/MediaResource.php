<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MediaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $variantUrls = [];
        if ($this->variants) {
            foreach ($this->variants as $size => $path) {
                $variantUrls[$size] = $this->variantUrl($size);
            }
        }

        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'filename' => $this->filename,
            'mime_type' => $this->mime_type,
            'size' => $this->size,
            'width' => $this->width,
            'height' => $this->height,
            'url' => $this->url,
            'variant_urls' => $variantUrls,
            'alt' => $this->alt,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
