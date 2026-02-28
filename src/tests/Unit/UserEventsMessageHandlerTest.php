<?php

namespace Tests\Unit;

use App\Models\Author;
use App\Services\UserEventsMessageHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UserEventsMessageHandlerTest extends TestCase
{
    use RefreshDatabase;

    private UserEventsMessageHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->handler = new UserEventsMessageHandler();
    }

    #[Test]
    public function it_throws_on_invalid_json(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid JSON message');

        $this->handler->handle('not json');
    }

    #[Test]
    public function it_throws_when_action_missing(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing action or user data');

        $this->handler->handle(json_encode(['user' => ['id' => 1, 'name' => 'Test', 'email' => 'a@b.com']]));
    }

    #[Test]
    public function it_throws_when_user_data_missing(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing action or user data');

        $this->handler->handle(json_encode(['action' => 'created']));
    }

    #[Test]
    public function it_creates_author_on_created_action(): void
    {
        $body = json_encode([
            'action' => 'created',
            'user' => [
                'id' => 100,
                'name' => 'Jane Doe',
                'email' => 'jane@example.com',
                'created_at' => '2026-01-15T10:00:00.000000Z',
            ],
        ]);

        $this->handler->handle($body);

        $this->assertDatabaseHas('authors', [
            'user_id' => 100,
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
        ]);
    }

    #[Test]
    public function it_updates_author_on_updated_action(): void
    {
        Author::create([
            'user_id' => 200,
            'name' => 'Old Name',
            'email' => 'old@example.com',
        ]);

        $body = json_encode([
            'action' => 'updated',
            'user' => [
                'id' => 200,
                'name' => 'New Name',
                'email' => 'new@example.com',
                'created_at' => '2026-01-10T00:00:00.000000Z',
            ],
        ]);

        $this->handler->handle($body);

        $author = Author::where('user_id', 200)->first();
        $this->assertSame('New Name', $author->name);
        $this->assertSame('new@example.com', $author->email);
    }

    #[Test]
    public function it_does_nothing_for_unknown_action(): void
    {
        $body = json_encode([
            'action' => 'deleted',
            'user' => ['id' => 1, 'name' => 'X', 'email' => 'x@x.com'],
        ]);

        $this->handler->handle($body);

        $this->assertDatabaseCount('authors', 0);
    }
}
