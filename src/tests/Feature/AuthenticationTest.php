<?php

namespace Tests\Feature;

use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\WithJwtAuth;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase, WithJwtAuth;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpJwtAuth();
    }

    // --- Public endpoints (no auth required) ---

    public function test_can_access_public_posts_without_token(): void
    {
        Post::factory()->published()->count(3)->create();

        $response = $this->getJson('/api/v1/public/posts');

        $response->assertOk();
        $this->assertCount(3, $response->json('data'));
    }

    public function test_can_list_posts_without_token(): void
    {
        Post::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/posts');

        $response->assertOk();
    }

    public function test_can_show_post_without_token(): void
    {
        $post = Post::factory()->create();

        $response = $this->getJson("/api/v1/posts/{$post->id}");

        $response->assertOk();
    }

    // --- Protected endpoints (auth required) ---

    public function test_cannot_create_post_without_token(): void
    {
        $response = $this->postJson('/api/v1/posts', [
            'title' => 'Test Post',
            'slug' => 'test-post',
            'content' => 'Test content',
            'status' => 'draft',
        ]);

        $response->assertUnauthorized();
    }

    public function test_can_create_post_with_valid_token(): void
    {
        $response = $this->postJson('/api/v1/posts', [
            'title' => 'Test Post',
            'slug' => 'test-post',
            'content' => 'Test content',
            'status' => 'draft',
        ], $this->authHeaders(42));

        $response->assertCreated();
        $this->assertEquals(42, $response->json('data.author_id'));
    }

    public function test_cannot_update_post_without_token(): void
    {
        $post = Post::factory()->create();

        $response = $this->putJson("/api/v1/posts/{$post->id}", [
            'title' => 'Updated Title',
        ]);

        $response->assertUnauthorized();
    }

    public function test_can_update_post_with_valid_token(): void
    {
        $post = Post::factory()->create();

        $response = $this->putJson("/api/v1/posts/{$post->id}", [
            'title' => 'Updated Title',
        ], $this->authHeaders());

        $response->assertOk();
        $this->assertEquals('Updated Title', $response->json('data.title'));
    }

    public function test_cannot_delete_post_without_token(): void
    {
        $post = Post::factory()->create();

        $response = $this->deleteJson("/api/v1/posts/{$post->id}");

        $response->assertUnauthorized();
    }

    public function test_can_delete_post_with_valid_token(): void
    {
        $post = Post::factory()->create();

        $response = $this->deleteJson("/api/v1/posts/{$post->id}", [], $this->authHeaders());

        $response->assertOk();
    }

    // --- Invalid token scenarios ---

    public function test_returns_401_with_expired_token(): void
    {
        $expiredToken = $this->createTestToken(overrides: [
            'iat' => time() - 7200,
            'exp' => time() - 3600,
        ]);

        $response = $this->postJson('/api/v1/posts', [
            'title' => 'Test',
            'slug' => 'test',
            'content' => 'Content',
            'status' => 'draft',
        ], ['Authorization' => 'Bearer ' . $expiredToken]);

        $response->assertUnauthorized();
    }

    public function test_returns_401_with_malformed_token(): void
    {
        $response = $this->postJson('/api/v1/posts', [
            'title' => 'Test',
            'slug' => 'test',
            'content' => 'Content',
            'status' => 'draft',
        ], ['Authorization' => 'Bearer invalid.token.here']);

        $response->assertUnauthorized();
    }

    public function test_user_id_from_token_is_used_as_author_id(): void
    {
        $response = $this->postJson('/api/v1/posts', [
            'title' => 'Test Post',
            'slug' => 'test-post',
            'content' => 'Test content',
            'status' => 'draft',
        ], $this->authHeaders(123));

        $response->assertCreated();
        $this->assertDatabaseHas('posts', [
            'title' => 'Test Post',
            'author_id' => 123,
        ]);
    }
}
