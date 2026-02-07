<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use Tests\Traits\WithJwtAuth;

class CategoryApiTest extends TestCase
{
    use RefreshDatabase, WithJwtAuth;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpJwtAuth();
    }

    public function test_can_list_categories(): void
    {
        Category::factory()->count(5)->create();

        $response = $this->getJson('/api/v1/categories');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'slug',
                        'parent_id',
                    ]
                ],
                'links',
                'meta'
            ]);
    }

    public function test_can_create_category(): void
    {
        $categoryData = [
            'name' => 'Technology',
            'slug' => 'technology',
        ];

        $response = $this->postJson('/api/v1/categories', $categoryData, $this->authHeaders());

        $response->assertCreated()
            ->assertJsonFragment([
                'name' => 'Technology',
                'slug' => 'technology',
            ]);

        $this->assertDatabaseHas('categories', [
            'name' => 'Technology',
            'slug' => 'technology',
        ]);
    }

    public function test_can_create_subcategory_with_parent(): void
    {
        $parent = Category::factory()->create(['name' => 'Programming']);

        $categoryData = [
            'name' => 'PHP',
            'slug' => 'php',
            'parent_id' => $parent->id,
        ];

        $response = $this->postJson('/api/v1/categories', $categoryData, $this->authHeaders());

        $response->assertCreated()
            ->assertJsonFragment([
                'name' => 'PHP',
                'parent_id' => $parent->id,
            ]);

        $this->assertDatabaseHas('categories', [
            'name' => 'PHP',
            'parent_id' => $parent->id,
        ]);
    }

    #[DataProvider('invalidCategoryDataProvider')]
    public function test_create_category_validation_fails(array $data, array $expectedErrors): void
    {
        $response = $this->postJson('/api/v1/categories', $data, $this->authHeaders());

        $response->assertUnprocessable()
            ->assertJsonValidationErrors($expectedErrors);
    }

    public static function invalidCategoryDataProvider(): array
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
                ['name' => str_repeat('a', 101), 'slug' => 'test'],
                ['name'],
            ],
            'slug too long' => [
                ['name' => 'Test', 'slug' => str_repeat('a', 101)],
                ['slug'],
            ],
            'invalid parent_id' => [
                ['name' => 'Test', 'slug' => 'test', 'parent_id' => 9999],
                ['parent_id'],
            ],
        ];
    }

    public function test_create_category_with_duplicate_slug_fails(): void
    {
        Category::factory()->create(['slug' => 'technology']);

        $response = $this->postJson('/api/v1/categories', [
            'name' => 'Tech',
            'slug' => 'technology',
        ], $this->authHeaders());

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['slug']);
    }

    public function test_can_show_single_category(): void
    {
        $category = Category::factory()->create();

        $response = $this->getJson("/api/v1/categories/{$category->id}");

        $response->assertOk()
            ->assertJsonFragment([
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
            ]);
    }

    public function test_can_show_category_with_hierarchy(): void
    {
        $parent = Category::factory()->create(['name' => 'Parent']);
        $category = Category::factory()->create([
            'name' => 'Child',
            'parent_id' => $parent->id,
        ]);
        $subchild = Category::factory()->create([
            'name' => 'Subchild',
            'parent_id' => $category->id,
        ]);

        $response = $this->getJson("/api/v1/categories/{$category->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'parent' => ['id', 'name', 'slug'],
                    'children' => [
                        '*' => ['id', 'name', 'slug']
                    ],
                ]
            ]);
    }

    public function test_can_update_category(): void
    {
        $category = Category::factory()->create();

        $updateData = [
            'name' => 'Updated Name',
        ];

        $response = $this->putJson("/api/v1/categories/{$category->id}", $updateData, $this->authHeaders());

        $response->assertOk()
            ->assertJsonFragment([
                'name' => 'Updated Name',
            ]);

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'name' => 'Updated Name',
        ]);
    }

    public function test_can_delete_category(): void
    {
        $category = Category::factory()->create();

        $response = $this->deleteJson("/api/v1/categories/{$category->id}", [], $this->authHeaders());

        $response->assertOk()
            ->assertJson([
                'message' => 'Category deleted successfully'
            ]);

        $this->assertDatabaseMissing('categories', [
            'id' => $category->id,
        ]);
    }

    public function test_cannot_delete_category_with_children(): void
    {
        $parent = Category::factory()->create();
        $child = Category::factory()->create(['parent_id' => $parent->id]);

        $response = $this->deleteJson("/api/v1/categories/{$parent->id}", [], $this->authHeaders());

        $response->assertUnprocessable()
            ->assertJson([
                'message' => 'Cannot delete category with subcategories'
            ]);

        $this->assertDatabaseHas('categories', [
            'id' => $parent->id,
        ]);
    }

    public function test_can_filter_root_categories(): void
    {
        $root1 = Category::factory()->create(['parent_id' => null]);
        $root2 = Category::factory()->create(['parent_id' => null]);
        $child = Category::factory()->create(['parent_id' => $root1->id]);

        $response = $this->getJson('/api/v1/categories?root=true');

        $response->assertOk();
        $this->assertEquals(2, count($response->json('data')));
    }

    public function test_can_filter_categories_by_parent(): void
    {
        $parent = Category::factory()->create();
        $child1 = Category::factory()->create(['parent_id' => $parent->id]);
        $child2 = Category::factory()->create(['parent_id' => $parent->id]);
        $other = Category::factory()->create();

        $response = $this->getJson("/api/v1/categories?parent_id={$parent->id}");

        $response->assertOk();
        $this->assertEquals(2, count($response->json('data')));
    }

    public function test_can_search_categories(): void
    {
        Category::factory()->create(['name' => 'Technology']);
        Category::factory()->create(['name' => 'Programming']);
        Category::factory()->create(['name' => 'Design']);

        $response = $this->getJson('/api/v1/categories?search=Tech');

        $response->assertOk();
        $this->assertEquals(1, count($response->json('data')));
        $this->assertStringContainsString('Tech', $response->json('data.0.name'));
    }

    public function test_category_includes_posts_count(): void
    {
        $category = Category::factory()->create();
        $post1 = Post::factory()->create();
        $post2 = Post::factory()->create();

        $post1->categories()->attach($category);
        $post2->categories()->attach($category);

        $response = $this->getJson("/api/v1/categories/{$category->id}");

        $response->assertOk()
            ->assertJsonFragment([
                'posts_count' => 2
            ]);
    }

    public function test_categories_are_paginated(): void
    {
        Category::factory()->count(20)->create();

        $response = $this->getJson('/api/v1/categories?per_page=5');

        $response->assertOk();
        $this->assertEquals(5, count($response->json('data')));
        $this->assertEquals(20, $response->json('meta.total'));
    }
}
