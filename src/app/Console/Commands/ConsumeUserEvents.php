<?php

namespace App\Console\Commands;

use App\Models\Author;
use Illuminate\Console\Command;
use PhpAmqpLib\Connection\AMQPStreamConnection;

class ConsumeUserEvents extends Command
{
    protected $signature = 'rabbitmq:consume-users';

    protected $description = 'Consume user events from RabbitMQ queue';

    private ?AMQPStreamConnection $connection = null;

    public function handle(): int
    {
        $this->info('Starting user events consumer...');

        $connection = $this->getConnection();
        $channel = $connection->channel();

        $exchange = config('rabbitmq.exchanges.users');
        $queue = config('rabbitmq.queues.blog_users');

        $channel->exchange_declare($exchange, 'topic', false, true, false);
        $channel->queue_declare($queue, false, true, false, false);
        $channel->queue_bind($queue, $exchange, 'user.*');

        $this->info("Waiting for messages on queue: {$queue}");

        $channel->basic_consume(
            $queue,
            '',
            false,
            false,
            false,
            false,
            function ($message) {
                $this->processMessage($message);
            }
        );

        while ($channel->is_consuming()) {
            $channel->wait();
        }

        return Command::SUCCESS;
    }

    private function processMessage($message): void
    {
        try {
            $data = json_decode($message->body, true);

            if (!$data) {
                $this->error('Invalid JSON message');
                $message->nack();
                return;
            }

            $action = $data['action'] ?? null;
            $userData = $data['user'] ?? null;

            if (!$action || !$userData) {
                $this->error('Missing action or user data');
                $message->nack();
                return;
            }

            $this->info("Processing {$action} event for user: {$userData['email']}");

            match ($action) {
                'created', 'updated' => $this->upsertAuthor($userData),
                default => $this->warn("Unknown action: {$action}"),
            };

            $message->ack();
            $this->info("Successfully processed {$action} event");
        } catch (\Exception $e) {
            $this->error("Error processing message: {$e->getMessage()}");
            $message->nack();
        }
    }

    private function upsertAuthor(array $userData): void
    {
        Author::updateOrCreate(
            ['user_id' => $userData['id']],
            [
                'name' => $userData['name'],
                'email' => $userData['email'],
                'user_created_at' => $userData['created_at'] ?? null,
            ]
        );
    }

    private function getConnection(): AMQPStreamConnection
    {
        if ($this->connection === null || !$this->connection->isConnected()) {
            $this->connection = new AMQPStreamConnection(
                config('rabbitmq.host'),
                config('rabbitmq.port'),
                config('rabbitmq.user'),
                config('rabbitmq.password'),
                config('rabbitmq.vhost'),
            );
        }

        return $this->connection;
    }

    public function __destruct()
    {
        if ($this->connection !== null && $this->connection->isConnected()) {
            $this->connection->close();
        }
    }
}
