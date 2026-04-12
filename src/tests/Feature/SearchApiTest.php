<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Post;
use App\Models\Tag;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SearchApiTest extends TestCase
{

    // -------------------------------------------------------------------------
    // Validation
    // -------------------------------------------------------------------------

    #[Test]
    public function returns_422_when_query_missing(): void
    {
        $this->getJson('/api/v1/search')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['q']);
    }

    #[Test]
    public function returns_422_when_query_too_short(): void
    {
        $this->getJson('/api/v1/search?q=a')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['q']);
    }

    #[Test]
    public function returns_422_when_query_exceeds_max_length(): void
    {
        $this->getJson('/api/v1/search?q=' . str_repeat('a', 101))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['q']);
    }

    // -------------------------------------------------------------------------
    // Response structure
    // -------------------------------------------------------------------------

    #[Test]
    public function returns_grouped_structure_with_empty_results_for_null_engine(): void
    {
        $this->getJson('/api/v1/search?q=laravel')
            ->assertOk()
            ->assertJsonStructure([
                'query',
                'posts',
                'categories',
                'tags',
            ]);
    }

    #[Test]
    public function response_echoes_query_string(): void
    {
        $this->getJson('/api/v1/search?q=laravel')
            ->assertOk()
            ->assertJsonPath('query', 'laravel');
    }

    #[Test]
    public function posts_categories_and_tags_are_arrays(): void
    {
        $response = $this->getJson('/api/v1/search?q=laravel')->assertOk();

        $this->assertIsArray($response->json('posts'));
        $this->assertIsArray($response->json('categories'));
        $this->assertIsArray($response->json('tags'));
    }

    // -------------------------------------------------------------------------
    // Graceful degradation
    // -------------------------------------------------------------------------

    #[Test]
    public function returns_503_when_search_engine_throws(): void
    {
        // Bind a broken engine that always throws a CommunicationException
        $this->app->bind(\Laravel\Scout\EngineManager::class, function () {
            $manager = \Mockery::mock(\Laravel\Scout\EngineManager::class);
            $manager->shouldReceive('engine')->andThrow(
                new \Meilisearch\Exceptions\CommunicationException('Connection refused')
            );
            return $manager;
        });

        $this->getJson('/api/v1/search?q=laravel')
            ->assertStatus(503)
            ->assertJsonStructure(['query', 'posts', 'categories', 'tags', 'error'])
            ->assertJsonPath('posts', [])
            ->assertJsonPath('categories', [])
            ->assertJsonPath('tags', []);
    }

    // -------------------------------------------------------------------------
    // Minimum query length boundary
    // -------------------------------------------------------------------------

    #[Test]
    public function accepts_two_character_query(): void
    {
        $this->getJson('/api/v1/search?q=ph')
            ->assertOk();
    }

    #[Test]
    public function accepts_100_character_query(): void
    {
        $this->getJson('/api/v1/search?q=' . str_repeat('a', 100))
            ->assertOk();
    }
}
