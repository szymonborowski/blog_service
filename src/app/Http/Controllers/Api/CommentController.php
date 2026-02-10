<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCommentRequest;
use App\Http\Requests\UpdateCommentRequest;
use App\Http\Resources\CommentResource;
use App\Models\Comment;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class CommentController extends Controller
{
    #[OA\Get(
        path: '/api/v1/comments',
        summary: 'List comments',
        tags: ['Comments'],
        parameters: [
            new OA\Parameter(name: 'post_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['pending', 'approved', 'rejected'])),
            new OA\Parameter(name: 'public', in: 'query', required: false, schema: new OA\Schema(type: 'boolean')),
            new OA\Parameter(name: 'sort_by', in: 'query', required: false, schema: new OA\Schema(type: 'string', default: 'created_at')),
            new OA\Parameter(name: 'sort_order', in: 'query', required: false, schema: new OA\Schema(type: 'string', default: 'desc', enum: ['asc', 'desc'])),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 15)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Paginated list of comments', content: new OA\JsonContent(
                properties: [new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Comment'))]
            )),
        ]
    )]
    public function index(Request $request)
    {
        $query = Comment::query()->with('post');

        // Filter by post
        if ($request->has('post_id')) {
            $query->where('post_id', $request->post_id);
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Only approved comments for public
        if ($request->has('public') && $request->public) {
            $query->approved();
        }

        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $comments = $query->paginate($request->get('per_page', 15));

        return CommentResource::collection($comments);
    }

    #[OA\Post(
        path: '/api/v1/comments',
        summary: 'Create a comment',
        security: [['bearerAuth' => []]],
        tags: ['Comments'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['post_id', 'content'],
            properties: [
                new OA\Property(property: 'post_id', type: 'integer'),
                new OA\Property(property: 'content', type: 'string', minLength: 3),
            ]
        )),
        responses: [
            new OA\Response(response: 201, description: 'Comment created', content: new OA\JsonContent(ref: '#/components/schemas/Comment')),
            new OA\Response(response: 422, description: 'Validation error'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function store(StoreCommentRequest $request)
    {
        $validated = $request->validated();

        $validated['author_id'] = $request->user()->id;
        $validated['status'] = 'pending';

        $comment = Comment::create($validated);

        return new CommentResource($comment->load('post'));
    }

    #[OA\Get(
        path: '/api/v1/comments/{comment}',
        summary: 'Show a comment',
        tags: ['Comments'],
        parameters: [new OA\Parameter(name: 'comment', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Comment details', content: new OA\JsonContent(ref: '#/components/schemas/Comment')),
            new OA\Response(response: 404, description: 'Comment not found'),
        ]
    )]
    public function show(Comment $comment)
    {
        $comment->load('post');
        return new CommentResource($comment);
    }

    #[OA\Put(
        path: '/api/v1/comments/{comment}',
        summary: 'Update a comment',
        security: [['bearerAuth' => []]],
        tags: ['Comments'],
        parameters: [new OA\Parameter(name: 'comment', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'content', type: 'string', minLength: 3),
                new OA\Property(property: 'status', type: 'string', enum: ['pending', 'approved', 'rejected']),
            ]
        )),
        responses: [
            new OA\Response(response: 200, description: 'Comment updated', content: new OA\JsonContent(ref: '#/components/schemas/Comment')),
            new OA\Response(response: 404, description: 'Comment not found'),
            new OA\Response(response: 422, description: 'Validation error'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function update(UpdateCommentRequest $request, Comment $comment)
    {
        $comment->update($request->validated());
        return new CommentResource($comment->load('post'));
    }

    #[OA\Delete(
        path: '/api/v1/comments/{comment}',
        summary: 'Delete a comment',
        security: [['bearerAuth' => []]],
        tags: ['Comments'],
        parameters: [new OA\Parameter(name: 'comment', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Comment deleted'),
            new OA\Response(response: 404, description: 'Comment not found'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function destroy(Comment $comment)
    {
        $comment->delete();

        return response()->json([
            'message' => 'Comment deleted successfully'
        ], 200);
    }

    #[OA\Patch(
        path: '/api/v1/comments/{comment}/approve',
        summary: 'Approve a comment',
        security: [['bearerAuth' => []]],
        tags: ['Comments'],
        parameters: [new OA\Parameter(name: 'comment', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Comment approved', content: new OA\JsonContent(ref: '#/components/schemas/Comment')),
            new OA\Response(response: 404, description: 'Comment not found'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function approve(Comment $comment)
    {
        $comment->update(['status' => 'approved']);
        return new CommentResource($comment->load('post'));
    }

    #[OA\Patch(
        path: '/api/v1/comments/{comment}/reject',
        summary: 'Reject a comment',
        security: [['bearerAuth' => []]],
        tags: ['Comments'],
        parameters: [new OA\Parameter(name: 'comment', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Comment rejected', content: new OA\JsonContent(ref: '#/components/schemas/Comment')),
            new OA\Response(response: 404, description: 'Comment not found'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function reject(Comment $comment)
    {
        $comment->update(['status' => 'rejected']);
        return new CommentResource($comment->load('post'));
    }
}
