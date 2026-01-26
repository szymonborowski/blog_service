<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Post;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test listing all posts.
     */
    public function test_can_list_posts(): void
    {
        Post::factory()->count(5)->create();

        $response = $this->getJson('/api/v1/posts');

        $response->assertStatus(200)
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

    /**
     * Test listing published posts only.
     */
    public function test_can_list_only_published_posts(): void
    {
        Post::factory()->published()->count(3)->create();
        Post::factory()->draft()->count(2)->create();

        $response = $this->getJson('/api/v1/public/posts');

        $response->assertStatus(200);
        $this->assertEquals(3, count($response->json('data')));
    }

    /**
     * Test creating a new post.
     */
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
            'author_id' => 1,
            'category_ids' => [$category->id],
            'tag_ids' => [$tag->id],
        ];

        $response = $this->postJson('/api/v1/posts', $postData);

        $response->assertStatus(201)
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

    /**
     * Test validation when creating a post.
     */
    public function test_create_post_validation_fails(): void
    {
        $response = $this->postJson('/api/v1/posts', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['title', 'slug', 'content', 'status']);
    }

    /**
     * Test showing a single post.
     */
    public function test_can_show_single_post(): void
    {
        $post = Post::factory()->create();

        $response = $this->getJson("/api/v1/posts/{$post->id}");

        $response->assertStatus(200)
            ->assertJsonFragment([
                'id' => $post->id,
                'title' => $post->title,
                'slug' => $post->slug,
            ]);
    }

    /**
     * Test showing a post with relationships.
     */
    public function test_can_show_post_with_relationships(): void
    {
        $post = Post::factory()->create();
        $category = Category::factory()->create();
        $tag = Tag::factory()->create();

        $post->categories()->attach($category);
        $post->tags()->attach($tag);

        $response = $this->getJson("/api/v1/posts/{$post->id}");

        $response->assertStatus(200)
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

    /**
     * Test updating a post.
     */
    public function test_can_update_post(): void
    {
        $post = Post::factory()->create();

        $updateData = [
            'title' => 'Updated Title',
            'content' => 'Updated content',
        ];

        $response = $this->putJson("/api/v1/posts/{$post->id}", $updateData);

        $response->assertStatus(200)
            ->assertJsonFragment([
                'title' => 'Updated Title',
            ]);

        $this->assertDatabaseHas('posts', [
            'id' => $post->id,
            'title' => 'Updated Title',
        ]);
    }

    /**
     * Test updating post categories and tags.
     */
    public function test_can_update_post_relationships(): void
    {
        $post = Post::factory()->create();
        $oldCategory = Category::factory()->create();
        $newCategory = Category::factory()->create();

        $post->categories()->attach($oldCategory);

        $response = $this->putJson("/api/v1/posts/{$post->id}", [
            'category_ids' => [$newCategory->id],
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('category_post', [
            'post_id' => $post->id,
            'category_id' => $newCategory->id,
        ]);

        $this->assertDatabaseMissing('category_post', [
            'post_id' => $post->id,
            'category_id' => $oldCategory->id,
        ]);
    }

    /**
     * Test deleting a post.
     */
    public function test_can_delete_post(): void
    {
        $post = Post::factory()->create();

        $response = $this->deleteJson("/api/v1/posts/{$post->id}");

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Post deleted successfully'
            ]);

        $this->assertSoftDeleted('posts', [
            'id' => $post->id,
        ]);
    }

    /**
     * Test filtering posts by status.
     */
    public function test_can_filter_posts_by_status(): void
    {
        Post::factory()->published()->count(3)->create();
        Post::factory()->draft()->count(2)->create();

        $response = $this->getJson('/api/v1/posts?status=published');

        $response->assertStatus(200);
        $this->assertEquals(3, count($response->json('data')));
    }

    /**
     * Test filtering posts by category.
     */
    public function test_can_filter_posts_by_category(): void
    {
        $category1 = Category::factory()->create();
        $category2 = Category::factory()->create();

        $post1 = Post::factory()->create();
        $post1->categories()->attach($category1);

        $post2 = Post::factory()->create();
        $post2->categories()->attach($category2);

        $response = $this->getJson("/api/v1/posts?category_id={$category1->id}");

        $response->assertStatus(200);
        $this->assertEquals(1, count($response->json('data')));
        $this->assertEquals($post1->id, $response->json('data.0.id'));
    }

    /**
     * Test searching posts.
     */
    public function test_can_search_posts(): void
    {
        Post::factory()->create(['title' => 'Laravel Tutorial']);
        Post::factory()->create(['title' => 'PHP Best Practices']);
        Post::factory()->create(['title' => 'JavaScript Basics']);

        $response = $this->getJson('/api/v1/posts?search=Laravel');

        $response->assertStatus(200);
        $this->assertEquals(1, count($response->json('data')));
        $this->assertStringContainsString('Laravel', $response->json('data.0.title'));
    }

    /**
     * Test pagination.
     */
    public function test_posts_are_paginated(): void
    {
        Post::factory()->count(20)->create();

        $response = $this->getJson('/api/v1/posts?per_page=5');

        $response->assertStatus(200);
        $this->assertEquals(5, count($response->json('data')));
        $this->assertEquals(20, $response->json('meta.total'));
    }
}
