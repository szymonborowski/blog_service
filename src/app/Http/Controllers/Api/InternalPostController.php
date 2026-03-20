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

        $validated['uuid']    = Str::uuid();
        $validated['locale']  = $validated['locale'] ?? 'pl';
        $validated['version'] = 1;
        $validated['author_id'] = $request->input('author_id', 1);

        $post = Post::create($validated);

        if (isset($validated['category_ids'])) {
            $post->categories()->attach($validated['category_ids']);
        }

        if (isset($validated['tag_ids'])) {
            $post->tags()->attach($validated['tag_ids']);
        }

        return new PostResource($post->load(['categories', 'tags']));
    }
}
