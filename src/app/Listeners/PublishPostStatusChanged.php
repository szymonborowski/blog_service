<?php

namespace App\Listeners;

use App\Events\PostStatusChanged;
use App\Services\RabbitMQService;

class PublishPostStatusChanged
{
    public function __construct(private RabbitMQService $rabbitMQ) {}

    public function handle(PostStatusChanged $event): void
    {
        $post = $event->post;

        $this->rabbitMQ->publish(
            config('rabbitmq.exchanges.blog'),
            "post.{$event->newStatus}",
            [
                'action' => $event->newStatus,
                'post'   => [
                    'id'   => $post->id,
                    'slug' => $post->slug,
                ],
                'timestamp' => now()->toISOString(),
            ]
        );
    }
}
