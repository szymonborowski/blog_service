<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('featured_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->onDelete('cascade');
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->unique('post_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('featured_posts');
    }
};
