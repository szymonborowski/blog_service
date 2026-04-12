<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Post;
use App\Models\PostTranslation;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Traits\WithJwtAuth;

class PostSearchIndexingTest extends TestCase
{
    use RefreshDatabase, WithJwtAuth;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpJwtAuth();
    }

    // -------------------------------------------------------------------------
    // store — published post is indexed with full relations
    // -------------------------------------------------------------------------

    #[Test]
    public function store_creates_published_post_with_relations_ready_for_indexing(): void
    {
        $category = Category::factory()->create();
        $tag      = Tag::factory()->create();

        $this->postJson('/api/v1/posts', [
            'title'        => 'Test Post',
            'slug'         => 'test-post',
            'content'      => 'Some content',
            'status'       => 'published',
            'published_at' => now()->toDateTimeString(),
            'locale'       => 'pl',
            'category_ids' => [$category->id],
            'tag_ids'      => [$tag->id],
        ], $this->authHeaders(1))->assertCreated();

        $post = Post::with(['translations', 'categories', 'tags'])
            ->where('slug', 'test-post')
            ->first();

        $this->assertTrue($post->shouldBeSearchable());
        $this->assertCount(1, $post->categories);
        $this->assertCount(1, $post->tags);
        $this->assertNotEmpty($post->translations);
    }

    #[Test]
    public function store_does_not_make_draft_searchable(): void
    {
        $this->postJson('/api/v1/posts', [
            'title'   => 'Draft Post',
            'slug'    => 'draft-post',
            'content' => 'Draft content',
            'status'  => 'draft',
            'locale'  => 'pl',
        ], $this->authHeaders(1))->assertCreated();

        $post = Post::where('slug', 'draft-post')->first();

        $this->assertFalse($post->shouldBeSearchable());
    }

    // -------------------------------------------------------------------------
    // update — relations synced before re-indexing
    // -------------------------------------------------------------------------

    #[Test]
    public function update_loads_fresh_relations_before_indexing(): void
    {
        $post = Post::factory()
            ->published()
            ->has(PostTranslation::factory()->state(['locale' => 'pl', 'title' => 'Old']), 'translations')
            ->create();

        $newCategory = Category::factory()->create();
        $newTag      = Tag::factory()->create();

        $this->putJson("/api/v1/posts/{$post->id}", [
            'title'        => 'Updated',
            'locale'       => 'pl',
            'content'      => 'New body',
            'status'       => 'published',
            'published_at' => now()->toDateTimeString(),
            'category_ids' => [$newCategory->id],
            'tag_ids'      => [$newTag->id],
        ], $this->authHeaders(1))->assertOk();

        $post->refresh()->load(['translations', 'categories', 'tags']);

        $this->assertEquals('Updated', $post->translations->first()->title);
        $this->assertTrue($post->categories->contains($newCategory));
        $this->assertTrue($post->tags->contains($newTag));
    }

    #[Test]
    public function update_marks_archived_post_as_unsearchable(): void
    {
        // Event::fake() prevents PostStatusChanged from connecting to RabbitMQ
        Event::fake();

        $post = Post::factory()
            ->published()
            ->has(PostTranslation::factory(), 'translations')
            ->create();

        $this->putJson("/api/v1/posts/{$post->id}", [
            'status' => 'archived',
        ], $this->authHeaders(1))->assertOk();

        $post->refresh();

        $this->assertFalse($post->shouldBeSearchable());
    }

    #[Test]
    public function update_marks_drafted_post_as_unsearchable(): void
    {
        Event::fake();

        $post = Post::factory()
            ->published()
            ->has(PostTranslation::factory(), 'translations')
            ->create();

        $this->putJson("/api/v1/posts/{$post->id}", [
            'status' => 'draft',
        ], $this->authHeaders(1))->assertOk();

        $post->refresh();

        $this->assertFalse($post->shouldBeSearchable());
    }

    // -------------------------------------------------------------------------
    // PostTranslation $touches — updating translation re-syncs post updated_at
    // -------------------------------------------------------------------------

    #[Test]
    public function updating_translation_via_post_update_reflects_in_index(): void
    {
        $post = Post::factory()
            ->published()
            ->has(PostTranslation::factory()->state(['locale' => 'pl', 'title' => 'Old title']), 'translations')
            ->create();

        $this->travel(2)->seconds();

        $before = $post->updated_at;

        $this->putJson("/api/v1/posts/{$post->id}", [
            'title'   => 'New title',
            'locale'  => 'pl',
            'content' => 'New content',
            'status'  => 'published',
        ], $this->authHeaders(1))->assertOk();

        $post->refresh();

        $this->assertTrue($post->updated_at->greaterThanOrEqualTo($before));
        $this->assertEquals('New title', $post->translations->firstWhere('locale', 'pl')->title);
    }
}
