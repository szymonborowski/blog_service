<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Post;
use App\Models\PostTranslation;
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

    // -------------------------------------------------------------------------
    // Index
    // -------------------------------------------------------------------------

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
                        'color',
                    ]
                ],
                'links',
                'meta'
            ]);
    }

    public function test_can_filter_root_categories(): void
    {
        $root1 = Category::factory()->create(['parent_id' => null]);
        $root2 = Category::factory()->create(['parent_id' => null]);
        Category::factory()->create(['parent_id' => $root1->id]);

        $response = $this->getJson('/api/v1/categories?root=true');

        $response->assertOk();
        $this->assertEquals(2, count($response->json('data')));
    }

    public function test_can_filter_categories_by_parent(): void
    {
        $parent = Category::factory()->create();
        Category::factory()->count(2)->create(['parent_id' => $parent->id]);
        Category::factory()->create();

        $response = $this->getJson("/api/v1/categories?parent_id={$parent->id}");

        $response->assertOk();
        $this->assertEquals(2, count($response->json('data')));
    }

    public function test_can_search_categories(): void
    {
        Category::factory()->create(['name' => 'Technology', 'slug' => 'technology']);
        Category::factory()->create(['name' => 'Programming', 'slug' => 'programming']);
        Category::factory()->create(['name' => 'Design', 'slug' => 'design']);

        $response = $this->getJson('/api/v1/categories?search=Tech');

        $response->assertOk();
        $this->assertEquals(1, count($response->json('data')));
        $this->assertStringContainsString('Tech', $response->json('data.0.name'));
    }

    public function test_categories_are_paginated(): void
    {
        Category::factory()->count(20)->create();

        $response = $this->getJson('/api/v1/categories?per_page=5');

        $response->assertOk();
        $this->assertEquals(5, count($response->json('data')));
        $this->assertEquals(20, $response->json('meta.total'));
    }

    // -------------------------------------------------------------------------
    // Show
    // -------------------------------------------------------------------------

    public function test_can_show_single_category(): void
    {
        $category = Category::factory()->create();

        $response = $this->getJson("/api/v1/categories/{$category->id}");

        $response->assertOk()
            ->assertJsonFragment([
                'id'   => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
            ]);
    }

    public function test_can_show_category_with_hierarchy(): void
    {
        $parent   = Category::factory()->create(['name' => 'Parent', 'slug' => 'parent']);
        $category = Category::factory()->create(['name' => 'Child', 'slug' => 'child', 'parent_id' => $parent->id]);
        Category::factory()->create(['name' => 'Subchild', 'slug' => 'subchild', 'parent_id' => $category->id]);

        $response = $this->getJson("/api/v1/categories/{$category->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'parent'   => ['id', 'name', 'slug'],
                    'children' => [
                        '*' => ['id', 'name', 'slug']
                    ],
                ]
            ]);
    }

    public function test_category_includes_posts_count(): void
    {
        $category = Category::factory()->create();

        Post::factory()->published()
            ->has(PostTranslation::factory()->locale('pl'), 'translations')
            ->count(2)
            ->create()
            ->each(fn ($p) => $p->categories()->attach($category));

        $response = $this->getJson("/api/v1/categories/{$category->id}");

        $response->assertOk()
            ->assertJsonFragment(['posts_count' => 2]);
    }

    public function test_posts_count_excludes_drafts_in_index(): void
    {
        // posts_count in index is filtered to published only
        $category = Category::factory()->create(['slug' => 'filtered-cat']);

        Post::factory()->published()
            ->has(PostTranslation::factory()->locale('pl'), 'translations')
            ->count(3)
            ->create()
            ->each(fn ($p) => $p->categories()->attach($category));

        Post::factory()->draft()
            ->has(PostTranslation::factory()->locale('pl'), 'translations')
            ->count(2)
            ->create()
            ->each(fn ($p) => $p->categories()->attach($category));

        $response = $this->getJson('/api/v1/categories');

        $response->assertOk();
        $cat = collect($response->json('data'))->firstWhere('slug', 'filtered-cat');
        $this->assertEquals(3, $cat['posts_count']);
    }

    public function test_posts_count_filtered_by_locale_in_index(): void
    {
        $category = Category::factory()->create(['slug' => 'locale-cat']);

        // 2 posts with PL translation
        Post::factory()->published()
            ->has(PostTranslation::factory()->locale('pl'), 'translations')
            ->count(2)
            ->create()
            ->each(fn ($p) => $p->categories()->attach($category));

        // 1 post with EN translation only
        Post::factory()->published()
            ->has(PostTranslation::factory()->locale('en'), 'translations')
            ->count(1)
            ->create()
            ->each(fn ($p) => $p->categories()->attach($category));

        $response = $this->getJson('/api/v1/categories?locale=pl');

        $response->assertOk();
        $cat = collect($response->json('data'))->firstWhere('slug', 'locale-cat');
        $this->assertEquals(2, $cat['posts_count']);
    }

    // -------------------------------------------------------------------------
    // Color
    // -------------------------------------------------------------------------

    public function test_can_create_category_with_color(): void
    {
        $response = $this->postJson('/api/v1/categories', [
            'name'  => 'Tech',
            'slug'  => 'tech',
            'color' => '#3B82F6',
        ], $this->authHeaders());

        $response->assertCreated()
            ->assertJsonFragment(['color' => '#3B82F6']);

        $this->assertDatabaseHas('categories', ['slug' => 'tech', 'color' => '#3B82F6']);
    }

    public function test_color_is_null_by_default(): void
    {
        $category = Category::factory()->create(['color' => null]);

        $response = $this->getJson("/api/v1/categories/{$category->id}");

        $response->assertOk()
            ->assertJsonFragment(['color' => null]);
    }

    public function test_can_update_category_color(): void
    {
        $category = Category::factory()->create(['color' => '#000000']);

        $this->putJson("/api/v1/categories/{$category->id}", [
            'color' => '#FF0000',
        ], $this->authHeaders())->assertOk();

        $this->assertDatabaseHas('categories', ['id' => $category->id, 'color' => '#FF0000']);
    }

    // -------------------------------------------------------------------------
    // Store / Update / Delete — basic validation
    // -------------------------------------------------------------------------

    public function test_can_create_category(): void
    {
        $response = $this->postJson('/api/v1/categories', [
            'name' => 'Technology',
            'slug' => 'technology',
        ], $this->authHeaders());

        $response->assertCreated()
            ->assertJsonFragment(['name' => 'Technology', 'slug' => 'technology']);

        $this->assertDatabaseHas('categories', ['name' => 'Technology', 'slug' => 'technology']);
    }

    public function test_can_create_subcategory_with_parent(): void
    {
        $parent = Category::factory()->create(['name' => 'Programming', 'slug' => 'programming']);

        $response = $this->postJson('/api/v1/categories', [
            'name'      => 'PHP',
            'slug'      => 'php',
            'parent_id' => $parent->id,
        ], $this->authHeaders());

        $response->assertCreated()
            ->assertJsonFragment(['name' => 'PHP', 'parent_id' => $parent->id]);
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

        $this->postJson('/api/v1/categories', [
            'name' => 'Tech',
            'slug' => 'technology',
        ], $this->authHeaders())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['slug']);
    }

    public function test_can_update_category(): void
    {
        $category = Category::factory()->create();

        $this->putJson("/api/v1/categories/{$category->id}", [
            'name' => 'Updated Name',
        ], $this->authHeaders())
            ->assertOk()
            ->assertJsonFragment(['name' => 'Updated Name']);

        $this->assertDatabaseHas('categories', ['id' => $category->id, 'name' => 'Updated Name']);
    }

    public function test_can_delete_category(): void
    {
        $category = Category::factory()->create();

        $this->deleteJson("/api/v1/categories/{$category->id}", [], $this->authHeaders())
            ->assertOk()
            ->assertJson(['message' => 'Category deleted successfully']);

        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }

    public function test_cannot_delete_category_with_children(): void
    {
        $parent = Category::factory()->create();
        Category::factory()->create(['parent_id' => $parent->id]);

        $this->deleteJson("/api/v1/categories/{$parent->id}", [], $this->authHeaders())
            ->assertUnprocessable()
            ->assertJson(['message' => 'Cannot delete category with subcategories']);

        $this->assertDatabaseHas('categories', ['id' => $parent->id]);
    }
}
