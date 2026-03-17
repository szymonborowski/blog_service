<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Post;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InternalPostApiTest extends TestCase
{
    use RefreshDatabase;

    private string $apiKey = 'test-internal-key';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.internal.api_key' => $this->apiKey]);
    }

    private function withKey(): array
    {
        return ['X-Internal-Api-Key' => $this->apiKey];
    }

    // --- Auth ---

    #[Test]
    public function rejects_request_without_api_key(): void
    {
        $this->getJson('/api/internal/posts')->assertUnauthorized();
    }

    #[Test]
    public function rejects_request_with_wrong_api_key(): void
    {
        $this->getJson('/api/internal/posts', ['X-Internal-Api-Key' => 'wrong'])
            ->assertUnauthorized();
    }

    // --- List posts ---

    #[Test]
    public function can_list_posts(): void
    {
        Post::factory()->count(3)->create();

        $this->getJson('/api/internal/posts', $this->withKey())
            ->assertOk()
            ->assertJsonStructure(['data', 'meta', 'links']);
    }

    #[Test]
    public function can_filter_posts_by_status(): void
    {
        Post::factory()->published()->count(2)->create();
        Post::factory()->draft()->count(3)->create();

        $response = $this->getJson('/api/internal/posts?status=published', $this->withKey())
            ->assertOk();

        $this->assertCount(2, $response->json('data'));
    }

    #[Test]
    public function can_search_posts(): void
    {
        Post::factory()->create(['title' => 'Laravel microservices guide']);
        Post::factory()->create(['title' => 'Something else entirely']);

        $response = $this->getJson('/api/internal/posts?search=microservices', $this->withKey())
            ->assertOk();

        $this->assertCount(1, $response->json('data'));
        $this->assertStringContainsString('microservices', $response->json('data.0.title'));
    }

    // --- Create post ---

    #[Test]
    public function can_create_post_via_internal_api(): void
    {
        $category = Category::factory()->create();
        $tag = Tag::factory()->create();

        $payload = [
            'title'        => 'My new post',
            'slug'         => 'my-new-post',
            'content'      => 'Post content here.',
            'status'       => 'draft',
            'category_ids' => [$category->id],
            'tag_ids'      => [$tag->id],
        ];

        $this->postJson('/api/internal/posts', $payload, $this->withKey())
            ->assertCreated()
            ->assertJsonPath('data.title', 'My new post')
            ->assertJsonPath('data.slug', 'my-new-post');

        $this->assertDatabaseHas('posts', ['slug' => 'my-new-post']);
    }

    #[Test]
    public function create_post_uses_default_author_id_when_not_provided(): void
    {
        $this->postJson('/api/internal/posts', [
            'title'   => 'Test',
            'slug'    => 'test',
            'content' => 'Content',
            'status'  => 'draft',
        ], $this->withKey())->assertCreated();

        $this->assertDatabaseHas('posts', ['slug' => 'test', 'author_id' => 1]);
    }

    #[Test]
    public function create_post_uses_provided_author_id(): void
    {
        $this->postJson('/api/internal/posts', [
            'title'     => 'Test',
            'slug'      => 'test-author',
            'content'   => 'Content',
            'status'    => 'draft',
            'author_id' => 42,
        ], $this->withKey())->assertCreated();

        $this->assertDatabaseHas('posts', ['slug' => 'test-author', 'author_id' => 42]);
    }

    #[Test]
    public function create_post_generates_uuid_automatically(): void
    {
        $response = $this->postJson('/api/internal/posts', [
            'title'   => 'UUID test',
            'slug'    => 'uuid-test',
            'content' => 'Content',
            'status'  => 'draft',
        ], $this->withKey())->assertCreated();

        $this->assertNotEmpty($response->json('data.uuid'));
    }

    #[Test]
    public function create_post_validates_required_fields(): void
    {
        $this->postJson('/api/internal/posts', [], $this->withKey())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title', 'slug', 'content', 'status']);
    }

    #[Test]
    public function create_post_rejects_duplicate_slug(): void
    {
        Post::factory()->create(['slug' => 'existing-slug']);

        $this->postJson('/api/internal/posts', [
            'title'   => 'Another post',
            'slug'    => 'existing-slug',
            'content' => 'Content',
            'status'  => 'draft',
        ], $this->withKey())->assertUnprocessable()
            ->assertJsonValidationErrors(['slug']);
    }

    // --- Update post ---

    #[Test]
    public function can_update_post_via_internal_api(): void
    {
        $post = Post::factory()->create(['title' => 'Old title', 'slug' => 'old-slug']);

        $this->putJson("/api/internal/posts/{$post->id}", [
            'title'   => 'New title',
            'slug'    => 'new-slug',
            'content' => 'Updated content',
            'status'  => 'published',
        ], $this->withKey())
            ->assertOk()
            ->assertJsonPath('data.title', 'New title')
            ->assertJsonPath('data.slug', 'new-slug');

        $this->assertDatabaseHas('posts', ['id' => $post->id, 'title' => 'New title']);
    }

    #[Test]
    public function update_post_allows_same_slug_on_same_post(): void
    {
        $post = Post::factory()->create(['slug' => 'my-slug']);

        $this->putJson("/api/internal/posts/{$post->id}", [
            'title'   => 'Updated title',
            'slug'    => 'my-slug',
            'content' => 'Content',
            'status'  => 'draft',
        ], $this->withKey())->assertOk();
    }

    #[Test]
    public function update_post_rejects_slug_taken_by_another_post(): void
    {
        Post::factory()->create(['slug' => 'taken-slug']);
        $post = Post::factory()->create(['slug' => 'my-slug']);

        $this->putJson("/api/internal/posts/{$post->id}", [
            'slug' => 'taken-slug',
        ], $this->withKey())->assertUnprocessable()
            ->assertJsonValidationErrors(['slug']);
    }

    #[Test]
    public function update_post_syncs_categories_and_tags(): void
    {
        $post = Post::factory()->create();
        $cat1 = Category::factory()->create();
        $cat2 = Category::factory()->create();
        $tag  = Tag::factory()->create();

        $post->categories()->attach($cat1->id);

        $this->putJson("/api/internal/posts/{$post->id}", [
            'category_ids' => [$cat2->id],
            'tag_ids'      => [$tag->id],
        ], $this->withKey())->assertOk();

        $post->refresh();
        $this->assertTrue($post->categories->contains($cat2->id));
        $this->assertFalse($post->categories->contains($cat1->id));
        $this->assertTrue($post->tags->contains($tag->id));
    }

    // --- Delete post ---

    #[Test]
    public function can_delete_post_via_internal_api(): void
    {
        $post = Post::factory()->create();

        $this->deleteJson("/api/internal/posts/{$post->id}", [], $this->withKey())
            ->assertOk();

        $this->assertSoftDeleted('posts', ['id' => $post->id]);
    }

    #[Test]
    public function returns_404_when_deleting_nonexistent_post(): void
    {
        $this->deleteJson('/api/internal/posts/9999', [], $this->withKey())
            ->assertNotFound();
    }

    // --- Categories ---

    #[Test]
    public function can_list_categories_via_internal_api(): void
    {
        Category::factory()->count(3)->create();

        $this->getJson('/api/internal/categories', $this->withKey())
            ->assertOk()
            ->assertJsonStructure(['data']);
    }

    #[Test]
    public function can_create_category_via_internal_api(): void
    {
        $this->postJson('/api/internal/categories', [
            'name' => 'DevOps',
            'slug' => 'devops',
        ], $this->withKey())
            ->assertCreated()
            ->assertJsonPath('data.name', 'DevOps');

        $this->assertDatabaseHas('categories', ['slug' => 'devops']);
    }

    // --- Tags ---

    #[Test]
    public function can_list_tags_via_internal_api(): void
    {
        Tag::factory()->count(3)->create();

        $this->getJson('/api/internal/tags', $this->withKey())
            ->assertOk()
            ->assertJsonStructure(['data']);
    }

    #[Test]
    public function can_create_tag_via_internal_api(): void
    {
        $this->postJson('/api/internal/tags', [
            'name' => 'Kubernetes',
            'slug' => 'kubernetes',
        ], $this->withKey())
            ->assertCreated()
            ->assertJsonPath('data.name', 'Kubernetes');

        $this->assertDatabaseHas('tags', ['slug' => 'kubernetes']);
    }
}
