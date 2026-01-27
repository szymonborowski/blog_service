<?php

use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CommentController;
use App\Http\Controllers\Api\PostController;
use App\Http\Controllers\Api\TagController;
use Illuminate\Support\Facades\Route;
// Blog API Routes
Route::prefix('v1')->group(function () {
    // Posts
    Route::apiResource('posts', PostController::class);

    // Public posts endpoint (only published)
    Route::get('public/posts', function (Illuminate\Http\Request $request) {
        $request->merge(['public' => true]);
        return app(App\Http\Controllers\Api\PostController::class)->index($request);
    });

    // Categories
    Route::apiResource('categories', CategoryController::class);

    // Tags
    Route::apiResource('tags', TagController::class);

    // Comments
    Route::apiResource('comments', CommentController::class);

    // Comment moderation endpoints
    Route::patch('comments/{comment}/approve', [CommentController::class, 'approve']);
    Route::patch('comments/{comment}/reject', [CommentController::class, 'reject']);

    // Public comments endpoint (only approved)
    Route::get('public/comments', function (Illuminate\Http\Request $request) {
        $request->merge(['public' => true]);
        return app(App\Http\Controllers\Api\CommentController::class)->index($request);
    });
});
