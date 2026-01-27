<?php

namespace Tests\Feature;

use App\Models\Post;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected string $privateKey;

    protected function setUp(): void
    {
        parent::setUp();

        $this->privateKey = file_get_contents(base_path('tests/keys/test-private.key'));

        // Use test public key for JWT verification
        config(['auth.jwt_public_key' => base_path('tests/keys/test-public.key')]);
    }

    protected function createToken(int $userId = 1, ?string $name = 'Test User'): string
    {
        return JWT::encode([
            'iss' => 'test-sso',
            'sub' => $userId,
            'name' => $name,
            'email' => 'test@example.com',
            'iat' => time(),
            'exp' => time() + 3600,
        ], $this->privateKey, 'RS256');
    }

    protected function authHeaders(int $userId = 1): array
    {
        return ['Authorization' => 'Bearer ' . $this->createToken($userId)];
    }

    // --- Public endpoints (no auth required) ---

    public function test_can_access_public_posts_without_token(): void
    {
        Post::factory()->published()->count(3)->create();

        $response = $this->getJson('/api/v1/public/posts');

        $response->assertStatus(200);
        $this->assertCount(3, $response->json('data'));
    }

    public function test_can_list_posts_without_token(): void
    {
        Post::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/posts');

        $response->assertStatus(200);
    }

    public function test_can_show_post_without_token(): void
    {
        $post = Post::factory()->create();

        $response = $this->getJson("/api/v1/posts/{$post->id}");

        $response->assertStatus(200);
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

        $response->assertStatus(401);
    }

    public function test_can_create_post_with_valid_token(): void
    {
        $response = $this->postJson('/api/v1/posts', [
            'title' => 'Test Post',
            'slug' => 'test-post',
            'content' => 'Test content',
            'status' => 'draft',
        ], $this->authHeaders(42));

        $response->assertStatus(201);
        $this->assertEquals(42, $response->json('data.author_id'));
    }

    public function test_cannot_update_post_without_token(): void
    {
        $post = Post::factory()->create();

        $response = $this->putJson("/api/v1/posts/{$post->id}", [
            'title' => 'Updated Title',
        ]);

        $response->assertStatus(401);
    }

    public function test_can_update_post_with_valid_token(): void
    {
        $post = Post::factory()->create();

        $response = $this->putJson("/api/v1/posts/{$post->id}", [
            'title' => 'Updated Title',
        ], $this->authHeaders());

        $response->assertStatus(200);
        $this->assertEquals('Updated Title', $response->json('data.title'));
    }

    public function test_cannot_delete_post_without_token(): void
    {
        $post = Post::factory()->create();

        $response = $this->deleteJson("/api/v1/posts/{$post->id}");

        $response->assertStatus(401);
    }

    public function test_can_delete_post_with_valid_token(): void
    {
        $post = Post::factory()->create();

        $response = $this->deleteJson("/api/v1/posts/{$post->id}", [], $this->authHeaders());

        $response->assertStatus(200);
    }

    // --- Invalid token scenarios ---

    public function test_returns_401_with_expired_token(): void
    {
        $expiredToken = JWT::encode([
            'sub' => 1,
            'iat' => time() - 7200,
            'exp' => time() - 3600,
        ], $this->privateKey, 'RS256');

        $response = $this->postJson('/api/v1/posts', [
            'title' => 'Test',
            'slug' => 'test',
            'content' => 'Content',
            'status' => 'draft',
        ], ['Authorization' => 'Bearer ' . $expiredToken]);

        $response->assertStatus(401);
    }

    public function test_returns_401_with_malformed_token(): void
    {
        $response = $this->postJson('/api/v1/posts', [
            'title' => 'Test',
            'slug' => 'test',
            'content' => 'Content',
            'status' => 'draft',
        ], ['Authorization' => 'Bearer invalid.token.here']);

        $response->assertStatus(401);
    }

    public function test_user_id_from_token_is_used_as_author_id(): void
    {
        $response = $this->postJson('/api/v1/posts', [
            'title' => 'Test Post',
            'slug' => 'test-post',
            'content' => 'Test content',
            'status' => 'draft',
        ], $this->authHeaders(123));

        $response->assertStatus(201);
        $this->assertDatabaseHas('posts', [
            'title' => 'Test Post',
            'author_id' => 123,
        ]);
    }
}
