<?php

namespace Tests\Feature;

use App\Models\NewsletterSubscriber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NewsletterApiTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Subscribe
    // -------------------------------------------------------------------------

    public function test_can_subscribe_with_valid_email(): void
    {
        $response = $this->postJson('/api/v1/newsletter/subscribe', [
            'email' => 'user@example.com',
        ]);

        $response->assertCreated()
            ->assertJson(['message' => 'Subscribed successfully.']);

        $this->assertDatabaseHas('newsletter_subscribers', [
            'email' => 'user@example.com',
        ]);
    }

    public function test_subscribe_sets_confirmed_at(): void
    {
        $this->postJson('/api/v1/newsletter/subscribe', ['email' => 'user@example.com'])
            ->assertCreated();

        $subscriber = NewsletterSubscriber::where('email', 'user@example.com')->first();

        $this->assertNotNull($subscriber->confirmed_at);
        $this->assertNull($subscriber->unsubscribed_at);
    }

    public function test_subscribing_already_subscribed_email_returns_200(): void
    {
        NewsletterSubscriber::create([
            'email'        => 'user@example.com',
            'confirmed_at' => now(),
        ]);

        $response = $this->postJson('/api/v1/newsletter/subscribe', [
            'email' => 'user@example.com',
        ]);

        $response->assertOk()
            ->assertJson(['message' => 'Already subscribed.']);

        $this->assertEquals(1, NewsletterSubscriber::where('email', 'user@example.com')->count());
    }

    public function test_can_resubscribe_after_unsubscribing(): void
    {
        NewsletterSubscriber::create([
            'email'           => 'user@example.com',
            'confirmed_at'    => now()->subDays(30),
            'unsubscribed_at' => now()->subDays(10),
        ]);

        $response = $this->postJson('/api/v1/newsletter/subscribe', [
            'email' => 'user@example.com',
        ]);

        $response->assertCreated()
            ->assertJson(['message' => 'Subscribed successfully.']);

        $subscriber = NewsletterSubscriber::where('email', 'user@example.com')->first();

        $this->assertNull($subscriber->unsubscribed_at);
        $this->assertNotNull($subscriber->confirmed_at);
    }

    public function test_subscribe_validation_requires_email(): void
    {
        $this->postJson('/api/v1/newsletter/subscribe', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_subscribe_validation_rejects_invalid_email(): void
    {
        $this->postJson('/api/v1/newsletter/subscribe', ['email' => 'not-an-email'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_subscribe_validation_rejects_email_too_long(): void
    {
        // max:255 — local part 250 chars + '@x.com' = 256 chars total
        $this->postJson('/api/v1/newsletter/subscribe', [
            'email' => str_repeat('a', 250) . '@x.com',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    // -------------------------------------------------------------------------
    // Unsubscribe
    // -------------------------------------------------------------------------

    public function test_can_unsubscribe_active_subscriber(): void
    {
        NewsletterSubscriber::create([
            'email'        => 'user@example.com',
            'confirmed_at' => now(),
        ]);

        $response = $this->postJson('/api/v1/newsletter/unsubscribe', [
            'email' => 'user@example.com',
        ]);

        $response->assertOk()
            ->assertJson(['message' => 'Unsubscribed successfully.']);

        $subscriber = NewsletterSubscriber::where('email', 'user@example.com')->first();
        $this->assertNotNull($subscriber->unsubscribed_at);
    }

    public function test_unsubscribe_nonexistent_email_returns_200(): void
    {
        // Endpoint is idempotent — should not reveal whether email exists
        $this->postJson('/api/v1/newsletter/unsubscribe', [
            'email' => 'ghost@example.com',
        ])->assertOk()
            ->assertJson(['message' => 'Unsubscribed successfully.']);
    }

    public function test_unsubscribe_validation_requires_email(): void
    {
        $this->postJson('/api/v1/newsletter/unsubscribe', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_unsubscribe_validation_rejects_invalid_email(): void
    {
        $this->postJson('/api/v1/newsletter/unsubscribe', ['email' => 'bad-email'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    // -------------------------------------------------------------------------
    // Scope
    // -------------------------------------------------------------------------

    public function test_active_scope_excludes_unsubscribed(): void
    {
        NewsletterSubscriber::create(['email' => 'active@example.com', 'confirmed_at' => now()]);
        NewsletterSubscriber::create(['email' => 'gone@example.com', 'confirmed_at' => now(), 'unsubscribed_at' => now()]);

        $this->assertEquals(1, NewsletterSubscriber::active()->count());
    }

    public function test_active_scope_excludes_unconfirmed(): void
    {
        NewsletterSubscriber::create(['email' => 'pending@example.com', 'confirmed_at' => null]);

        $this->assertEquals(0, NewsletterSubscriber::active()->count());
    }
}
