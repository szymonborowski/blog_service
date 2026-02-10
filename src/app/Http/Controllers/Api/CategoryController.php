<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class CategoryController extends Controller
{
    #[OA\Get(
        path: '/api/v1/categories',
        summary: 'List categories',
        tags: ['Categories'],
        parameters: [
            new OA\Parameter(name: 'root', in: 'query', required: false, schema: new OA\Schema(type: 'boolean')),
            new OA\Parameter(name: 'children_only', in: 'query', required: false, schema: new OA\Schema(type: 'boolean')),
            new OA\Parameter(name: 'parent_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'sort_by', in: 'query', required: false, schema: new OA\Schema(type: 'string', default: 'name')),
            new OA\Parameter(name: 'sort_order', in: 'query', required: false, schema: new OA\Schema(type: 'string', default: 'asc', enum: ['asc', 'desc'])),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 15)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Paginated list of categories', content: new OA\JsonContent(
                properties: [new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Category'))]
            )),
        ]
    )]
    public function index(Request $request)
    {
        $query = Category::query()
            ->with(['parent', 'children'])
            ->withCount('posts');

        // Filter: only root categories (no parent)
        if ($request->has('root') && $request->root) {
            $query->whereNull('parent_id');
        }

        // Filter: only child categories (has parent)
        if ($request->has('children_only') && $request->children_only) {
            $query->whereNotNull('parent_id');
        }

        // Filter by parent
        if ($request->has('parent_id')) {
            $query->where('parent_id', $request->parent_id);
        }

        // Search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where('name', 'like', "%{$search}%");
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'name');
        $sortOrder = $request->get('sort_order', 'asc');
        $query->orderBy($sortBy, $sortOrder);

        $categories = $query->paginate($request->get('per_page', 15));

        return CategoryResource::collection($categories);
    }

    #[OA\Post(
        path: '/api/v1/categories',
        summary: 'Create a category',
        security: [['bearerAuth' => []]],
        tags: ['Categories'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['name', 'slug'],
            properties: [
                new OA\Property(property: 'name', type: 'string', maxLength: 100),
                new OA\Property(property: 'slug', type: 'string', maxLength: 100),
                new OA\Property(property: 'parent_id', type: 'integer', nullable: true),
            ]
        )),
        responses: [
            new OA\Response(response: 201, description: 'Category created', content: new OA\JsonContent(ref: '#/components/schemas/Category')),
            new OA\Response(response: 422, description: 'Validation error'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function store(StoreCategoryRequest $request)
    {
        $validated = $request->validated();

        $category = Category::create($validated);

        return new CategoryResource($category->load(['parent', 'children']));
    }

    #[OA\Get(
        path: '/api/v1/categories/{category}',
        summary: 'Show a category',
        tags: ['Categories'],
        parameters: [
            new OA\Parameter(name: 'category', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Category details', content: new OA\JsonContent(ref: '#/components/schemas/Category')),
            new OA\Response(response: 404, description: 'Category not found'),
        ]
    )]
    public function show(Category $category)
    {
        $category->load(['parent', 'children'])
                 ->loadCount('posts');

        return new CategoryResource($category);
    }

    #[OA\Put(
        path: '/api/v1/categories/{category}',
        summary: 'Update a category',
        security: [['bearerAuth' => []]],
        tags: ['Categories'],
        parameters: [
            new OA\Parameter(name: 'category', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'name', type: 'string', maxLength: 100),
                new OA\Property(property: 'slug', type: 'string', maxLength: 100),
                new OA\Property(property: 'parent_id', type: 'integer', nullable: true),
            ]
        )),
        responses: [
            new OA\Response(response: 200, description: 'Category updated', content: new OA\JsonContent(ref: '#/components/schemas/Category')),
            new OA\Response(response: 404, description: 'Category not found'),
            new OA\Response(response: 422, description: 'Validation error'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function update(UpdateCategoryRequest $request, Category $category)
    {
        $validated = $request->validated();

        $category->update($validated);

        return new CategoryResource($category->load(['parent', 'children']));
    }

    #[OA\Delete(
        path: '/api/v1/categories/{category}',
        summary: 'Delete a category',
        security: [['bearerAuth' => []]],
        tags: ['Categories'],
        parameters: [
            new OA\Parameter(name: 'category', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Category deleted'),
            new OA\Response(response: 404, description: 'Category not found'),
            new OA\Response(response: 422, description: 'Cannot delete category with subcategories'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function destroy(Category $category)
    {
        // Check if category has children
        if ($category->children()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete category with subcategories'
            ], 422);
        }

        // Detach from posts before deleting
        $category->posts()->detach();
        $category->delete();

        return response()->json([
            'message' => 'Category deleted successfully'
        ], 200);
    }
}
