<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Comment;
use App\Models\Post;
use App\Models\Tag;
use Illuminate\Database\Seeder;

class BlogSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Seeding blog data...');

        // Create categories with hierarchy
        $this->command->info('Creating categories...');
        $tech = Category::create(['name' => 'Technology', 'slug' => 'technology']);
        $programming = Category::create(['name' => 'Programming', 'slug' => 'programming', 'parent_id' => $tech->id]);
        $webdev = Category::create(['name' => 'Web Development', 'slug' => 'web-development', 'parent_id' => $programming->id]);

        $lifestyle = Category::create(['name' => 'Lifestyle', 'slug' => 'lifestyle']);
        $travel = Category::create(['name' => 'Travel', 'slug' => 'travel', 'parent_id' => $lifestyle->id]);

        $business = Category::create(['name' => 'Business', 'slug' => 'business']);

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

        // Create 100 published posts with comments (author_id 1-10)
        $this->command->info('Creating 100 published posts with comments...');
        $publishedPosts = Post::factory()
            ->count(100)
            ->published()
            ->create([
                'author_id' => fn () => fake()->numberBetween(1, 10),
            ]);

        foreach ($publishedPosts as $post) {
            // Attach random categories (1-2)
            $categories = Category::inRandomOrder()->limit(rand(1, 2))->pluck('id');
            $post->categories()->attach($categories);

            // Attach random tags (2-4)
            $tags = Tag::inRandomOrder()->limit(rand(2, 4))->pluck('id');
            $post->tags()->attach($tags);

            // Add 1-5 comments per post
            $commentCount = rand(1, 5);
            Comment::factory()
                ->count($commentCount)
                ->approved()
                ->create(['post_id' => $post->id]);
        }

        // Create 10 draft posts without comments (pending moderation)
        $this->command->info('Creating 10 draft posts (pending moderation)...');
        $draftPosts = Post::factory()
            ->count(10)
            ->draft()
            ->create([
                'author_id' => fn () => fake()->numberBetween(1, 10),
            ]);

        foreach ($draftPosts as $post) {
            $categories = Category::inRandomOrder()->limit(rand(1, 2))->pluck('id');
            $post->categories()->attach($categories);

            $tags = Tag::inRandomOrder()->limit(rand(2, 4))->pluck('id');
            $post->tags()->attach($tags);
        }

        $this->command->info('Blog seeding completed!');
        $this->command->info('Summary:');
        $this->command->info('   - Categories: ' . Category::count());
        $this->command->info('   - Tags: ' . Tag::count());
        $this->command->info('   - Posts: ' . Post::count() . ' (100 published, 10 draft)');
        $this->command->info('   - Comments: ' . Comment::count());
    }
}
