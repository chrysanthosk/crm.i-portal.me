<?php

namespace Tests\Feature;

use App\Models\SmsProvider;
use App\Models\SmsSetting;
use App\Models\SmtpSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SmtpSmsSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function adminUser(): User
    {
        return User::factory()->create([
            'role' => 'admin',
        ]);
    }

    public function test_admin_can_save_smtp_settings_and_password_is_encrypted(): void
    {
        $admin = $this->adminUser();

        $response = $this->actingAs($admin)->put(route('settings.smtp.update'), [
            'enabled' => '1',
            'host' => 'smtp.example.com',
            'port' => '587',
            'encryption' => 'tls',
            'username' => 'mailer@example.com',
            'password' => 'fixture-mail-password',
            'from_address' => 'noreply@example.com',
            'from_name' => 'CRM Mailer',
        ]);

        $response->assertRedirect(route('settings.smtp.edit'));
        $response->assertSessionHas('status');

        $smtp = SmtpSetting::query()->first();
        $this->assertNotNull($smtp);
        $this->assertSame('smtp.example.com', $smtp->host);
        $this->assertSame(587, $smtp->port);
        $this->assertTrue($smtp->enabled);
        $this->assertNotSame('fixture-mail-password', $smtp->password_enc);
        $this->assertSame('fixture-mail-password', Crypt::decryptString($smtp->password_enc));
    }

    public function test_smtp_test_fails_safely_when_smtp_is_not_enabled(): void
    {
        $admin = $this->adminUser();

        SmtpSetting::query()->create([
            'enabled' => false,
            'host' => 'smtp.example.com',
            'port' => 587,
        ]);

        $response = $this->actingAs($admin)
            ->from(route('settings.smtp.edit'))
            ->post(route('settings.smtp.test'), [
                'test_email' => 'test@example.com',
            ]);

        $response->assertRedirect(route('settings.smtp.edit'));
        $response->assertSessionHasErrors('smtp_test');
    }

    public function test_admin_can_save_sms_provider_settings(): void
    {
        $admin = $this->adminUser();
        $provider = SmsProvider::query()->create([
            'name' => 'twilio',
            'doc_url' => 'https://www.twilio.com/docs/sms',
            'is_active' => 1,
            'priority' => 0,
        ]);

        $response = $this->actingAs($admin)->post(route('settings.sms.settings.save'), [
            'provider_id' => $provider->id,
            'api_key' => 'fixture-key-123',
            'api_secret' => 'fixture-secret-value',
            'sender_id' => 'CRM',
            'is_enabled' => '1',
        ]);

        $response->assertRedirect(route('settings.sms.edit'));
        $response->assertSessionHas('status');

        $setting = SmsSetting::query()->where('provider_id', $provider->id)->first();
        $this->assertNotNull($setting);
        $this->assertSame('fixture-key-123', $setting->api_key);
        $this->assertSame('fixture-secret-value', $setting->api_secret);
        $this->assertSame('CRM', $setting->sender_id);
        $this->assertTrue($setting->is_enabled);
    }

    public function test_admin_can_toggle_sms_provider_active_state(): void
    {
        $admin = $this->adminUser();
        $provider = SmsProvider::query()->create([
            'name' => 'infobip',
            'doc_url' => 'https://www.infobip.com/docs/sms',
            'is_active' => 1,
            'priority' => 1,
        ]);

        $response = $this->actingAs($admin)
            ->post(route('settings.sms.providers.toggle', $provider), [
                'is_active' => 0,
            ]);

        $response->assertOk();
        $this->assertFalse($provider->fresh()->is_active);
    }

    public function test_admin_can_update_sms_provider_priority_order(): void
    {
        $admin = $this->adminUser();
        $p1 = SmsProvider::query()->create([
            'name' => 'sms.to',
            'doc_url' => 'https://github.com/intergo/sms.to-php',
            'is_active' => 1,
            'priority' => 0,
        ]);
        $p2 = SmsProvider::query()->create([
            'name' => 'twilio',
            'doc_url' => 'https://www.twilio.com/docs/sms',
            'is_active' => 1,
            'priority' => 1,
        ]);

        $response = $this->actingAs($admin)
            ->post(route('settings.sms.providers.priority'), [
                'order' => [$p2->id, $p1->id],
            ]);

        $response->assertOk();
        $this->assertSame(0, $p2->fresh()->priority);
        $this->assertSame(1, $p1->fresh()->priority);
    }
}
