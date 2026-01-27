<?php

use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CommentController;
use App\Http\Controllers\Api\PostController;
use App\Http\Controllers\Api\TagController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Public endpoints (no auth required)
    Route::get('public/posts', function (Illuminate\Http\Request $request) {
        $request->merge(['public' => true]);
        return app(PostController::class)->index($request);
    });
    Route::get('public/comments', function (Illuminate\Http\Request $request) {
        $request->merge(['public' => true]);
        return app(CommentController::class)->index($request);
    });

    // Read-only endpoints (no auth required)
    Route::get('posts', [PostController::class, 'index']);
    Route::get('posts/{post}', [PostController::class, 'show']);
    Route::get('categories', [CategoryController::class, 'index']);
    Route::get('categories/{category}', [CategoryController::class, 'show']);
    Route::get('tags', [TagController::class, 'index']);
    Route::get('tags/{tag}', [TagController::class, 'show']);
    Route::get('comments', [CommentController::class, 'index']);
    Route::get('comments/{comment}', [CommentController::class, 'show']);

    // Protected endpoints (auth required)
    Route::middleware('auth:api')->group(function () {
        // Posts CRUD (create, update, delete)
        Route::post('posts', [PostController::class, 'store']);
        Route::match(['put', 'patch'], 'posts/{post}', [PostController::class, 'update']);
        Route::delete('posts/{post}', [PostController::class, 'destroy']);

        // Categories CRUD
        Route::post('categories', [CategoryController::class, 'store']);
        Route::match(['put', 'patch'], 'categories/{category}', [CategoryController::class, 'update']);
        Route::delete('categories/{category}', [CategoryController::class, 'destroy']);

        // Tags CRUD
        Route::post('tags', [TagController::class, 'store']);
        Route::match(['put', 'patch'], 'tags/{tag}', [TagController::class, 'update']);
        Route::delete('tags/{tag}', [TagController::class, 'destroy']);

        // Comments CRUD
        Route::post('comments', [CommentController::class, 'store']);
        Route::match(['put', 'patch'], 'comments/{comment}', [CommentController::class, 'update']);
        Route::delete('comments/{comment}', [CommentController::class, 'destroy']);

        // Comment moderation
        Route::patch('comments/{comment}/approve', [CommentController::class, 'approve']);
        Route::patch('comments/{comment}/reject', [CommentController::class, 'reject']);
    });
});
