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
        $this->command->info('🌱 Seeding blog data...');

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
        $phpTag = Tag::create(['name' => 'PHP', 'slug' => 'php']);
        $laravelTag = Tag::create(['name' => 'Laravel', 'slug' => 'laravel']);
        $jsTag = Tag::create(['name' => 'JavaScript', 'slug' => 'javascript']);
        $vueTag = Tag::create(['name' => 'Vue.js', 'slug' => 'vuejs']);
        $dockerTag = Tag::create(['name' => 'Docker', 'slug' => 'docker']);
        $apiTag = Tag::create(['name' => 'API', 'slug' => 'api']);
        $tutorialTag = Tag::create(['name' => 'Tutorial', 'slug' => 'tutorial']);
        $tipsTag = Tag::create(['name' => 'Tips', 'slug' => 'tips']);

        // Create published posts
        $this->command->info('Creating posts...');
        $post1 = Post::factory()->published()->create([
            'title' => 'Getting Started with Laravel 11',
            'slug' => 'getting-started-with-laravel-11',
            'excerpt' => 'Learn how to build modern web applications with Laravel 11',
            'author_id' => 1,
        ]);
        $post1->categories()->attach([$webdev->id, $programming->id]);
        $post1->tags()->attach([$phpTag->id, $laravelTag->id, $tutorialTag->id]);

        $post2 = Post::factory()->published()->create([
            'title' => 'Building REST APIs with Laravel',
            'slug' => 'building-rest-apis-with-laravel',
            'excerpt' => 'A comprehensive guide to creating RESTful APIs',
            'author_id' => 1,
        ]);
        $post2->categories()->attach([$webdev->id]);
        $post2->tags()->attach([$phpTag->id, $laravelTag->id, $apiTag->id]);

        $post3 = Post::factory()->published()->create([
            'title' => 'Docker for PHP Developers',
            'slug' => 'docker-for-php-developers',
            'excerpt' => 'Containerize your PHP applications with Docker',
            'author_id' => 2,
        ]);
        $post3->categories()->attach([$programming->id]);
        $post3->tags()->attach([$phpTag->id, $dockerTag->id, $tutorialTag->id]);

        $post4 = Post::factory()->published()->create([
            'title' => 'Vue.js 3 Composition API',
            'slug' => 'vuejs-3-composition-api',
            'excerpt' => 'Master the new Composition API in Vue.js 3',
            'author_id' => 1,
        ]);
        $post4->categories()->attach([$webdev->id]);
        $post4->tags()->attach([$jsTag->id, $vueTag->id, $tutorialTag->id]);

        // Create some draft posts
        $post5 = Post::factory()->draft()->create([
            'title' => 'Advanced Laravel Patterns',
            'slug' => 'advanced-laravel-patterns',
            'excerpt' => 'Deep dive into Laravel design patterns',
            'author_id' => 1,
        ]);
        $post5->categories()->attach([$webdev->id]);
        $post5->tags()->attach([$phpTag->id, $laravelTag->id]);

        // Create random posts
        $this->command->info('Creating additional random posts...');
        $posts = Post::factory()->count(10)->create();
        
        foreach ($posts as $post) {
            // Attach random categories (1-2)
            $categories = Category::inRandomOrder()->limit(rand(1, 2))->pluck('id');
            $post->categories()->attach($categories);
            
            // Attach random tags (2-4)
            $tags = Tag::inRandomOrder()->limit(rand(2, 4))->pluck('id');
            $post->tags()->attach($tags);
        }

        // Create comments with different statuses
        $this->command->info('Creating comments...');
        $allPosts = Post::all();
        
        foreach ($allPosts->take(5) as $post) {
            // Approved comments
            Comment::create([
                'post_id' => $post->id,
                'author_id' => rand(1, 5),
                'content' => 'Great article! Very informative and well-written.',
                'status' => 'approved',
            ]);

            Comment::create([
                'post_id' => $post->id,
                'author_id' => rand(1, 5),
                'content' => 'Thanks for sharing this. It really helped me understand the concept better.',
                'status' => 'approved',
            ]);

            // Pending comment
            Comment::create([
                'post_id' => $post->id,
                'author_id' => rand(1, 5),
                'content' => 'I have a question about this topic. Can you clarify something?',
                'status' => 'pending',
            ]);

            // Rejected comment (spam)
            if (rand(0, 1)) {
                Comment::create([
                    'post_id' => $post->id,
                    'author_id' => rand(1, 5),
                    'content' => 'Check out my website for more info!!!',
                    'status' => 'rejected',
                ]);
            }
        }

        $this->command->info('✅ Blog seeding completed!');
        $this->command->info('📊 Summary:');
        $this->command->info('   - Categories: ' . Category::count());
        $this->command->info('   - Tags: ' . Tag::count());
        $this->command->info('   - Posts: ' . Post::count());
        $this->command->info('   - Comments: ' . Comment::count());
    }
}
