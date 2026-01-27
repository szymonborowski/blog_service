<?php

namespace App\Auth;

use App\Models\User;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Auth\GuardHelpers;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Http\Request;

class JwtGuard implements Guard
{
    use GuardHelpers;

    protected Request $request;
    protected string $publicKey;

    public function __construct(Request $request, ?string $publicKeyPath = null)
    {
        $this->request = $request;
        $keyPath = $publicKeyPath ?? config('auth.jwt_public_key', storage_path('oauth-public.key'));
        $this->publicKey = file_get_contents($keyPath);
    }

    public function user()
    {
        if ($this->user !== null) {
            return $this->user;
        }

        $token = $this->getTokenFromRequest();

        if (!$token) {
            return null;
        }

        try {
            $decoded = JWT::decode($token, new Key($this->publicKey, 'RS256'));

            $this->user = new User([
                'id' => $decoded->sub,
                'name' => $decoded->name ?? null,
                'email' => $decoded->email ?? null,
            ]);
            $this->user->id = $decoded->sub;

            return $this->user;
        } catch (\Exception $e) {
            return null;
        }
    }

    public function validate(array $credentials = []): bool
    {
        $token = $credentials['token'] ?? null;

        if (!$token) {
            return false;
        }

        try {
            JWT::decode($token, new Key($this->publicKey, 'RS256'));
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    protected function getTokenFromRequest(): ?string
    {
        $header = $this->request->header('Authorization', '');

        if (str_starts_with($header, 'Bearer ')) {
            return substr($header, 7);
        }

        return null;
    }
}
