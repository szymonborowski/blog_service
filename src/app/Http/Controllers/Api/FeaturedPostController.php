<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FeaturedPost;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class FeaturedPostController extends Controller
{
    public function index(): JsonResponse
    {
        $posts = Cache::remember('featured_posts.public', 300, function () {
            return FeaturedPost::with(['post.categories', 'post.tags'])
                ->ordered()
                ->get()
                ->map(fn($fp) => array_merge($fp->post->toArray(), [
                    'featured_id' => $fp->id,
                    'position'    => $fp->position,
                    'categories'  => $fp->post->categories,
                    'tags'        => $fp->post->tags,
                ]))
                ->filter(fn($p) => !empty($p['id']))
                ->values();
        });

        return response()->json(['data' => $posts]);
    }

    public function indexAll(): JsonResponse
    {
        $items = FeaturedPost::with(['post'])
            ->ordered()
            ->get();

        return response()->json(['data' => $items]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'post_id' => ['required', 'integer', 'exists:posts,id'],
        ]);

        if (FeaturedPost::where('post_id', $request->post_id)->exists()) {
            return response()->json(['message' => 'Post is already in the list'], 422);
        }

        $position = (FeaturedPost::max('position') ?? -1) + 1;

        $item = FeaturedPost::create([
            'post_id'  => $request->post_id,
            'position' => $position,
        ]);

        Cache::forget('featured_posts.public');

        return response()->json(['data' => $item->load('post')], 201);
    }

    public function destroy(FeaturedPost $featuredPost): JsonResponse
    {
        $featuredPost->delete();
        Cache::forget('featured_posts.public');

        return response()->json(['message' => 'Removed from featured posts']);
    }

    public function reorder(Request $request): JsonResponse
    {
        $request->validate([
            'items'            => ['required', 'array'],
            'items.*.id'       => ['required', 'integer', 'exists:featured_posts,id'],
            'items.*.position' => ['required', 'integer', 'min:0'],
        ]);

        foreach ($request->items as $item) {
            FeaturedPost::where('id', $item['id'])->update(['position' => $item['position']]);
        }

        Cache::forget('featured_posts.public');

        return response()->json(['message' => 'Reordered successfully']);
    }
}
