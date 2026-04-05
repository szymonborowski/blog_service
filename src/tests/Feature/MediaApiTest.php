<?php

namespace Tests\Feature;

use App\Models\Media;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MediaApiTest extends TestCase
{
    use RefreshDatabase;

    private string $apiKey = 'test-internal-key';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.internal.api_key' => $this->apiKey]);
        Storage::fake('public');
    }

    private function withKey(): array
    {
        return ['X-Internal-Api-Key' => $this->apiKey];
    }

    // --- Auth ---

    #[Test]
    public function rejects_request_without_api_key(): void
    {
        $this->getJson('/api/internal/media')->assertUnauthorized();
    }

    #[Test]
    public function rejects_request_with_wrong_api_key(): void
    {
        $this->getJson('/api/internal/media', ['X-Internal-Api-Key' => 'wrong'])
            ->assertUnauthorized();
    }

    // --- List media ---

    #[Test]
    public function can_list_media(): void
    {
        Media::factory()->count(3)->create();

        $this->getJson('/api/internal/media', $this->withKey())
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'uuid', 'filename', 'mime_type', 'size', 'width', 'height', 'url', 'variant_urls', 'alt', 'created_at'],
                ],
                'meta',
                'links',
            ])
            ->assertJsonCount(3, 'data');
    }

    #[Test]
    public function list_media_is_ordered_by_newest_first(): void
    {
        $old = Media::factory()->create(['created_at' => now()->subDay()]);
        $new = Media::factory()->create(['created_at' => now()]);

        $response = $this->getJson('/api/internal/media', $this->withKey())->assertOk();

        $this->assertEquals($new->id, $response->json('data.0.id'));
        $this->assertEquals($old->id, $response->json('data.1.id'));
    }

    #[Test]
    public function can_search_media_by_filename(): void
    {
        Media::factory()->create(['filename' => 'hero-banner.jpg']);
        Media::factory()->create(['filename' => 'avatar.png']);

        $response = $this->getJson('/api/internal/media?search=hero', $this->withKey())
            ->assertOk();

        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('hero-banner.jpg', $response->json('data.0.filename'));
    }

    #[Test]
    public function can_filter_media_by_mime_type(): void
    {
        Media::factory()->create(['mime_type' => 'image/jpeg']);
        Media::factory()->create(['mime_type' => 'image/png']);
        Media::factory()->create(['mime_type' => 'image/jpeg']);

        $response = $this->getJson('/api/internal/media?mime_type=image/png', $this->withKey())
            ->assertOk();

        $this->assertCount(1, $response->json('data'));
    }

    #[Test]
    public function media_list_is_paginated(): void
    {
        Media::factory()->count(30)->create();

        $response = $this->getJson('/api/internal/media?per_page=10', $this->withKey())
            ->assertOk();

        $this->assertCount(10, $response->json('data'));
        $this->assertEquals(30, $response->json('meta.total'));
    }

    // --- Upload media ---

    #[Test]
    public function can_upload_image(): void
    {
        $file = UploadedFile::fake()->image('photo.jpg', 1920, 1080);

        $response = $this->postJson('/api/internal/media', [
            'file' => $file,
            'alt' => 'A test photo',
        ], $this->withKey())
            ->assertCreated()
            ->assertJsonStructure([
                'data' => ['id', 'uuid', 'filename', 'mime_type', 'size', 'width', 'height', 'url', 'variant_urls', 'alt', 'created_at'],
            ]);

        $this->assertEquals('photo.jpg', $response->json('data.filename'));
        $this->assertEquals('A test photo', $response->json('data.alt'));
        $this->assertEquals('image/jpeg', $response->json('data.mime_type'));
        $this->assertDatabaseCount('media', 1);

        // Original file stored
        Storage::disk('public')->assertExists($response->json('data.url') ? Media::first()->path : '');
    }

    #[Test]
    public function upload_stores_original_file_on_disk(): void
    {
        $file = UploadedFile::fake()->image('test.png', 800, 600);

        $this->postJson('/api/internal/media', ['file' => $file], $this->withKey())
            ->assertCreated();

        $media = Media::first();
        Storage::disk('public')->assertExists($media->path);
    }

    #[Test]
    public function upload_generates_variants_for_large_images(): void
    {
        $file = UploadedFile::fake()->image('large.jpg', 2000, 1500);

        $this->postJson('/api/internal/media', ['file' => $file], $this->withKey())
            ->assertCreated();

        $media = Media::first();

        $this->assertNotNull($media->variants);
        $this->assertArrayHasKey('thumbnail', $media->variants);
        $this->assertArrayHasKey('medium', $media->variants);
        $this->assertArrayHasKey('large', $media->variants);

        foreach ($media->variants as $variantPath) {
            Storage::disk('public')->assertExists($variantPath);
        }
    }

    #[Test]
    public function upload_skips_variants_larger_than_original(): void
    {
        $file = UploadedFile::fake()->image('small.jpg', 500, 400);

        $this->postJson('/api/internal/media', ['file' => $file], $this->withKey())
            ->assertCreated();

        $media = Media::first();

        $this->assertNotNull($media->variants);
        $this->assertArrayHasKey('thumbnail', $media->variants);
        $this->assertArrayNotHasKey('medium', $media->variants);
        $this->assertArrayNotHasKey('large', $media->variants);
    }

    #[Test]
    public function upload_stores_dimensions_for_images(): void
    {
        $file = UploadedFile::fake()->image('photo.jpg', 1024, 768);

        $this->postJson('/api/internal/media', ['file' => $file], $this->withKey())
            ->assertCreated();

        $media = Media::first();
        $this->assertEquals(1024, $media->width);
        $this->assertEquals(768, $media->height);
    }

    #[Test]
    public function upload_with_alt_text(): void
    {
        $file = UploadedFile::fake()->image('photo.jpg', 200, 200);

        $response = $this->postJson('/api/internal/media', [
            'file' => $file,
            'alt' => 'Descriptive alt text',
        ], $this->withKey())->assertCreated();

        $this->assertEquals('Descriptive alt text', $response->json('data.alt'));
    }

    #[Test]
    public function upload_without_alt_text(): void
    {
        $file = UploadedFile::fake()->image('photo.jpg', 200, 200);

        $response = $this->postJson('/api/internal/media', [
            'file' => $file,
        ], $this->withKey())->assertCreated();

        $this->assertNull($response->json('data.alt'));
    }

    // --- Upload validation ---

    #[Test]
    public function upload_requires_file(): void
    {
        $this->postJson('/api/internal/media', [], $this->withKey())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['file']);
    }

    #[Test]
    public function upload_rejects_non_image_file(): void
    {
        $file = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

        $this->postJson('/api/internal/media', ['file' => $file], $this->withKey())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['file']);
    }

    #[Test]
    public function upload_rejects_file_exceeding_max_size(): void
    {
        $file = UploadedFile::fake()->image('huge.jpg')->size(11000); // 11MB

        $this->postJson('/api/internal/media', ['file' => $file], $this->withKey())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['file']);
    }

    #[Test]
    public function upload_rejects_alt_text_exceeding_max_length(): void
    {
        $file = UploadedFile::fake()->image('photo.jpg', 200, 200);

        $this->postJson('/api/internal/media', [
            'file' => $file,
            'alt' => str_repeat('a', 256),
        ], $this->withKey())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['alt']);
    }

    // --- Show media ---

    #[Test]
    public function can_show_single_media(): void
    {
        $media = Media::factory()->create();

        $this->getJson("/api/internal/media/{$media->id}", $this->withKey())
            ->assertOk()
            ->assertJsonPath('data.id', $media->id)
            ->assertJsonPath('data.uuid', $media->uuid)
            ->assertJsonPath('data.filename', $media->filename);
    }

    #[Test]
    public function show_returns_variant_urls(): void
    {
        $media = Media::factory()->create();

        $response = $this->getJson("/api/internal/media/{$media->id}", $this->withKey())
            ->assertOk();

        $variantUrls = $response->json('data.variant_urls');
        $this->assertArrayHasKey('thumbnail', $variantUrls);
        $this->assertArrayHasKey('medium', $variantUrls);
        $this->assertArrayHasKey('large', $variantUrls);
    }

    #[Test]
    public function show_returns_404_for_nonexistent_media(): void
    {
        $this->getJson('/api/internal/media/9999', $this->withKey())
            ->assertNotFound();
    }

    // --- Update media ---

    #[Test]
    public function can_update_media_alt_text(): void
    {
        $media = Media::factory()->create(['alt' => 'Old alt']);

        $this->patchJson("/api/internal/media/{$media->id}", [
            'alt' => 'New alt text',
        ], $this->withKey())
            ->assertOk()
            ->assertJsonPath('data.alt', 'New alt text');

        $this->assertDatabaseHas('media', ['id' => $media->id, 'alt' => 'New alt text']);
    }

    #[Test]
    public function can_clear_media_alt_text(): void
    {
        $media = Media::factory()->create(['alt' => 'Some alt']);

        $this->patchJson("/api/internal/media/{$media->id}", [
            'alt' => null,
        ], $this->withKey())
            ->assertOk()
            ->assertJsonPath('data.alt', null);
    }

    #[Test]
    public function update_rejects_alt_text_exceeding_max_length(): void
    {
        $media = Media::factory()->create();

        $this->patchJson("/api/internal/media/{$media->id}", [
            'alt' => str_repeat('a', 256),
        ], $this->withKey())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['alt']);
    }

    #[Test]
    public function update_returns_404_for_nonexistent_media(): void
    {
        $this->patchJson('/api/internal/media/9999', [
            'alt' => 'test',
        ], $this->withKey())
            ->assertNotFound();
    }

    // --- Delete media ---

    #[Test]
    public function can_delete_media(): void
    {
        $media = Media::factory()->create();

        Storage::disk('public')->put($media->path, 'fake-content');
        foreach ($media->variants as $variantPath) {
            Storage::disk('public')->put($variantPath, 'fake-content');
        }

        $this->deleteJson("/api/internal/media/{$media->id}", [], $this->withKey())
            ->assertNoContent();

        $this->assertDatabaseMissing('media', ['id' => $media->id]);

        Storage::disk('public')->assertMissing($media->path);
        foreach ($media->variants as $variantPath) {
            Storage::disk('public')->assertMissing($variantPath);
        }
    }

    #[Test]
    public function delete_returns_404_for_nonexistent_media(): void
    {
        $this->deleteJson('/api/internal/media/9999', [], $this->withKey())
            ->assertNotFound();
    }
}
