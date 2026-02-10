<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTagRequest;
use App\Http\Requests\UpdateTagRequest;
use App\Http\Resources\TagResource;
use App\Models\Tag;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class TagController extends Controller
{
    #[OA\Get(
        path: '/api/v1/tags',
        summary: 'List tags',
        tags: ['Tags'],
        parameters: [
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'sort_by', in: 'query', required: false, schema: new OA\Schema(type: 'string', default: 'name')),
            new OA\Parameter(name: 'sort_order', in: 'query', required: false, schema: new OA\Schema(type: 'string', default: 'asc', enum: ['asc', 'desc'])),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 15)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Paginated list of tags', content: new OA\JsonContent(
                properties: [new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Tag'))]
            )),
        ]
    )]
    public function index(Request $request)
    {
        $query = Tag::query()->withCount('posts');

        if ($request->has('search')) {
            $search = $request->search;
            $query->where('name', 'like', "%{$search}%");
        }

        $sortBy = $request->get('sort_by', 'name');
        $sortOrder = $request->get('sort_order', 'asc');
        $query->orderBy($sortBy, $sortOrder);

        $tags = $query->paginate($request->get('per_page', 15));

        return TagResource::collection($tags);
    }

    #[OA\Post(
        path: '/api/v1/tags',
        summary: 'Create a tag',
        security: [['bearerAuth' => []]],
        tags: ['Tags'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['name', 'slug'],
            properties: [
                new OA\Property(property: 'name', type: 'string', maxLength: 50),
                new OA\Property(property: 'slug', type: 'string', maxLength: 50),
            ]
        )),
        responses: [
            new OA\Response(response: 201, description: 'Tag created', content: new OA\JsonContent(ref: '#/components/schemas/Tag')),
            new OA\Response(response: 422, description: 'Validation error'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function store(StoreTagRequest $request)
    {
        $tag = Tag::create($request->validated());
        return new TagResource($tag);
    }

    #[OA\Get(
        path: '/api/v1/tags/{tag}',
        summary: 'Show a tag',
        tags: ['Tags'],
        parameters: [new OA\Parameter(name: 'tag', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Tag details', content: new OA\JsonContent(ref: '#/components/schemas/Tag')),
            new OA\Response(response: 404, description: 'Tag not found'),
        ]
    )]
    public function show(Tag $tag)
    {
        $tag->loadCount('posts');
        return new TagResource($tag);
    }

    #[OA\Put(
        path: '/api/v1/tags/{tag}',
        summary: 'Update a tag',
        security: [['bearerAuth' => []]],
        tags: ['Tags'],
        parameters: [new OA\Parameter(name: 'tag', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'name', type: 'string', maxLength: 50),
                new OA\Property(property: 'slug', type: 'string', maxLength: 50),
            ]
        )),
        responses: [
            new OA\Response(response: 200, description: 'Tag updated', content: new OA\JsonContent(ref: '#/components/schemas/Tag')),
            new OA\Response(response: 404, description: 'Tag not found'),
            new OA\Response(response: 422, description: 'Validation error'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function update(UpdateTagRequest $request, Tag $tag)
    {
        $tag->update($request->validated());
        return new TagResource($tag);
    }

    #[OA\Delete(
        path: '/api/v1/tags/{tag}',
        summary: 'Delete a tag',
        security: [['bearerAuth' => []]],
        tags: ['Tags'],
        parameters: [new OA\Parameter(name: 'tag', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Tag deleted'),
            new OA\Response(response: 404, description: 'Tag not found'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function destroy(Tag $tag)
    {
        $tag->posts()->detach();
        $tag->delete();

        return response()->json([
            'message' => 'Tag deleted successfully'
        ], 200);
    }
}
