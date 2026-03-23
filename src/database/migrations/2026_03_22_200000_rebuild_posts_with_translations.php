<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        Schema::dropIfExists('featured_posts');
        Schema::dropIfExists('post_tag');
        Schema::dropIfExists('category_post');
        Schema::dropIfExists('comments');
        Schema::dropIfExists('post_translations');
        Schema::dropIfExists('posts');

        Schema::enableForeignKeyConstraints();

        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('author_id')->index();
            $table->string('slug')->unique();
            $table->string('cover_image')->nullable();
            $table->enum('status', ['draft', 'published', 'archived'])->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['status', 'published_at']);
        });

        Schema::create('post_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained('posts')->cascadeOnDelete();
            $table->enum('locale', ['pl', 'en'])->default('pl');
            $table->string('title');
            $table->text('excerpt')->nullable();
            $table->longText('content');
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->unique(['post_id', 'locale']);
        });

        Schema::create('category_post', function (Blueprint $table) {
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->primary(['category_id', 'post_id']);
        });

        Schema::create('post_tag', function (Blueprint $table) {
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->primary(['post_id', 'tag_id']);
        });

        Schema::create('featured_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
            $table->unique('post_id');
        });

        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('author_id');
            $table->text('content');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('comments');
        Schema::dropIfExists('featured_posts');
        Schema::dropIfExists('post_tag');
        Schema::dropIfExists('category_post');
        Schema::dropIfExists('post_translations');
        Schema::dropIfExists('posts');
        Schema::enableForeignKeyConstraints();
    }
};
