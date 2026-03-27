<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Post;
use App\Models\PostTranslation;
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

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function postWithTranslation(array $postAttrs = [], array $translationAttrs = []): Post
    {
        return Post::factory()
            ->has(PostTranslation::factory()->state($translationAttrs), 'translations')
            ->create($postAttrs);
    }

    // -------------------------------------------------------------------------
    // Index
    // -------------------------------------------------------------------------

    public function test_can_list_posts(): void
    {
        Post::factory()
            ->has(PostTranslation::factory(), 'translations')
            ->count(5)
            ->create();

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
                        'cover_image',
                        'status',
                        'locale',
                        'version',
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
        Post::factory()->published()
            ->has(PostTranslation::factory(), 'translations')
            ->count(3)
            ->create();

        Post::factory()->draft()
            ->has(PostTranslation::factory(), 'translations')
            ->count(2)
            ->create();

        $response = $this->getJson('/api/v1/public/posts');

        $response->assertOk();
        $this->assertEquals(3, count($response->json('data')));
    }

    public function test_can_filter_posts_by_locale(): void
    {
        Post::factory()
            ->has(PostTranslation::factory()->locale('pl'), 'translations')
            ->count(3)
            ->create();

        Post::factory()
            ->has(PostTranslation::factory()->locale('en'), 'translations')
            ->count(2)
            ->create();

        $response = $this->getJson('/api/v1/posts?locale=pl');

        $response->assertOk();
        $this->assertEquals(3, count($response->json('data')));
        $this->assertEquals('pl', $response->json('data.0.locale'));
    }

    public function test_locale_filter_returns_fallback_translation_when_requested_locale_missing(): void
    {
        // Post with only PL translation
        $this->postWithTranslation([], ['locale' => 'pl', 'title' => 'Tytuł PL']);

        // Fetching without locale filter — should return the PL translation
        $response = $this->getJson('/api/v1/posts');

        $response->assertOk();
        $this->assertEquals('Tytuł PL', $response->json('data.0.title'));
    }

    public function test_response_includes_locale_and_version_from_translation(): void
    {
        $this->postWithTranslation([], ['locale' => 'pl', 'version' => 3]);

        $response = $this->getJson('/api/v1/posts?locale=pl');

        $response->assertOk()
            ->assertJsonFragment([
                'locale'  => 'pl',
                'version' => 3,
            ]);
    }

    public function test_can_filter_posts_by_status(): void
    {
        Post::factory()->published()
            ->has(PostTranslation::factory(), 'translations')
            ->count(3)
            ->create();

        Post::factory()->draft()
            ->has(PostTranslation::factory(), 'translations')
            ->count(2)
            ->create();

        $response = $this->getJson('/api/v1/posts?status=published');

        $response->assertOk();
        $this->assertEquals(3, count($response->json('data')));
    }

    public function test_can_filter_posts_by_category(): void
    {
        $category1 = Category::factory()->create();
        $category2 = Category::factory()->create();

        $post1 = $this->postWithTranslation();
        $post1->categories()->attach($category1);

        $post2 = $this->postWithTranslation();
        $post2->categories()->attach($category2);

        $response = $this->getJson("/api/v1/posts?category_id={$category1->id}");

        $response->assertOk();
        $this->assertEquals(1, count($response->json('data')));
        $this->assertEquals($post1->id, $response->json('data.0.id'));
    }

    public function test_can_filter_posts_by_tag(): void
    {
        $tag1 = Tag::factory()->create();
        $tag2 = Tag::factory()->create();

        $post1 = $this->postWithTranslation();
        $post1->tags()->attach($tag1);

        $post2 = $this->postWithTranslation();
        $post2->tags()->attach($tag2);

        $response = $this->getJson("/api/v1/posts?tag_id={$tag1->id}");

        $response->assertOk();
        $this->assertEquals(1, count($response->json('data')));
        $this->assertEquals($post1->id, $response->json('data.0.id'));
    }

    public function test_can_search_posts_in_translations(): void
    {
        $this->postWithTranslation([], ['title' => 'Laravel Tutorial', 'locale' => 'pl']);
        $this->postWithTranslation([], ['title' => 'PHP Best Practices', 'locale' => 'pl']);
        $this->postWithTranslation([], ['title' => 'JavaScript Basics', 'locale' => 'pl']);

        $response = $this->getJson('/api/v1/posts?search=Laravel');

        $response->assertOk();
        $this->assertEquals(1, count($response->json('data')));
        $this->assertStringContainsString('Laravel', $response->json('data.0.title'));
    }

    public function test_search_respects_locale_filter(): void
    {
        $this->postWithTranslation([], ['title' => 'Laravel PL', 'locale' => 'pl']);
        $this->postWithTranslation([], ['title' => 'Laravel EN', 'locale' => 'en']);

        $response = $this->getJson('/api/v1/posts?search=Laravel&locale=pl');

        $response->assertOk();
        $this->assertEquals(1, count($response->json('data')));
        $this->assertEquals('pl', $response->json('data.0.locale'));
    }

    public function test_posts_are_paginated(): void
    {
        Post::factory()
            ->has(PostTranslation::factory(), 'translations')
            ->count(20)
            ->create();

        $response = $this->getJson('/api/v1/posts?per_page=5');

        $response->assertOk();
        $this->assertEquals(5, count($response->json('data')));
        $this->assertEquals(20, $response->json('meta.total'));
    }

    // -------------------------------------------------------------------------
    // Show
    // -------------------------------------------------------------------------

    public function test_can_show_single_post(): void
    {
        $post = $this->postWithTranslation(['slug' => 'my-post'], ['title' => 'My Post', 'locale' => 'pl']);

        $response = $this->getJson("/api/v1/posts/{$post->id}");

        $response->assertOk()
            ->assertJsonFragment([
                'id'    => $post->id,
                'slug'  => 'my-post',
                'title' => 'My Post',
            ]);
    }

    public function test_show_returns_requested_locale_translation(): void
    {
        $post = Post::factory()
            ->has(PostTranslation::factory()->locale('pl')->state(['title' => 'Tytuł']), 'translations')
            ->has(PostTranslation::factory()->locale('en')->state(['title' => 'Title']), 'translations')
            ->create();

        $response = $this->getJson("/api/v1/posts/{$post->id}?locale=en");

        $response->assertOk()
            ->assertJsonFragment(['title' => 'Title', 'locale' => 'en']);
    }

    public function test_can_show_post_with_relationships(): void
    {
        $post     = $this->postWithTranslation();
        $category = Category::factory()->create();
        $tag      = Tag::factory()->create();

        $post->categories()->attach($category);
        $post->tags()->attach($tag);

        $response = $this->getJson("/api/v1/posts/{$post->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'title',
                    'categories' => [
                        '*' => ['id', 'name', 'slug', 'color']
                    ],
                    'tags' => [
                        '*' => ['id', 'name', 'slug']
                    ],
                    'comments_count',
                ]
            ]);
    }

    public function test_response_includes_author(): void
    {
        $post = $this->postWithTranslation();
        $post->load('author');

        $response = $this->getJson("/api/v1/posts/{$post->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'author_id',
                    'author',
                ]
            ]);
    }

    public function test_response_includes_cover_image(): void
    {
        $post = $this->postWithTranslation(['cover_image' => 'posts/cover.jpg']);

        $response = $this->getJson("/api/v1/posts/{$post->id}");

        $response->assertOk()
            ->assertJsonFragment(['cover_image' => 'posts/cover.jpg']);
    }

    // -------------------------------------------------------------------------
    // Store
    // -------------------------------------------------------------------------

    public function test_can_create_post(): void
    {
        $category = Category::factory()->create();
        $tag      = Tag::factory()->create();

        $postData = [
            'title'        => 'Test Post',
            'slug'         => 'test-post',
            'excerpt'      => 'Short excerpt',
            'content'      => 'Full content of the post.',
            'locale'       => 'pl',
            'status'       => 'draft',
            'category_ids' => [$category->id],
            'tag_ids'      => [$tag->id],
        ];

        $response = $this->postJson('/api/v1/posts', $postData, $this->authHeaders());

        $response->assertCreated()
            ->assertJsonFragment([
                'title'  => 'Test Post',
                'slug'   => 'test-post',
                'status' => 'draft',
                'locale' => 'pl',
            ]);

        $this->assertDatabaseHas('posts', ['slug' => 'test-post']);
        $this->assertDatabaseHas('post_translations', [
            'title'   => 'Test Post',
            'locale'  => 'pl',
            'version' => 1,
        ]);
    }

    public function test_create_post_defaults_locale_to_pl(): void
    {
        $response = $this->postJson('/api/v1/posts', [
            'title'   => 'No Locale Post',
            'slug'    => 'no-locale-post',
            'content' => 'Content.',
            'status'  => 'draft',
        ], $this->authHeaders());

        $response->assertCreated();
        $this->assertDatabaseHas('post_translations', ['locale' => 'pl']);
    }

    public function test_create_post_with_cover_image(): void
    {
        $response = $this->postJson('/api/v1/posts', [
            'title'       => 'Cover Post',
            'slug'        => 'cover-post',
            'content'     => 'Content.',
            'status'      => 'draft',
            'cover_image' => 'posts/hero.jpg',
        ], $this->authHeaders());

        $response->assertCreated()
            ->assertJsonFragment(['cover_image' => 'posts/hero.jpg']);

        $this->assertDatabaseHas('posts', ['cover_image' => 'posts/hero.jpg']);
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
            'invalid locale' => [
                ['title' => 'Test', 'slug' => 'test', 'content' => 'Content', 'status' => 'draft', 'locale' => 'de'],
                ['locale'],
            ],
        ];
    }

    // -------------------------------------------------------------------------
    // Update
    // -------------------------------------------------------------------------

    public function test_can_update_post(): void
    {
        $post = $this->postWithTranslation([], ['title' => 'Old Title', 'locale' => 'pl']);

        $response = $this->putJson("/api/v1/posts/{$post->id}", [
            'title'   => 'Updated Title',
            'content' => 'Updated content',
            'locale'  => 'pl',
        ], $this->authHeaders());

        $response->assertOk()
            ->assertJsonFragment(['title' => 'Updated Title']);

        $this->assertDatabaseHas('post_translations', [
            'post_id' => $post->id,
            'locale'  => 'pl',
            'title'   => 'Updated Title',
        ]);
    }

    public function test_can_add_translation_for_second_locale(): void
    {
        $post = $this->postWithTranslation([], ['locale' => 'pl', 'title' => 'Tytuł']);

        $response = $this->putJson("/api/v1/posts/{$post->id}", [
            'locale'  => 'en',
            'title'   => 'English Title',
            'content' => 'English content.',
        ], $this->authHeaders());

        $response->assertOk();

        $this->assertDatabaseHas('post_translations', ['post_id' => $post->id, 'locale' => 'pl']);
        $this->assertDatabaseHas('post_translations', ['post_id' => $post->id, 'locale' => 'en', 'title' => 'English Title']);
    }

    public function test_can_update_post_relationships(): void
    {
        $post        = $this->postWithTranslation();
        $oldCategory = Category::factory()->create();
        $newCategory = Category::factory()->create();

        $post->categories()->attach($oldCategory);

        $response = $this->putJson("/api/v1/posts/{$post->id}", [
            'category_ids' => [$newCategory->id],
        ], $this->authHeaders());

        $response->assertOk();

        $this->assertDatabaseHas('category_post', [
            'post_id'     => $post->id,
            'category_id' => $newCategory->id,
        ]);
        $this->assertDatabaseMissing('category_post', [
            'post_id'     => $post->id,
            'category_id' => $oldCategory->id,
        ]);
    }

    public function test_can_update_cover_image(): void
    {
        $post = $this->postWithTranslation(['cover_image' => null]);

        $this->putJson("/api/v1/posts/{$post->id}", [
            'cover_image' => 'posts/new-cover.jpg',
        ], $this->authHeaders())->assertOk();

        $this->assertDatabaseHas('posts', [
            'id'          => $post->id,
            'cover_image' => 'posts/new-cover.jpg',
        ]);
    }

    // -------------------------------------------------------------------------
    // Delete
    // -------------------------------------------------------------------------

    public function test_can_delete_post(): void
    {
        $post = $this->postWithTranslation();

        $response = $this->deleteJson("/api/v1/posts/{$post->id}", [], $this->authHeaders());

        $response->assertOk()
            ->assertJson(['message' => 'Post deleted successfully']);

        $this->assertSoftDeleted('posts', ['id' => $post->id]);
    }

    // -------------------------------------------------------------------------
    // Slug filter
    // -------------------------------------------------------------------------

    public function test_can_filter_posts_by_slug(): void
    {
        $this->postWithTranslation(['slug' => 'unique-slug']);
        $this->postWithTranslation(['slug' => 'other-slug']);

        $response = $this->getJson('/api/v1/posts?slug=unique-slug');

        $response->assertOk();
        $this->assertEquals(1, count($response->json('data')));
        $this->assertEquals('unique-slug', $response->json('data.0.slug'));
    }
}
