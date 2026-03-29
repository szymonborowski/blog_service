<?php

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use Tests\Traits\WithJwtAuth;

class CommentApiTest extends TestCase
{
    use RefreshDatabase, WithJwtAuth;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpJwtAuth();
    }

    public function test_can_list_comments(): void
    {
        $post = Post::factory()->create();
        Comment::factory()->count(3)->create(['post_id' => $post->id]);

        $response = $this->getJson('/api/v1/comments');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'post_id',
                        'author_id',
                        'content',
                        'status',
                    ]
                ],
                'links',
                'meta'
            ]);
    }

    public function test_can_filter_comments_by_post_id(): void
    {
        $post1 = Post::factory()->create();
        $post2 = Post::factory()->create();
        Comment::factory()->create(['post_id' => $post1->id, 'content' => 'Comment one']);
        Comment::factory()->create(['post_id' => $post2->id, 'content' => 'Comment two']);

        $response = $this->getJson("/api/v1/comments?post_id={$post1->id}");

        $response->assertOk();
        $this->assertEquals(1, count($response->json('data')));
        $this->assertEquals($post1->id, $response->json('data.0.post_id'));
    }

    public function test_can_filter_comments_by_status(): void
    {
        $post = Post::factory()->create();
        Comment::factory()->approved()->count(2)->create(['post_id' => $post->id]);
        Comment::factory()->pending()->count(1)->create(['post_id' => $post->id]);

        $response = $this->getJson('/api/v1/comments?status=approved');

        $response->assertOk();
        $this->assertEquals(2, count($response->json('data')));
    }

    public function test_public_comments_endpoint_returns_only_approved(): void
    {
        $post = Post::factory()->create();
        Comment::factory()->approved()->count(2)->create(['post_id' => $post->id]);
        Comment::factory()->pending()->create(['post_id' => $post->id]);

        $response = $this->getJson('/api/v1/public/comments');

        $response->assertOk();
        $this->assertEquals(2, count($response->json('data')));
    }

    public function test_can_create_comment(): void
    {
        $post = Post::factory()->create();

        $response = $this->postJson('/api/v1/comments', [
            'post_id' => $post->id,
            'content' => 'This is my comment content.',
        ], $this->authHeaders(42));

        $response->assertCreated()
            ->assertJsonFragment([
                'post_id' => $post->id,
                'author_id' => 42,
                'content' => 'This is my comment content.',
                'status' => 'pending',
            ]);

        $this->assertDatabaseHas('comments', [
            'post_id' => $post->id,
            'author_id' => 42,
            'content' => 'This is my comment content.',
            'status' => 'pending',
        ]);
    }

    #[DataProvider('invalidCommentDataProvider')]
    public function test_create_comment_validation_fails(array $data, array $expectedErrors): void
    {
        $response = $this->postJson('/api/v1/comments', $data, $this->authHeaders());

        $response->assertUnprocessable()
            ->assertJsonValidationErrors($expectedErrors);
    }

    public static function invalidCommentDataProvider(): array
    {
        return [
            'empty payload' => [
                [],
                ['post_id', 'content'],
            ],
            'missing post_id' => [
                ['content' => 'Valid content here'],
                ['post_id'],
            ],
        ];
    }

    public function test_create_comment_validation_fails_for_missing_content(): void
    {
        $post = Post::factory()->create();

        $response = $this->postJson('/api/v1/comments', [
            'post_id' => $post->id,
        ], $this->authHeaders());

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['content']);
    }

    public function test_create_comment_validation_fails_for_content_too_short(): void
    {
        $post = Post::factory()->create();

        $response = $this->postJson('/api/v1/comments', [
            'post_id' => $post->id,
            'content' => 'ab',
        ], $this->authHeaders());

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['content']);
    }

    public function test_create_comment_validation_fails_for_nonexistent_post(): void
    {
        $response = $this->postJson('/api/v1/comments', [
            'post_id' => 99999,
            'content' => 'Valid content here',
        ], $this->authHeaders());

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['post_id']);
    }

    public function test_can_show_single_comment(): void
    {
        $post = Post::factory()->create();
        $comment = Comment::factory()->create(['post_id' => $post->id]);

        $response = $this->getJson("/api/v1/comments/{$comment->id}");

        $response->assertOk()
            ->assertJsonFragment([
                'id' => $comment->id,
                'content' => $comment->content,
                'status' => $comment->status,
            ])
            ->assertJsonStructure([
                'data' => [
                    'post' => ['id', 'title', 'slug'],
                ],
            ]);
    }

    public function test_can_update_comment(): void
    {
        $post = Post::factory()->create();
        $comment = Comment::factory()->create(['post_id' => $post->id]);

        $response = $this->putJson("/api/v1/comments/{$comment->id}", [
            'content' => 'Updated comment content',
        ], $this->authHeaders());

        $response->assertOk()
            ->assertJsonFragment([
                'content' => 'Updated comment content',
            ]);

        $this->assertDatabaseHas('comments', [
            'id' => $comment->id,
            'content' => 'Updated comment content',
        ]);
    }

    public function test_can_delete_comment(): void
    {
        $post = Post::factory()->create();
        $comment = Comment::factory()->create(['post_id' => $post->id]);

        $response = $this->deleteJson("/api/v1/comments/{$comment->id}", [], $this->authHeaders());

        $response->assertOk()
            ->assertJson([
                'message' => 'Comment deleted successfully'
            ]);

        $this->assertDatabaseMissing('comments', [
            'id' => $comment->id,
        ]);
    }

    public function test_can_approve_comment(): void
    {
        $post = Post::factory()->create();
        $comment = Comment::factory()->pending()->create(['post_id' => $post->id]);

        $response = $this->patchJson("/api/v1/comments/{$comment->id}/approve", [], $this->authHeaders());

        $response->assertOk()
            ->assertJsonFragment([
                'status' => 'approved',
            ]);

        $this->assertDatabaseHas('comments', [
            'id' => $comment->id,
            'status' => 'approved',
        ]);
    }

    public function test_can_reject_comment(): void
    {
        $post = Post::factory()->create();
        $comment = Comment::factory()->pending()->create(['post_id' => $post->id]);

        $response = $this->patchJson("/api/v1/comments/{$comment->id}/reject", [], $this->authHeaders());

        $response->assertOk()
            ->assertJsonFragment([
                'status' => 'rejected',
            ]);

        $this->assertDatabaseHas('comments', [
            'id' => $comment->id,
            'status' => 'rejected',
        ]);
    }

    public function test_comments_are_paginated(): void
    {
        $post = Post::factory()->create();
        Comment::factory()->count(20)->create(['post_id' => $post->id]);

        $response = $this->getJson('/api/v1/comments?per_page=5');

        $response->assertOk();
        $this->assertEquals(5, count($response->json('data')));
        $this->assertEquals(20, $response->json('meta.total'));
    }

    public function test_cannot_create_comment_without_token(): void
    {
        $post = Post::factory()->create();

        $response = $this->postJson('/api/v1/comments', [
            'post_id' => $post->id,
            'content' => 'Content',
        ]);

        $response->assertUnauthorized();
    }

    public function test_cannot_update_comment_without_token(): void
    {
        $post = Post::factory()->create();
        $comment = Comment::factory()->create(['post_id' => $post->id]);

        $response = $this->putJson("/api/v1/comments/{$comment->id}", ['content' => 'Updated']);

        $response->assertUnauthorized();
    }

    public function test_cannot_delete_comment_without_token(): void
    {
        $post = Post::factory()->create();
        $comment = Comment::factory()->create(['post_id' => $post->id]);

        $response = $this->deleteJson("/api/v1/comments/{$comment->id}");

        $response->assertUnauthorized();
    }

    public function test_create_comment_validation_fails_for_content_too_long(): void
    {
        $post = Post::factory()->create();

        $response = $this->postJson('/api/v1/comments', [
            'post_id' => $post->id,
            'content' => str_repeat('a', 5001),
        ], $this->authHeaders());

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['content']);
    }

    public function test_list_comments_includes_author_name(): void
    {
        $post   = Post::factory()->create();
        $author = \App\Models\Author::factory()->create(['name' => 'Jane Doe']);
        Comment::factory()->approved()->create([
            'post_id'   => $post->id,
            'author_id' => $author->user_id,
        ]);

        $response = $this->getJson('/api/v1/comments');

        $response->assertOk()
            ->assertJsonPath('data.0.author.name', 'Jane Doe');
    }

    public function test_show_comment_includes_author_name(): void
    {
        $post    = Post::factory()->create();
        $author  = \App\Models\Author::factory()->create(['name' => 'John Smith']);
        $comment = Comment::factory()->create([
            'post_id'   => $post->id,
            'author_id' => $author->user_id,
        ]);

        $response = $this->getJson("/api/v1/comments/{$comment->id}");

        $response->assertOk()
            ->assertJsonPath('data.author.name', 'John Smith');
    }

    public function test_list_comments_author_is_null_when_no_author_record(): void
    {
        $post = Post::factory()->create();
        Comment::factory()->approved()->create([
            'post_id'   => $post->id,
            'author_id' => 99999,
        ]);

        $response = $this->getJson('/api/v1/comments');

        $response->assertOk()
            ->assertJsonPath('data.0.author', null);
    }
}
