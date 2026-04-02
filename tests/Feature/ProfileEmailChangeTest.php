<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ProfileEmailChangeTest extends TestCase
{
    use RefreshDatabase;

    public function test_email_change_confirmation_updates_email_and_clears_pending_fields(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'email' => 'old@example.com',
            'pending_email' => 'new@example.com',
            'pending_email_requested_at' => now(),
        ]);

        $token = bin2hex(random_bytes(32));
        $user->pending_email_token = hash('sha256', $token);
        $user->save();

        $response = $this->get(route('profile.email.confirm', ['token' => $token]));

        $response->assertRedirect(route('login'));
        $this->assertSame('new@example.com', $user->fresh()->email);
        $this->assertNull($user->fresh()->pending_email);
        $this->assertNull($user->fresh()->pending_email_token);
    }

    public function test_email_change_confirmation_fails_for_invalid_token(): void
    {
        $response = $this->get(route('profile.email.confirm', ['token' => 'invalid-token']));

        $response->assertRedirect(route('login'));
    }

    public function test_email_change_confirmation_expires_after_24_hours(): void
    {
        $user = User::factory()->create([
            'email' => 'old@example.com',
            'pending_email' => 'new@example.com',
            'pending_email_requested_at' => now()->subHours(25),
        ]);

        $token = bin2hex(random_bytes(32));
        $user->pending_email_token = hash('sha256', $token);
        $user->save();

        $response = $this->get(route('profile.email.confirm', ['token' => $token]));

        $response->assertRedirect(route('login'));
        $this->assertNull($user->fresh()->pending_email);
        $this->assertNull($user->fresh()->pending_email_token);
    }

    public function test_email_change_confirmation_rejects_email_already_used_by_another_user(): void
    {
        User::factory()->create(['email' => 'new@example.com']);

        $user = User::factory()->create([
            'email' => 'old@example.com',
            'pending_email' => 'new@example.com',
            'pending_email_requested_at' => now(),
        ]);

        $token = bin2hex(random_bytes(32));
        $user->pending_email_token = hash('sha256', $token);
        $user->save();

        $response = $this->get(route('profile.email.confirm', ['token' => $token]));

        $response->assertRedirect(route('login'));
        $this->assertSame('old@example.com', $user->fresh()->email);
        $this->assertSame('new@example.com', $user->fresh()->pending_email);
    }
}
