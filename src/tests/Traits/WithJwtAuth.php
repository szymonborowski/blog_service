<?php

namespace Tests\Traits;

use App\Models\User;
use Firebase\JWT\JWT;

trait WithJwtAuth
{
    protected function setUpJwtAuth(): void
    {
        config(['auth.jwt_public_key' => base_path('tests/keys/test-public.key')]);
    }

    protected function createTestToken(int $userId = 1, ?string $name = 'Test User', ?string $email = 'test@example.com', array $overrides = []): string
    {
        $privateKey = file_get_contents(base_path('tests/keys/test-private.key'));

        return JWT::encode(array_merge([
            'iss' => 'test-sso',
            'sub' => $userId,
            'name' => $name,
            'email' => $email,
            'iat' => time(),
            'exp' => time() + 3600,
        ], $overrides), $privateKey, 'RS256');
    }

    protected function authHeaders(int $userId = 1): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->createTestToken($userId),
        ];
    }
}
