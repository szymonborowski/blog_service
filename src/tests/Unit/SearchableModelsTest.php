<?php

namespace Tests\Unit;

use App\Models\Category;
use App\Models\Post;
use App\Models\PostTranslation;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SearchableModelsTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Post::toSearchableArray()
    // -------------------------------------------------------------------------

    #[Test]
    public function post_searchable_array_contains_required_keys(): void
    {
        $post = Post::factory()
            ->has(PostTranslation::factory()->state(['locale' => 'pl', 'title' => 'Laravel tricks']), 'translations')
            ->create();

        $array = $post->toSearchableArray();

        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('slug', $array);
        $this->assertArrayHasKey('title', $array);
        $this->assertArrayHasKey('excerpt', $array);
        $this->assertArrayHasKey('content', $array);
        $this->assertArrayHasKey('categories', $array);
        $this->assertArrayHasKey('tags', $array);
        $this->assertArrayHasKey('published_at', $array);
    }

    #[Test]
    public function post_searchable_array_joins_translations_by_pipe(): void
    {
        $post = Post::factory()->create();
        PostTranslation::factory()->create(['post_id' => $post->id, 'locale' => 'pl', 'title' => 'Tytuł PL']);
        PostTranslation::factory()->create(['post_id' => $post->id, 'locale' => 'en', 'title' => 'Title EN']);

        $post->load('translations');
        $array = $post->toSearchableArray();

        $this->assertStringContainsString('Tytuł PL', $array['title']);
        $this->assertStringContainsString('Title EN', $array['title']);
        $this->assertStringContainsString(' | ', $array['title']);
    }

    #[Test]
    public function post_searchable_array_strips_html_from_content(): void
    {
        $post = Post::factory()
            ->has(PostTranslation::factory()->state(['content' => '<p>Hello <strong>world</strong></p>']), 'translations')
            ->create();

        $post->load('translations');
        $array = $post->toSearchableArray();

        $this->assertStringNotContainsString('<p>', $array['content']);
        $this->assertStringNotContainsString('<strong>', $array['content']);
        $this->assertStringContainsString('Hello', $array['content']);
    }

    #[Test]
    public function post_searchable_array_includes_category_names(): void
    {
        $post    = Post::factory()->create();
        $category = Category::factory()->create(['name' => 'Backend']);
        $post->categories()->attach($category);
        $post->load(['translations', 'categories', 'tags']);

        $array = $post->toSearchableArray();

        $this->assertContains('Backend', $array['categories']);
    }

    #[Test]
    public function post_searchable_array_includes_tag_names(): void
    {
        $post = Post::factory()->create();
        $tag  = Tag::factory()->create(['name' => 'laravel']);
        $post->tags()->attach($tag);
        $post->load(['translations', 'categories', 'tags']);

        $array = $post->toSearchableArray();

        $this->assertContains('laravel', $array['tags']);
    }

    #[Test]
    public function post_searchable_array_published_at_is_unix_timestamp_or_null(): void
    {
        $published = Post::factory()->published()->create();
        $draft     = Post::factory()->draft()->create();

        $published->load(['translations', 'categories', 'tags']);
        $draft->load(['translations', 'categories', 'tags']);

        $this->assertIsInt($published->toSearchableArray()['published_at']);
        $this->assertNull($draft->toSearchableArray()['published_at']);
    }

    // -------------------------------------------------------------------------
    // Post::shouldBeSearchable()
    // -------------------------------------------------------------------------

    #[Test]
    public function published_post_should_be_searchable(): void
    {
        $post = Post::factory()->published()->create();

        $this->assertTrue($post->shouldBeSearchable());
    }

    #[Test]
    public function draft_post_should_not_be_searchable(): void
    {
        $post = Post::factory()->draft()->create();

        $this->assertFalse($post->shouldBeSearchable());
    }

    #[Test]
    public function archived_post_should_not_be_searchable(): void
    {
        $post = Post::factory()->create(['status' => 'archived']);

        $this->assertFalse($post->shouldBeSearchable());
    }

    #[Test]
    public function soft_deleted_post_should_not_be_searchable(): void
    {
        $post = Post::factory()->published()->create();
        $post->delete();
        $post->refresh();

        $this->assertFalse($post->shouldBeSearchable());
    }

    #[Test]
    public function published_post_with_future_date_should_not_be_searchable(): void
    {
        $post = Post::factory()->create([
            'status'       => 'published',
            'published_at' => now()->addDay(),
        ]);

        $this->assertFalse($post->shouldBeSearchable());
    }

    // -------------------------------------------------------------------------
    // Category::toSearchableArray()
    // -------------------------------------------------------------------------

    #[Test]
    public function category_searchable_array_contains_required_keys(): void
    {
        $category = Category::factory()->create(['name' => 'PHP', 'color' => 'sky', 'icon' => '🐘']);

        $array = $category->toSearchableArray();

        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('name', $array);
        $this->assertArrayHasKey('slug', $array);
        $this->assertArrayHasKey('color', $array);
        $this->assertArrayHasKey('icon', $array);
        $this->assertSame('PHP', $array['name']);
        $this->assertSame('sky', $array['color']);
    }

    // -------------------------------------------------------------------------
    // Tag::toSearchableArray()
    // -------------------------------------------------------------------------

    #[Test]
    public function tag_searchable_array_contains_required_keys(): void
    {
        $tag = Tag::factory()->create(['name' => 'docker']);

        $array = $tag->toSearchableArray();

        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('name', $array);
        $this->assertArrayHasKey('slug', $array);
        $this->assertSame('docker', $array['name']);
    }

    // -------------------------------------------------------------------------
    // PostTranslation $touches propagation
    // -------------------------------------------------------------------------

    #[Test]
    public function updating_translation_touches_parent_post(): void
    {
        $post = Post::factory()
            ->has(PostTranslation::factory(), 'translations')
            ->create();

        $originalUpdatedAt = $post->updated_at;

        // Ensure at least 1 second passes so updated_at changes
        $this->travel(2)->seconds();

        $post->translations()->first()->update(['title' => 'Changed title']);
        $post->refresh();

        $this->assertTrue($post->updated_at->greaterThan($originalUpdatedAt));
    }
}
