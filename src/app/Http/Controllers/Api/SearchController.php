<?php

namespace App\Http\Controllers\Api;

use App\Models\Category;
use App\Models\Post;
use App\Models\Tag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'q' => 'required|string|min:2|max:100',
        ]);

        $query = $request->input('q');

        try {
            $postHits = Post::search($query, function ($engine, $q, $options) {
                $options['limit']                 = 5;
                $options['attributesToHighlight'] = ['title', 'excerpt'];
                $options['highlightPreTag']       = '<mark>';
                $options['highlightPostTag']       = '</mark>';
                return $engine->search($q, $options);
            })->raw();

            $categoryHits = Category::search($query, function ($engine, $q, $options) {
                $options['limit'] = 3;
                return $engine->search($q, $options);
            })->raw();

            $tagHits = Tag::search($query, function ($engine, $q, $options) {
                $options['limit'] = 5;
                return $engine->search($q, $options);
            })->raw();

            return response()->json([
                'query'      => $query,
                'posts'      => $postHits['hits']      ?? [],
                'categories' => $categoryHits['hits']  ?? [],
                'tags'       => $tagHits['hits']        ?? [],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'query'      => $query,
                'posts'      => [],
                'categories' => [],
                'tags'       => [],
                'error'      => 'Search service unavailable',
            ], 503);
        }
    }
}
