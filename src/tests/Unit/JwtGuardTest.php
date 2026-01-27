<?php

namespace Tests\Unit;

use App\Auth\JwtGuard;
use App\Models\User;
use Firebase\JWT\JWT;
use Illuminate\Http\Request;
use Tests\TestCase;

class JwtGuardTest extends TestCase
{
    protected string $privateKey;
    protected string $publicKeyPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->publicKeyPath = base_path('tests/keys/test-public.key');
        $this->privateKey = file_get_contents(base_path('tests/keys/test-private.key'));
    }

    protected function createToken(array $payload = []): string
    {
        $defaultPayload = [
            'iss' => 'test-sso',
            'sub' => 1,
            'name' => 'Test User',
            'email' => 'test@example.com',
            'iat' => time(),
            'exp' => time() + 3600,
        ];

        return JWT::encode(array_merge($defaultPayload, $payload), $this->privateKey, 'RS256');
    }

    protected function createGuard(Request $request): JwtGuard
    {
        return new JwtGuard($request, $this->publicKeyPath);
    }

    public function test_returns_null_when_no_token_provided(): void
    {
        $request = Request::create('/api/v1/posts', 'GET');
        $guard = $this->createGuard($request);

        $this->assertNull($guard->user());
    }

    public function test_returns_null_when_invalid_authorization_header(): void
    {
        $request = Request::create('/api/v1/posts', 'GET');
        $request->headers->set('Authorization', 'InvalidHeader');
        $guard = $this->createGuard($request);

        $this->assertNull($guard->user());
    }

    public function test_returns_user_when_valid_token_provided(): void
    {
        $token = $this->createToken(['sub' => 42, 'name' => 'John Doe', 'email' => 'john@example.com']);

        $request = Request::create('/api/v1/posts', 'GET');
        $request->headers->set('Authorization', 'Bearer ' . $token);
        $guard = $this->createGuard($request);

        $user = $guard->user();

        $this->assertInstanceOf(User::class, $user);
        $this->assertEquals(42, $user->id);
        $this->assertEquals('John Doe', $user->name);
        $this->assertEquals('john@example.com', $user->email);
    }

    public function test_returns_null_when_token_expired(): void
    {
        $token = $this->createToken(['exp' => time() - 3600]);

        $request = Request::create('/api/v1/posts', 'GET');
        $request->headers->set('Authorization', 'Bearer ' . $token);
        $guard = $this->createGuard($request);

        $this->assertNull($guard->user());
    }

    public function test_returns_null_when_token_has_invalid_signature(): void
    {
        $otherPrivateKey = openssl_pkey_new(['private_key_bits' => 2048]);
        openssl_pkey_export($otherPrivateKey, $otherPrivateKeyPem);

        $token = JWT::encode([
            'sub' => 1,
            'iat' => time(),
            'exp' => time() + 3600,
        ], $otherPrivateKeyPem, 'RS256');

        $request = Request::create('/api/v1/posts', 'GET');
        $request->headers->set('Authorization', 'Bearer ' . $token);
        $guard = $this->createGuard($request);

        $this->assertNull($guard->user());
    }

    public function test_caches_user_on_subsequent_calls(): void
    {
        $token = $this->createToken();

        $request = Request::create('/api/v1/posts', 'GET');
        $request->headers->set('Authorization', 'Bearer ' . $token);
        $guard = $this->createGuard($request);

        $user1 = $guard->user();
        $user2 = $guard->user();

        $this->assertSame($user1, $user2);
    }

    public function test_validate_returns_true_for_valid_token(): void
    {
        $token = $this->createToken();

        $request = Request::create('/api/v1/posts', 'GET');
        $guard = $this->createGuard($request);

        $this->assertTrue($guard->validate(['token' => $token]));
    }

    public function test_validate_returns_false_for_invalid_token(): void
    {
        $request = Request::create('/api/v1/posts', 'GET');
        $guard = $this->createGuard($request);

        $this->assertFalse($guard->validate(['token' => 'invalid.token.here']));
    }

    public function test_validate_returns_false_when_no_token(): void
    {
        $request = Request::create('/api/v1/posts', 'GET');
        $guard = $this->createGuard($request);

        $this->assertFalse($guard->validate([]));
    }

    public function test_handles_token_without_optional_claims(): void
    {
        $token = $this->createToken(['name' => null, 'email' => null]);

        $request = Request::create('/api/v1/posts', 'GET');
        $request->headers->set('Authorization', 'Bearer ' . $token);
        $guard = $this->createGuard($request);

        $user = $guard->user();

        $this->assertInstanceOf(User::class, $user);
        $this->assertNull($user->name);
        $this->assertNull($user->email);
    }
}
