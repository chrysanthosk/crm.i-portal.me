<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/profile');

        $response->assertOk();
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = User::factory()->create([
            'first_name' => 'Old',
            'last_name' => 'Name',
        ]);

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'first_name' => 'Test',
                'last_name' => 'User',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $user->refresh();

        $this->assertSame('Test', $user->first_name);
        $this->assertSame('User', $user->last_name);
        $this->assertSame('Test User', $user->name);
    }

    public function test_password_can_be_updated_from_profile_password_route(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->patch('/profile/password', [
                'current_password' => 'password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect();
    }

    public function test_email_change_confirmation_can_be_completed_without_being_logged_in(): void
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

    public function test_user_can_delete_their_account(): void
    {
        $user = User::factory()->create();

        $this->markTestIncomplete('Account deletion route is not implemented in the current application.');
    }

    public function test_correct_password_must_be_provided_to_delete_account(): void
    {
        $user = User::factory()->create();

        $this->markTestIncomplete('Account deletion route is not implemented in the current application.');
    }
}
