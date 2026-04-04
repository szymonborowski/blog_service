<?php

namespace Tests\Unit;

use App\Models\Media;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MediaModelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    #[Test]
    public function url_accessor_returns_full_url(): void
    {
        $media = Media::factory()->create(['path' => 'media/2026/04/test.jpg']);

        $url = $media->url;

        $this->assertStringContainsString('media/2026/04/test.jpg', $url);
    }

    #[Test]
    public function variant_url_returns_url_for_existing_variant(): void
    {
        $media = Media::factory()->create([
            'variants' => [
                'thumbnail' => 'media/2026/04/abc_thumbnail.webp',
                'medium' => 'media/2026/04/abc_medium.webp',
            ],
        ]);

        $thumbnailUrl = $media->variantUrl('thumbnail');

        $this->assertNotNull($thumbnailUrl);
        $this->assertStringContainsString('abc_thumbnail.webp', $thumbnailUrl);
    }

    #[Test]
    public function variant_url_returns_null_for_nonexistent_variant(): void
    {
        $media = Media::factory()->create([
            'variants' => ['thumbnail' => 'media/2026/04/abc_thumbnail.webp'],
        ]);

        $this->assertNull($media->variantUrl('large'));
    }

    #[Test]
    public function variant_url_returns_null_when_no_variants(): void
    {
        $media = Media::factory()->create(['variants' => null]);

        $this->assertNull($media->variantUrl('thumbnail'));
    }

    #[Test]
    public function variants_cast_to_array(): void
    {
        $variants = [
            'thumbnail' => 'media/2026/04/abc_thumbnail.webp',
            'medium' => 'media/2026/04/abc_medium.webp',
        ];

        $media = Media::factory()->create(['variants' => $variants]);
        $media->refresh();

        $this->assertIsArray($media->variants);
        $this->assertEquals($variants, $media->variants);
    }

    #[Test]
    public function size_cast_to_integer(): void
    {
        $media = Media::factory()->create(['size' => 123456]);
        $media->refresh();

        $this->assertIsInt($media->size);
    }

    #[Test]
    public function url_uses_cdn_domain_when_configured(): void
    {
        config(['services.cdn.url' => 'https://cdn.example.com']);

        $media = Media::factory()->create(['path' => 'media/2026/04/test.jpg']);

        $this->assertEquals('https://cdn.example.com/media/2026/04/test.jpg', $media->url);
    }

    #[Test]
    public function variant_url_uses_cdn_domain_when_configured(): void
    {
        config(['services.cdn.url' => 'https://cdn.example.com']);

        $media = Media::factory()->create([
            'variants' => ['thumbnail' => 'media/2026/04/abc_thumbnail.webp'],
        ]);

        $this->assertEquals('https://cdn.example.com/media/2026/04/abc_thumbnail.webp', $media->variantUrl('thumbnail'));
    }

    #[Test]
    public function url_falls_back_to_storage_url_without_cdn(): void
    {
        config(['services.cdn.url' => null]);

        $media = Media::factory()->create(['path' => 'media/2026/04/test.jpg']);

        $this->assertStringContainsString('media/2026/04/test.jpg', $media->url);
        $this->assertStringNotContainsString('cdn.example.com', $media->url);
    }
}
