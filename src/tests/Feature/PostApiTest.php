<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Post;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use Tests\Traits\WithJwtAuth;

class PostApiTest extends TestCase
{
    use RefreshDatabase, WithJwtAuth;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpJwtAuth();
    }

    public function test_can_list_posts(): void
    {
        Post::factory()->count(5)->create();

        $response = $this->getJson('/api/v1/posts');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'uuid',
                        'author_id',
                        'title',
                        'slug',
                        'excerpt',
                        'content',
                        'status',
                        'published_at',
                        'created_at',
                        'updated_at',
                    ]
                ],
                'links',
                'meta'
            ]);
    }

    public function test_can_list_only_published_posts(): void
    {
        Post::factory()->published()->count(3)->create();
        Post::factory()->draft()->count(2)->create();

        $response = $this->getJson('/api/v1/public/posts');

        $response->assertOk();
        $this->assertEquals(3, count($response->json('data')));
    }

    public function test_can_create_post(): void
    {
        $category = Category::factory()->create();
        $tag = Tag::factory()->create();

        $postData = [
            'title' => 'Test Post',
            'slug' => 'test-post',
            'excerpt' => 'This is a test excerpt',
            'content' => 'This is the full content of the test post.',
            'status' => 'draft',
            'category_ids' => [$category->id],
            'tag_ids' => [$tag->id],
        ];

        $response = $this->postJson('/api/v1/posts', $postData, $this->authHeaders());

        $response->assertCreated()
            ->assertJsonFragment([
                'title' => 'Test Post',
                'slug' => 'test-post',
                'status' => 'draft',
            ]);

        $this->assertDatabaseHas('posts', [
            'title' => 'Test Post',
            'slug' => 'test-post',
        ]);
    }

    #[DataProvider('invalidPostDataProvider')]
    public function test_create_post_validation_fails(array $data, array $expectedErrors): void
    {
        $response = $this->postJson('/api/v1/posts', $data, $this->authHeaders());

        $response->assertUnprocessable()
            ->assertJsonValidationErrors($expectedErrors);
    }

    public static function invalidPostDataProvider(): array
    {
        return [
            'empty payload' => [
                [],
                ['title', 'slug', 'content', 'status'],
            ],
            'missing title' => [
                ['slug' => 'test', 'content' => 'Content', 'status' => 'draft'],
                ['title'],
            ],
            'missing slug' => [
                ['title' => 'Test', 'content' => 'Content', 'status' => 'draft'],
                ['slug'],
            ],
            'missing content' => [
                ['title' => 'Test', 'slug' => 'test', 'status' => 'draft'],
                ['content'],
            ],
            'invalid status' => [
                ['title' => 'Test', 'slug' => 'test', 'content' => 'Content', 'status' => 'invalid'],
                ['status'],
            ],
            'title too long' => [
                ['title' => str_repeat('a', 256), 'slug' => 'test', 'content' => 'Content', 'status' => 'draft'],
                ['title'],
            ],
        ];
    }

    public function test_can_show_single_post(): void
    {
        $post = Post::factory()->create();

        $response = $this->getJson("/api/v1/posts/{$post->id}");

        $response->assertOk()
            ->assertJsonFragment([
                'id' => $post->id,
                'title' => $post->title,
                'slug' => $post->slug,
            ]);
    }

    public function test_can_show_post_with_relationships(): void
    {
        $post = Post::factory()->create();
        $category = Category::factory()->create();
        $tag = Tag::factory()->create();

        $post->categories()->attach($category);
        $post->tags()->attach($tag);

        $response = $this->getJson("/api/v1/posts/{$post->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'title',
                    'categories' => [
                        '*' => ['id', 'name', 'slug']
                    ],
                    'tags' => [
                        '*' => ['id', 'name', 'slug']
                    ],
                    'comments_count'
                ]
            ]);
    }

    public function test_can_update_post(): void
    {
        $post = Post::factory()->create();

        $updateData = [
            'title' => 'Updated Title',
            'content' => 'Updated content',
        ];

        $response = $this->putJson("/api/v1/posts/{$post->id}", $updateData, $this->authHeaders());

        $response->assertOk()
            ->assertJsonFragment([
                'title' => 'Updated Title',
            ]);

        $this->assertDatabaseHas('posts', [
            'id' => $post->id,
            'title' => 'Updated Title',
        ]);
    }

    public function test_can_update_post_relationships(): void
    {
        $post = Post::factory()->create();
        $oldCategory = Category::factory()->create();
        $newCategory = Category::factory()->create();

        $post->categories()->attach($oldCategory);

        $response = $this->putJson("/api/v1/posts/{$post->id}", [
            'category_ids' => [$newCategory->id],
        ], $this->authHeaders());

        $response->assertOk();

        $this->assertDatabaseHas('category_post', [
            'post_id' => $post->id,
            'category_id' => $newCategory->id,
        ]);

        $this->assertDatabaseMissing('category_post', [
            'post_id' => $post->id,
            'category_id' => $oldCategory->id,
        ]);
    }

    public function test_can_delete_post(): void
    {
        $post = Post::factory()->create();

        $response = $this->deleteJson("/api/v1/posts/{$post->id}", [], $this->authHeaders());

        $response->assertOk()
            ->assertJson([
                'message' => 'Post deleted successfully'
            ]);

        $this->assertSoftDeleted('posts', [
            'id' => $post->id,
        ]);
    }

    public function test_can_filter_posts_by_status(): void
    {
        Post::factory()->published()->count(3)->create();
        Post::factory()->draft()->count(2)->create();

        $response = $this->getJson('/api/v1/posts?status=published');

        $response->assertOk();
        $this->assertEquals(3, count($response->json('data')));
    }

    public function test_can_filter_posts_by_category(): void
    {
        $category1 = Category::factory()->create();
        $category2 = Category::factory()->create();

        $post1 = Post::factory()->create();
        $post1->categories()->attach($category1);

        $post2 = Post::factory()->create();
        $post2->categories()->attach($category2);

        $response = $this->getJson("/api/v1/posts?category_id={$category1->id}");

        $response->assertOk();
        $this->assertEquals(1, count($response->json('data')));
        $this->assertEquals($post1->id, $response->json('data.0.id'));
    }

    public function test_can_search_posts(): void
    {
        Post::factory()->create(['title' => 'Laravel Tutorial']);
        Post::factory()->create(['title' => 'PHP Best Practices']);
        Post::factory()->create(['title' => 'JavaScript Basics']);

        $response = $this->getJson('/api/v1/posts?search=Laravel');

        $response->assertOk();
        $this->assertEquals(1, count($response->json('data')));
        $this->assertStringContainsString('Laravel', $response->json('data.0.title'));
    }

    public function test_posts_are_paginated(): void
    {
        Post::factory()->count(20)->create();

        $response = $this->getJson('/api/v1/posts?per_page=5');

        $response->assertOk();
        $this->assertEquals(5, count($response->json('data')));
        $this->assertEquals(20, $response->json('meta.total'));
    }
}
