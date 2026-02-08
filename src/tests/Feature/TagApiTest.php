<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use Tests\Traits\WithJwtAuth;

class TagApiTest extends TestCase
{
    use RefreshDatabase, WithJwtAuth;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpJwtAuth();
    }

    public function test_can_list_tags(): void
    {
        Tag::factory()->count(5)->create();

        $response = $this->getJson('/api/v1/tags');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'slug',
                    ]
                ],
                'links',
                'meta'
            ]);
    }

    public function test_can_create_tag(): void
    {
        $tagData = [
            'name' => 'Laravel',
            'slug' => 'laravel',
        ];

        $response = $this->postJson('/api/v1/tags', $tagData, $this->authHeaders());

        $response->assertCreated()
            ->assertJsonFragment([
                'name' => 'Laravel',
                'slug' => 'laravel',
            ]);

        $this->assertDatabaseHas('tags', [
            'name' => 'Laravel',
            'slug' => 'laravel',
        ]);
    }

    #[DataProvider('invalidTagDataProvider')]
    public function test_create_tag_validation_fails(array $data, array $expectedErrors): void
    {
        $response = $this->postJson('/api/v1/tags', $data, $this->authHeaders());

        $response->assertUnprocessable()
            ->assertJsonValidationErrors($expectedErrors);
    }

    public static function invalidTagDataProvider(): array
    {
        return [
            'empty payload' => [
                [],
                ['name', 'slug'],
            ],
            'missing name' => [
                ['slug' => 'test'],
                ['name'],
            ],
            'missing slug' => [
                ['name' => 'Test'],
                ['slug'],
            ],
            'name too long' => [
                ['name' => str_repeat('a', 51), 'slug' => 'test'],
                ['name'],
            ],
            'slug too long' => [
                ['name' => 'Test', 'slug' => str_repeat('a', 51)],
                ['slug'],
            ],
        ];
    }

    public function test_create_tag_with_duplicate_slug_fails(): void
    {
        Tag::factory()->create(['slug' => 'laravel']);

        $response = $this->postJson('/api/v1/tags', [
            'name' => 'Laravel Framework',
            'slug' => 'laravel',
        ], $this->authHeaders());

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['slug']);
    }

    public function test_can_show_single_tag(): void
    {
        $tag = Tag::factory()->create();

        $response = $this->getJson("/api/v1/tags/{$tag->id}");

        $response->assertOk()
            ->assertJsonFragment([
                'id' => $tag->id,
                'name' => $tag->name,
                'slug' => $tag->slug,
            ]);
    }

    public function test_tag_includes_posts_count(): void
    {
        $tag = Tag::factory()->create();
        $post1 = Post::factory()->create();
        $post2 = Post::factory()->create();

        $post1->tags()->attach($tag);
        $post2->tags()->attach($tag);

        $response = $this->getJson("/api/v1/tags/{$tag->id}");

        $response->assertOk()
            ->assertJsonFragment([
                'posts_count' => 2
            ]);
    }

    public function test_can_update_tag(): void
    {
        $tag = Tag::factory()->create();

        $updateData = [
            'name' => 'Updated Name',
        ];

        $response = $this->putJson("/api/v1/tags/{$tag->id}", $updateData, $this->authHeaders());

        $response->assertOk()
            ->assertJsonFragment([
                'name' => 'Updated Name',
            ]);

        $this->assertDatabaseHas('tags', [
            'id' => $tag->id,
            'name' => 'Updated Name',
        ]);
    }

    public function test_can_delete_tag(): void
    {
        $tag = Tag::factory()->create();

        $response = $this->deleteJson("/api/v1/tags/{$tag->id}", [], $this->authHeaders());

        $response->assertOk()
            ->assertJson([
                'message' => 'Tag deleted successfully'
            ]);

        $this->assertDatabaseMissing('tags', [
            'id' => $tag->id,
        ]);
    }

    public function test_delete_tag_detaches_from_posts(): void
    {
        $tag = Tag::factory()->create();
        $post = Post::factory()->create();
        $post->tags()->attach($tag);

        $this->deleteJson("/api/v1/tags/{$tag->id}", [], $this->authHeaders());

        $this->assertDatabaseMissing('post_tag', [
            'tag_id' => $tag->id,
        ]);
    }

    public function test_can_search_tags(): void
    {
        Tag::factory()->create(['name' => 'Laravel']);
        Tag::factory()->create(['name' => 'PHP']);
        Tag::factory()->create(['name' => 'JavaScript']);

        $response = $this->getJson('/api/v1/tags?search=Laravel');

        $response->assertOk();
        $this->assertEquals(1, count($response->json('data')));
        $this->assertStringContainsString('Laravel', $response->json('data.0.name'));
    }

    public function test_tags_are_paginated(): void
    {
        Tag::factory()->count(20)->create();

        $response = $this->getJson('/api/v1/tags?per_page=5');

        $response->assertOk();
        $this->assertEquals(5, count($response->json('data')));
        $this->assertEquals(20, $response->json('meta.total'));
    }

    public function test_cannot_create_tag_without_token(): void
    {
        $response = $this->postJson('/api/v1/tags', [
            'name' => 'Test',
            'slug' => 'test',
        ]);

        $response->assertUnauthorized();
    }

    public function test_cannot_update_tag_without_token(): void
    {
        $tag = Tag::factory()->create();

        $response = $this->putJson("/api/v1/tags/{$tag->id}", ['name' => 'Updated']);

        $response->assertUnauthorized();
    }

    public function test_cannot_delete_tag_without_token(): void
    {
        $tag = Tag::factory()->create();

        $response = $this->deleteJson("/api/v1/tags/{$tag->id}");

        $response->assertUnauthorized();
    }
}
