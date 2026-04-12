<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePostRequest;
use App\Http\Requests\UpdatePostRequest;
use App\Http\Resources\PostResource;
use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
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
            new OA\Parameter(name: 'author_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['draft', 'published', 'archived'])),
            new OA\Parameter(name: 'slug', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'category_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'tag_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'public', in: 'query', required: false, schema: new OA\Schema(type: 'boolean')),
            new OA\Parameter(name: 'locale', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['pl', 'en'])),
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
        $locale = $request->filled('locale') ? $request->input('locale') : null;

        $query = Post::query()
            ->with(['categories', 'tags', 'translations', 'author'])
            ->withCount('comments');

        // Filter by author
        if ($request->filled('author_id')) {
            $query->where('author_id', $request->input('author_id'));
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by slug (exact match on posts.slug)
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

        // Search in translations
        if ($request->has('search')) {
            $search = $request->search;
            $query->whereHas('translations', function ($q) use ($search, $locale) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('title', 'like', "%{$search}%")
                          ->orWhere('content', 'like', "%{$search}%")
                          ->orWhere('excerpt', 'like', "%{$search}%");
                });
                if ($locale) {
                    $q->where('locale', $locale);
                }
            });
        }

        // Filter by locale — only return posts that have a translation in the requested locale
        if ($locale) {
            $query->whereHas('translations', fn ($q) => $q->where('locale', $locale));
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

        return PostResource::collection($posts)->additional(['locale' => $locale]);
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
                new OA\Property(property: 'locale', type: 'string', enum: ['pl', 'en'], nullable: true),
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

        $post = Post::create([
            'uuid'         => Str::uuid(),
            'author_id'    => $request->user()->id,
            'slug'         => $validated['slug'],
            'status'       => $validated['status'],
            'published_at' => $validated['published_at'] ?? null,
            'cover_image'  => $validated['cover_image'] ?? null,
        ]);

        $locale = $validated['locale'] ?? 'pl';
        $post->translations()->create([
            'locale'  => $locale,
            'title'   => $validated['title'],
            'excerpt' => $validated['excerpt'] ?? null,
            'content' => $validated['content'],
            'version' => 1,
        ]);

        // Attach categories
        if (isset($validated['category_ids'])) {
            $post->categories()->attach($validated['category_ids']);
        }

        // Attach tags
        if (isset($validated['tag_ids'])) {
            $post->tags()->attach($validated['tag_ids']);
        }

        $post->load(['categories', 'tags', 'translations', 'author']);

        if ($post->shouldBeSearchable()) {
            $post->searchable();
        }

        return (new PostResource($post))
            ->additional(['locale' => $locale])
            ->response()
            ->setStatusCode(201);
    }

    #[OA\Get(
        path: '/api/v1/posts/{post}',
        summary: 'Show a post',
        tags: ['Posts'],
        parameters: [
            new OA\Parameter(name: 'post', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'locale', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['pl', 'en'])),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Post details', content: new OA\JsonContent(ref: '#/components/schemas/Post')),
            new OA\Response(response: 404, description: 'Post not found'),
        ]
    )]
    public function show(Request $request, Post $post)
    {
        $post->load(['categories', 'tags', 'translations', 'author'])
             ->loadCount('comments');

        $locale = $request->filled('locale') ? $request->input('locale') : null;

        return (new PostResource($post))->additional(['locale' => $locale]);
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
                new OA\Property(property: 'locale', type: 'string', enum: ['pl', 'en'], nullable: true),
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

        $post->update(Arr::only($validated, ['slug', 'status', 'published_at', 'cover_image']));

        $locale = $validated['locale'] ?? $post->translations()->first()?->locale ?? 'pl';

        if (isset($validated['title']) || isset($validated['content']) || isset($validated['excerpt'])) {
            $post->translations()->updateOrCreate(
                ['locale' => $locale],
                array_filter([
                    'title'   => $validated['title'] ?? null,
                    'excerpt' => $validated['excerpt'] ?? null,
                    'content' => $validated['content'] ?? null,
                ], fn ($v) => $v !== null)
            );
        }

        // Sync categories
        if (isset($validated['category_ids'])) {
            $post->categories()->sync($validated['category_ids']);
        }

        // Sync tags
        if (isset($validated['tag_ids'])) {
            $post->tags()->sync($validated['tag_ids']);
        }

        $post->load(['categories', 'tags', 'translations', 'author']);

        if ($post->shouldBeSearchable()) {
            $post->searchable();
        } else {
            $post->unsearchable();
        }

        return (new PostResource($post))->additional(['locale' => $locale]);
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

        return response()->json(['message' => 'Post deleted successfully']);
    }
}
