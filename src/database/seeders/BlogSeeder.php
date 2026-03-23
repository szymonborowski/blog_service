<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Comment;
use App\Models\Post;
use App\Models\PostTranslation;
use App\Models\Tag;
use Database\Factories\PostTranslationFactory;
use Illuminate\Database\Seeder;

class BlogSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Seeding blog data...');

        // Create categories with hierarchy
        $this->command->info('Creating categories...');
        $tech = Category::create(['name' => 'Technology', 'slug' => 'technology', 'color' => 'blue']);
        $programming = Category::create(['name' => 'Programming', 'slug' => 'programming', 'parent_id' => $tech->id, 'color' => 'violet']);
        $webdev = Category::create(['name' => 'Web Development', 'slug' => 'web-development', 'parent_id' => $programming->id, 'color' => 'emerald']);

        $lifestyle = Category::create(['name' => 'Lifestyle', 'slug' => 'lifestyle', 'color' => 'rose']);
        $travel = Category::create(['name' => 'Travel', 'slug' => 'travel', 'parent_id' => $lifestyle->id, 'color' => 'cyan']);

        $business = Category::create(['name' => 'Business', 'slug' => 'business', 'color' => 'amber']);

        // Create tags
        $this->command->info('Creating tags...');
        Tag::create(['name' => 'PHP', 'slug' => 'php']);
        Tag::create(['name' => 'Laravel', 'slug' => 'laravel']);
        Tag::create(['name' => 'JavaScript', 'slug' => 'javascript']);
        Tag::create(['name' => 'Vue.js', 'slug' => 'vuejs']);
        Tag::create(['name' => 'Docker', 'slug' => 'docker']);
        Tag::create(['name' => 'API', 'slug' => 'api']);
        Tag::create(['name' => 'Tutorial', 'slug' => 'tutorial']);
        Tag::create(['name' => 'Tips', 'slug' => 'tips']);

        // Create 100 published posts with translations and comments
        $this->command->info('Creating 100 published posts with translations and comments...');
        $publishedPosts = Post::factory()
            ->count(100)
            ->published()
            ->create([
                'author_id' => fn () => fake()->numberBetween(1, 10),
            ]);

        foreach ($publishedPosts as $post) {
            // Polish translation
            $post->translations()->create(
                PostTranslationFactory::new()->make()->toArray()
            );

            // Attach random categories (1-2)
            $categories = Category::inRandomOrder()->limit(rand(1, 2))->pluck('id');
            $post->categories()->attach($categories);

            // Attach random tags (2-4)
            $tags = Tag::inRandomOrder()->limit(rand(2, 4))->pluck('id');
            $post->tags()->attach($tags);

            // Add 1-5 comments per post
            Comment::factory()
                ->count(rand(1, 5))
                ->approved()
                ->create(['post_id' => $post->id]);
        }

        // Create 10 draft posts
        $this->command->info('Creating 10 draft posts...');
        $draftPosts = Post::factory()
            ->count(10)
            ->draft()
            ->create([
                'author_id' => fn () => fake()->numberBetween(1, 10),
            ]);

        foreach ($draftPosts as $post) {
            $post->translations()->create(
                PostTranslationFactory::new()->make()->toArray()
            );

            $categories = Category::inRandomOrder()->limit(rand(1, 2))->pluck('id');
            $post->categories()->attach($categories);

            $tags = Tag::inRandomOrder()->limit(rand(2, 4))->pluck('id');
            $post->tags()->attach($tags);
        }

        $this->command->info('Blog seeding completed!');
        $this->command->info('   - Categories: ' . Category::count());
        $this->command->info('   - Tags: ' . Tag::count());
        $this->command->info('   - Posts: ' . Post::count() . ' (100 published, 10 draft)');
        $this->command->info('   - Translations: ' . PostTranslation::count());
        $this->command->info('   - Comments: ' . Comment::count());
    }
}
