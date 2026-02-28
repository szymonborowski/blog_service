<?php

namespace App\Services;

use App\Models\Author;

class UserEventsMessageHandler
{
    public function handle(string $body): void
    {
        $data = json_decode($body, true);

        if (!$data) {
            throw new \InvalidArgumentException('Invalid JSON message');
        }

        $action = $data['action'] ?? null;
        $userData = $data['user'] ?? null;

        if (!$action || !$userData) {
            throw new \InvalidArgumentException('Missing action or user data');
        }

        match ($action) {
            'created', 'updated' => $this->upsertAuthor($userData),
            default => null, // unknown action, no-op
        };
    }

    public function upsertAuthor(array $userData): void
    {
        Author::updateOrCreate(
            ['user_id' => $userData['id']],
            [
                'name' => $userData['name'],
                'email' => $userData['email'],
                'user_created_at' => isset($userData['created_at']) ? $userData['created_at'] : null,
            ]
        );
    }
}
