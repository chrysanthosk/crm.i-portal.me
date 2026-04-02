<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TwoFactorFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_2fa_enable_generates_pending_secret_in_session(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('profile.2fa.enable'));

        $response->assertRedirect(route('profile.2fa.show'));
        $response->assertSessionHas('2fa_secret_pending');
        $this->assertFalse($user->fresh()->hasTwoFactorEnabled());
    }

    public function test_2fa_confirm_requires_pending_secret_first(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->from(route('profile.2fa.show'))
            ->post(route('profile.2fa.confirm'), [
                'code' => '123456',
            ]);

        $response->assertRedirect(route('profile.2fa.show'));
        $response->assertSessionHasErrors('code');
        $this->assertFalse($user->fresh()->hasTwoFactorEnabled());
    }

    public function test_2fa_disable_requires_correct_current_password(): void
    {
        $user = User::factory()->create([
            'two_factor_enabled' => true,
            'two_factor_confirmed_at' => now(),
        ]);
        $user->setTwoFactorSecretPlain('ABCDEFGHIJKLMNOPQRST');
        $user->two_factor_recovery_codes = ['ABCDE-12345'];
        $user->save();

        $response = $this->actingAs($user)
            ->from(route('profile.2fa.show'))
            ->post(route('profile.2fa.disable'), [
                'current_password' => 'wrong-password',
            ]);

        $response->assertRedirect(route('profile.2fa.show'));
        $response->assertSessionHasErrors('current_password');
        $this->assertTrue($user->fresh()->hasTwoFactorEnabled());
    }

    public function test_recovery_codes_regeneration_requires_2fa_enabled(): void
    {
        $user = User::factory()->create([
            'two_factor_enabled' => false,
            'two_factor_confirmed_at' => null,
        ]);

        $response = $this->actingAs($user)
            ->from(route('profile.2fa.show'))
            ->post(route('profile.2fa.recovery.regenerate'));

        $response->assertRedirect(route('profile.2fa.show'));
        $response->assertSessionHasErrors('code');
    }
}
