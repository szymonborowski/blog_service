<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePostRequest;
use App\Http\Requests\UpdatePostRequest;
use App\Http\Resources\PostResource;
use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;

class PostController extends Controller
{
    #[OA\Get(
        path: '/api/v1/posts',
        summary: 'List posts',
        description: 'List posts with filtering, searching, sorting and pagination',
        tags: ['Posts'],
        parameters: [
            new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['draft', 'published', 'archived'])),
            new OA\Parameter(name: 'slug', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'category_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'tag_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'public', in: 'query', required: false, schema: new OA\Schema(type: 'boolean')),
            new OA\Parameter(name: 'sort_by', in: 'query', required: false, schema: new OA\Schema(type: 'string', default: 'created_at')),
            new OA\Parameter(name: 'sort_order', in: 'query', required: false, schema: new OA\Schema(type: 'string', default: 'desc', enum: ['asc', 'desc'])),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 15)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Paginated list of posts', content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Post')),
                ]
            )),
        ]
    )]
    public function index(Request $request)
    {
        $query = Post::query()
            ->with(['categories', 'tags'])
            ->withCount('comments');

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by slug (exact match)
        if ($request->filled('slug')) {
            $query->where('slug', $request->slug);
        }

        // Filter by category
        if ($request->has('category_id')) {
            $query->whereHas('categories', function ($q) use ($request) {
                $q->where('categories.id', $request->category_id);
            });
        }

        // Filter by tag
        if ($request->has('tag_id')) {
            $query->whereHas('tags', function ($q) use ($request) {
                $q->where('tags.id', $request->tag_id);
            });
        }

        // Search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('content', 'like', "%{$search}%")
                  ->orWhere('excerpt', 'like', "%{$search}%");
            });
        }

        // Only published posts for public API
        if ($request->has('public') && $request->public) {
            $query->published();
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $posts = $query->paginate($request->get('per_page', 15));

        return PostResource::collection($posts);
    }

    #[OA\Post(
        path: '/api/v1/posts',
        summary: 'Create a post',
        security: [['bearerAuth' => []]],
        tags: ['Posts'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['title', 'slug', 'content', 'status'],
            properties: [
                new OA\Property(property: 'title', type: 'string', maxLength: 255),
                new OA\Property(property: 'slug', type: 'string', maxLength: 255),
                new OA\Property(property: 'excerpt', type: 'string', maxLength: 500, nullable: true),
                new OA\Property(property: 'content', type: 'string'),
                new OA\Property(property: 'status', type: 'string', enum: ['draft', 'published', 'archived']),
                new OA\Property(property: 'published_at', type: 'string', format: 'date-time', nullable: true),
                new OA\Property(property: 'category_ids', type: 'array', items: new OA\Items(type: 'integer'), nullable: true),
                new OA\Property(property: 'tag_ids', type: 'array', items: new OA\Items(type: 'integer'), nullable: true),
            ]
        )),
        responses: [
            new OA\Response(response: 201, description: 'Post created', content: new OA\JsonContent(ref: '#/components/schemas/Post')),
            new OA\Response(response: 422, description: 'Validation error'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function store(StorePostRequest $request)
    {
        $validated = $request->validated();

        // Generate UUID
        $validated['uuid'] = Str::uuid();

        $validated['author_id'] = $request->user()->id;

        $post = Post::create($validated);

        // Attach categories
        if (isset($validated['category_ids'])) {
            $post->categories()->attach($validated['category_ids']);
        }

        // Attach tags
        if (isset($validated['tag_ids'])) {
            $post->tags()->attach($validated['tag_ids']);
        }

        return new PostResource($post->load(['categories', 'tags']));
    }

    #[OA\Get(
        path: '/api/v1/posts/{post}',
        summary: 'Show a post',
        tags: ['Posts'],
        parameters: [
            new OA\Parameter(name: 'post', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Post details', content: new OA\JsonContent(ref: '#/components/schemas/Post')),
            new OA\Response(response: 404, description: 'Post not found'),
        ]
    )]
    public function show(Post $post)
    {
        $post->load(['categories', 'tags'])
             ->loadCount('comments');

        return new PostResource($post);
    }

    #[OA\Put(
        path: '/api/v1/posts/{post}',
        summary: 'Update a post',
        security: [['bearerAuth' => []]],
        tags: ['Posts'],
        parameters: [
            new OA\Parameter(name: 'post', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'title', type: 'string', maxLength: 255),
                new OA\Property(property: 'slug', type: 'string', maxLength: 255),
                new OA\Property(property: 'excerpt', type: 'string', maxLength: 500, nullable: true),
                new OA\Property(property: 'content', type: 'string'),
                new OA\Property(property: 'status', type: 'string', enum: ['draft', 'published', 'archived']),
                new OA\Property(property: 'published_at', type: 'string', format: 'date-time', nullable: true),
                new OA\Property(property: 'category_ids', type: 'array', items: new OA\Items(type: 'integer'), nullable: true),
                new OA\Property(property: 'tag_ids', type: 'array', items: new OA\Items(type: 'integer'), nullable: true),
            ]
        )),
        responses: [
            new OA\Response(response: 200, description: 'Post updated', content: new OA\JsonContent(ref: '#/components/schemas/Post')),
            new OA\Response(response: 404, description: 'Post not found'),
            new OA\Response(response: 422, description: 'Validation error'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function update(UpdatePostRequest $request, Post $post)
    {
        $validated = $request->validated();

        $post->update($validated);

        // Sync categories
        if (isset($validated['category_ids'])) {
            $post->categories()->sync($validated['category_ids']);
        }

        // Sync tags
        if (isset($validated['tag_ids'])) {
            $post->tags()->sync($validated['tag_ids']);
        }

        return new PostResource($post->load(['categories', 'tags']));
    }

    #[OA\Delete(
        path: '/api/v1/posts/{post}',
        summary: 'Delete a post',
        description: 'Soft deletes the post',
        security: [['bearerAuth' => []]],
        tags: ['Posts'],
        parameters: [
            new OA\Parameter(name: 'post', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Post deleted'),
            new OA\Response(response: 404, description: 'Post not found'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function destroy(Post $post)
    {
        $post->delete();

        return response()->json([
            'message' => 'Post deleted successfully'
        ], 200);
    }
}
