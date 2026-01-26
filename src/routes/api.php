<?php

use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\PostController;
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
});
