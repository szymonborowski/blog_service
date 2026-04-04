<?php

namespace Tests\Unit;

use App\Models\Media;
use App\Services\MediaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MediaServiceTest extends TestCase
{
    use RefreshDatabase;

    private MediaService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->service = app(MediaService::class);
    }

    #[Test]
    public function upload_creates_media_record(): void
    {
        $file = UploadedFile::fake()->image('test.jpg', 800, 600);

        $media = $this->service->upload($file, 'Test image');

        $this->assertInstanceOf(Media::class, $media);
        $this->assertEquals('test.jpg', $media->filename);
        $this->assertEquals('image/jpeg', $media->mime_type);
        $this->assertEquals('Test image', $media->alt);
        $this->assertEquals('public', $media->disk);
        $this->assertNotNull($media->uuid);
        $this->assertDatabaseCount('media', 1);
    }

    #[Test]
    public function upload_stores_file_with_uuid_filename(): void
    {
        $file = UploadedFile::fake()->image('my-photo.jpg', 400, 300);

        $media = $this->service->upload($file);

        Storage::disk('public')->assertExists($media->path);
        $this->assertStringContainsString($media->uuid, $media->path);
        $this->assertStringEndsWith('.jpg', $media->path);
    }

    #[Test]
    public function upload_organizes_files_by_year_and_month(): void
    {
        $file = UploadedFile::fake()->image('photo.png', 400, 300);

        $media = $this->service->upload($file);

        $expectedPrefix = 'media/' . date('Y') . '/' . date('m') . '/';
        $this->assertStringStartsWith($expectedPrefix, $media->path);
    }

    #[Test]
    public function upload_reads_image_dimensions(): void
    {
        $file = UploadedFile::fake()->image('photo.jpg', 1920, 1080);

        $media = $this->service->upload($file);

        $this->assertEquals(1920, $media->width);
        $this->assertEquals(1080, $media->height);
    }

    #[Test]
    public function upload_generates_all_variants_for_large_image(): void
    {
        $file = UploadedFile::fake()->image('large.jpg', 2000, 1500);

        $media = $this->service->upload($file);

        $this->assertIsArray($media->variants);
        $this->assertArrayHasKey('thumbnail', $media->variants);
        $this->assertArrayHasKey('medium', $media->variants);
        $this->assertArrayHasKey('large', $media->variants);

        foreach ($media->variants as $name => $path) {
            Storage::disk('public')->assertExists($path);
            $this->assertStringEndsWith('_' . $name . '.webp', $path);
        }
    }

    #[Test]
    public function upload_skips_variant_when_original_is_smaller(): void
    {
        $file = UploadedFile::fake()->image('small.jpg', 100, 80);

        $media = $this->service->upload($file);

        $this->assertNull($media->variants);
    }

    #[Test]
    public function upload_generates_only_applicable_variants(): void
    {
        // 200px wide: only thumbnail (150px) should be generated
        $file = UploadedFile::fake()->image('medium.jpg', 200, 150);

        $media = $this->service->upload($file);

        $this->assertIsArray($media->variants);
        $this->assertArrayHasKey('thumbnail', $media->variants);
        $this->assertArrayNotHasKey('medium', $media->variants);
        $this->assertArrayNotHasKey('large', $media->variants);
    }

    #[Test]
    public function upload_does_not_process_svg_files(): void
    {
        $file = UploadedFile::fake()->create('icon.svg', 5, 'image/svg+xml');

        $media = $this->service->upload($file);

        $this->assertNull($media->width);
        $this->assertNull($media->height);
        $this->assertNull($media->variants);
        Storage::disk('public')->assertExists($media->path);
    }

    #[Test]
    public function upload_records_file_size(): void
    {
        $file = UploadedFile::fake()->image('photo.jpg', 400, 300);

        $media = $this->service->upload($file);

        $this->assertGreaterThan(0, $media->size);
    }

    #[Test]
    public function upload_without_alt_stores_null(): void
    {
        $file = UploadedFile::fake()->image('photo.jpg', 400, 300);

        $media = $this->service->upload($file);

        $this->assertNull($media->alt);
    }

    #[Test]
    public function delete_removes_original_file(): void
    {
        $file = UploadedFile::fake()->image('photo.jpg', 400, 300);
        $media = $this->service->upload($file);

        Storage::disk('public')->assertExists($media->path);

        $this->service->delete($media);

        Storage::disk('public')->assertMissing($media->path);
    }

    #[Test]
    public function delete_removes_all_variant_files(): void
    {
        $file = UploadedFile::fake()->image('photo.jpg', 2000, 1500);
        $media = $this->service->upload($file);

        $variantPaths = $media->variants;
        foreach ($variantPaths as $path) {
            Storage::disk('public')->assertExists($path);
        }

        $this->service->delete($media);

        foreach ($variantPaths as $path) {
            Storage::disk('public')->assertMissing($path);
        }
    }

    #[Test]
    public function delete_removes_database_record(): void
    {
        $file = UploadedFile::fake()->image('photo.jpg', 400, 300);
        $media = $this->service->upload($file);
        $mediaId = $media->id;

        $this->service->delete($media);

        $this->assertDatabaseMissing('media', ['id' => $mediaId]);
    }

    #[Test]
    public function delete_handles_media_without_variants(): void
    {
        $file = UploadedFile::fake()->image('tiny.jpg', 50, 50);
        $media = $this->service->upload($file);

        $this->assertNull($media->variants);

        $this->service->delete($media);

        Storage::disk('public')->assertMissing($media->path);
        $this->assertDatabaseMissing('media', ['id' => $media->id]);
    }
}
