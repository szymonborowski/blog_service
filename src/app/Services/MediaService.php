<?php

namespace App\Services;

use App\Models\Media;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class MediaService
{
    private const VARIANTS = [
        'thumbnail' => 150,
        'medium' => 768,
        'large' => 1200,
    ];

    private ImageManager $imageManager;

    public function __construct()
    {
        $this->imageManager = new ImageManager(new Driver());
    }

    public function upload(UploadedFile $file, ?string $alt = null): Media
    {
        $uuid = (string) Str::uuid();
        $extension = $file->getClientOriginalExtension();
        $directory = 'media/' . date('Y') . '/' . date('m');
        $filename = $uuid . '.' . $extension;
        $path = $directory . '/' . $filename;

        Storage::disk('public')->putFileAs($directory, $file, $filename);

        $width = null;
        $height = null;
        $variants = [];

        if ($this->isImage($file->getMimeType())) {
            $image = $this->imageManager->read($file->getPathname());
            $width = $image->width();
            $height = $image->height();
            $variants = $this->generateVariants($image, $directory, $uuid, $width);
        }

        return Media::create([
            'uuid' => $uuid,
            'filename' => $file->getClientOriginalName(),
            'disk' => 'public',
            'path' => $path,
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'width' => $width,
            'height' => $height,
            'variants' => $variants ?: null,
            'alt' => $alt,
        ]);
    }

    public function delete(Media $media): void
    {
        $disk = Storage::disk($media->disk);

        $disk->delete($media->path);

        if ($media->variants) {
            foreach ($media->variants as $variantPath) {
                $disk->delete($variantPath);
            }
        }

        $media->delete();
    }

    private function generateVariants($image, string $directory, string $uuid, int $originalWidth): array
    {
        $variants = [];

        foreach (self::VARIANTS as $name => $targetWidth) {
            if ($targetWidth >= $originalWidth) {
                continue;
            }

            $variantPath = $directory . '/' . $uuid . '_' . $name . '.webp';

            $variant = clone $image;
            $encoded = $variant
                ->scaleDown(width: $targetWidth)
                ->toWebp(quality: 80);

            Storage::disk('public')->put($variantPath, (string) $encoded);

            $variants[$name] = $variantPath;
        }

        return $variants;
    }

    private function isImage(string $mimeType): bool
    {
        return str_starts_with($mimeType, 'image/') && $mimeType !== 'image/svg+xml';
    }
}
