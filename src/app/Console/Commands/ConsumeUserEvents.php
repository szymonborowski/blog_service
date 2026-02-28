<?php

namespace App\Console\Commands;

use App\Services\UserEventsMessageHandler;
use Illuminate\Console\Command;
use PhpAmqpLib\Connection\AMQPStreamConnection;

class ConsumeUserEvents extends Command
{
    protected $signature = 'rabbitmq:consume-users';

    protected $description = 'Consume user events from RabbitMQ queue';

    private ?AMQPStreamConnection $connection = null;

    public function __construct(
        private readonly UserEventsMessageHandler $messageHandler
    ) {
        parent::__construct();
    }

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
            $this->messageHandler->handle($message->body);

            $data = json_decode($message->body, true);
            $action = $data['action'] ?? 'unknown';
            $userData = $data['user'] ?? [];
            $this->info("Processing {$action} event for user: " . ($userData['email'] ?? 'unknown'));
            $message->ack();
            $this->info("Successfully processed {$action} event");
        } catch (\Exception $e) {
            $this->error("Error processing message: {$e->getMessage()}");
            $message->nack();
        }
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
