<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCommentRequest;
use App\Http\Requests\UpdateCommentRequest;
use App\Http\Resources\CommentResource;
use App\Models\Comment;
use Illuminate\Http\Request;

class CommentController extends Controller
{
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

    public function store(StoreCommentRequest $request)
    {
        $validated = $request->validated();
        
        // TODO: Get author_id from authenticated SSO user
        $validated['author_id'] = $request->get('author_id', 1);
        $validated['status'] = 'pending'; // New comments are pending by default

        $comment = Comment::create($validated);

        return new CommentResource($comment->load('post'));
    }

    public function show(Comment $comment)
    {
        $comment->load('post');
        return new CommentResource($comment);
    }

    public function update(UpdateCommentRequest $request, Comment $comment)
    {
        $comment->update($request->validated());
        return new CommentResource($comment->load('post'));
    }

    public function destroy(Comment $comment)
    {
        $comment->delete();

        return response()->json([
            'message' => 'Comment deleted successfully'
        ], 200);
    }

    // Moderation endpoints
    public function approve(Comment $comment)
    {
        $comment->update(['status' => 'approved']);
        return new CommentResource($comment->load('post'));
    }

    public function reject(Comment $comment)
    {
        $comment->update(['status' => 'rejected']);
        return new CommentResource($comment->load('post'));
    }
}
