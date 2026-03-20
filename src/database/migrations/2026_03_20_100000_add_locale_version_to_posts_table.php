<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->enum('locale', ['pl', 'en'])->default('pl')->after('status');
            $table->unsignedInteger('version')->default(1)->after('locale');

            $table->index('locale');
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropIndex(['locale']);
            $table->dropColumn(['locale', 'version']);
        });
    }
};
