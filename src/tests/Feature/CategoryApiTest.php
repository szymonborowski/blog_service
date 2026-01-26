<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test listing all categories.
     */
    public function test_can_list_categories(): void
    {
        Category::factory()->count(5)->create();

        $response = $this->getJson('/api/v1/categories');

        $response->assertStatus(200)
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

    /**
     * Test creating a category.
     */
    public function test_can_create_category(): void
    {
        $categoryData = [
            'name' => 'Technology',
            'slug' => 'technology',
        ];

        $response = $this->postJson('/api/v1/categories', $categoryData);

        $response->assertStatus(201)
            ->assertJsonFragment([
                'name' => 'Technology',
                'slug' => 'technology',
            ]);

        $this->assertDatabaseHas('categories', [
            'name' => 'Technology',
            'slug' => 'technology',
        ]);
    }

    /**
     * Test creating a subcategory with parent.
     */
    public function test_can_create_subcategory_with_parent(): void
    {
        $parent = Category::factory()->create(['name' => 'Programming']);

        $categoryData = [
            'name' => 'PHP',
            'slug' => 'php',
            'parent_id' => $parent->id,
        ];

        $response = $this->postJson('/api/v1/categories', $categoryData);

        $response->assertStatus(201)
            ->assertJsonFragment([
                'name' => 'PHP',
                'parent_id' => $parent->id,
            ]);

        $this->assertDatabaseHas('categories', [
            'name' => 'PHP',
            'parent_id' => $parent->id,
        ]);
    }

    /**
     * Test validation when creating a category.
     */
    public function test_create_category_validation_fails(): void
    {
        $response = $this->postJson('/api/v1/categories', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'slug']);
    }

    /**
     * Test slug uniqueness validation.
     */
    public function test_create_category_with_duplicate_slug_fails(): void
    {
        Category::factory()->create(['slug' => 'technology']);

        $response = $this->postJson('/api/v1/categories', [
            'name' => 'Tech',
            'slug' => 'technology',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['slug']);
    }

    /**
     * Test showing a single category.
     */
    public function test_can_show_single_category(): void
    {
        $category = Category::factory()->create();

        $response = $this->getJson("/api/v1/categories/{$category->id}");

        $response->assertStatus(200)
            ->assertJsonFragment([
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
            ]);
    }

    /**
     * Test showing category with parent and children.
     */
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

        $response->assertStatus(200)
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

    /**
     * Test updating a category.
     */
    public function test_can_update_category(): void
    {
        $category = Category::factory()->create();

        $updateData = [
            'name' => 'Updated Name',
        ];

        $response = $this->putJson("/api/v1/categories/{$category->id}", $updateData);

        $response->assertStatus(200)
            ->assertJsonFragment([
                'name' => 'Updated Name',
            ]);

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'name' => 'Updated Name',
        ]);
    }

    /**
     * Test deleting a category.
     */
    public function test_can_delete_category(): void
    {
        $category = Category::factory()->create();

        $response = $this->deleteJson("/api/v1/categories/{$category->id}");

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Category deleted successfully'
            ]);

        $this->assertDatabaseMissing('categories', [
            'id' => $category->id,
        ]);
    }

    /**
     * Test cannot delete category with children.
     */
    public function test_cannot_delete_category_with_children(): void
    {
        $parent = Category::factory()->create();
        $child = Category::factory()->create(['parent_id' => $parent->id]);

        $response = $this->deleteJson("/api/v1/categories/{$parent->id}");

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Cannot delete category with subcategories'
            ]);

        $this->assertDatabaseHas('categories', [
            'id' => $parent->id,
        ]);
    }

    /**
     * Test filtering root categories only.
     */
    public function test_can_filter_root_categories(): void
    {
        $root1 = Category::factory()->create(['parent_id' => null]);
        $root2 = Category::factory()->create(['parent_id' => null]);
        $child = Category::factory()->create(['parent_id' => $root1->id]);

        $response = $this->getJson('/api/v1/categories?root=true');

        $response->assertStatus(200);
        $this->assertEquals(2, count($response->json('data')));
    }

    /**
     * Test filtering by parent_id.
     */
    public function test_can_filter_categories_by_parent(): void
    {
        $parent = Category::factory()->create();
        $child1 = Category::factory()->create(['parent_id' => $parent->id]);
        $child2 = Category::factory()->create(['parent_id' => $parent->id]);
        $other = Category::factory()->create();

        $response = $this->getJson("/api/v1/categories?parent_id={$parent->id}");

        $response->assertStatus(200);
        $this->assertEquals(2, count($response->json('data')));
    }

    /**
     * Test searching categories by name.
     */
    public function test_can_search_categories(): void
    {
        Category::factory()->create(['name' => 'Technology']);
        Category::factory()->create(['name' => 'Programming']);
        Category::factory()->create(['name' => 'Design']);

        $response = $this->getJson('/api/v1/categories?search=Tech');

        $response->assertStatus(200);
        $this->assertEquals(1, count($response->json('data')));
        $this->assertStringContainsString('Tech', $response->json('data.0.name'));
    }

    /**
     * Test category with posts count.
     */
    public function test_category_includes_posts_count(): void
    {
        $category = Category::factory()->create();
        $post1 = Post::factory()->create();
        $post2 = Post::factory()->create();

        $post1->categories()->attach($category);
        $post2->categories()->attach($category);

        $response = $this->getJson("/api/v1/categories/{$category->id}");

        $response->assertStatus(200)
            ->assertJsonFragment([
                'posts_count' => 2
            ]);
    }

    /**
     * Test pagination of categories.
     */
    public function test_categories_are_paginated(): void
    {
        Category::factory()->count(20)->create();

        $response = $this->getJson('/api/v1/categories?per_page=5');

        $response->assertStatus(200);
        $this->assertEquals(5, count($response->json('data')));
        $this->assertEquals(20, $response->json('meta.total'));
    }
}
