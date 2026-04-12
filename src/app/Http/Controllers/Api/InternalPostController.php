<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\StorePostRequest;
use App\Http\Resources\PostResource;
use App\Models\Post;
use Illuminate\Support\Str;

class InternalPostController extends PostController
{
    public function store(StorePostRequest $request)
    {
        $validated = $request->validated();

        $locale = $validated['locale'] ?? 'pl';

        $post = Post::create([
            'uuid'         => Str::uuid(),
            'author_id'    => $request->input('author_id', 1),
            'slug'         => $validated['slug'],
            'status'       => $validated['status'],
            'published_at' => $validated['published_at'] ?? null,
            'cover_image'  => $validated['cover_image'] ?? null,
        ]);

        $post->translations()->create([
            'locale'  => $locale,
            'title'   => $validated['title'],
            'excerpt' => $validated['excerpt'] ?? null,
            'content' => $validated['content'],
            'version' => 1,
        ]);

        if (isset($validated['category_ids'])) {
            $post->categories()->attach($validated['category_ids']);
        }

        if (isset($validated['tag_ids'])) {
            $post->tags()->attach($validated['tag_ids']);
        }

        $post->load(['categories', 'tags', 'translations', 'author']);

        if ($post->shouldBeSearchable()) {
            $post->searchable();
        }

        return (new PostResource($post))->additional(['locale' => $locale]);
    }
}
