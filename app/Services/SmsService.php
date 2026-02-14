<?php

namespace App\Services;

use App\Models\SmsFailure;
use App\Models\SmsProvider;
use App\Models\SmsSetting;
use App\Models\SmsSuccess;
use App\Services\SmsProviders\InfobipProvider;
use App\Services\SmsProviders\SMSToProvider;
use App\Services\SmsProviders\SmsProviderInterface;
use App\Services\SmsProviders\TwilioProvider;
use RuntimeException;
use Throwable;

class SmsService
{
    /**
     * Normal sending:
     * - Providers ordered by priority ASC (0 first).
     * - Only providers that are is_active=1 AND have sms_settings.is_enabled=1 are considered.
     */
    public function send(string $to, string $message): array
    {
        $to = trim($to);
        $message = trim($message);

        if ($to === '' || $message === '') {
            throw new RuntimeException("SMS: missing to/message.");
        }

        $providers = SmsProvider::query()
            ->where('is_active', true)
            ->orderBy('priority')
            ->orderBy('id')
            ->get();

        if ($providers->isEmpty()) {
            throw new RuntimeException("SMS: no active providers configured.");
        }

        $lastError = null;

        foreach ($providers as $provider) {
            $setting = SmsSetting::query()
                ->where('provider_id', $provider->id)
                ->where('is_enabled', true)
                ->first();

            if (!$setting) {
                continue;
            }

            try {
                $driver = $this->makeDriver($provider->name, $setting);
                $resp = $driver->send($to, $message);

                SmsSuccess::query()->create([
                    'mobile' => $to,
                    'provider' => $provider->name,
                    'success_code' => $this->extractSuccessCode($provider->name, $resp),
                    'sent_at' => now(),
                ]);

                return [
                    'provider' => $provider->name,
                    'response' => $resp,
                ];
            } catch (Throwable $e) {
                $lastError = $e;

                SmsFailure::query()->create([
                    'mobile' => $to,
                    'provider' => $provider->name,
                    'error_message' => $e->getMessage() !== '' ? $e->getMessage() : get_class($e),
                    'failed_at' => now(),
                ]);

                continue;
            }
        }

        $msg = $lastError?->getMessage();
        if (!$msg) $msg = $lastError ? get_class($lastError) : 'unknown';

        throw new RuntimeException("SMS: all providers failed. Last error: " . $msg);
    }

    /**
     * Test sending:
     * - If $providerId is provided: force that provider (ignores priority / is_active / is_enabled).
     * - Else: behaves like send().
     */
    public function sendTest(string $to, string $message, ?int $providerId = null): array
    {
        if ($providerId === null) {
            return $this->send($to, $message);
        }

        $provider = SmsProvider::query()->findOrFail($providerId);

        $setting = SmsSetting::query()
            ->where('provider_id', $provider->id)
            ->first();

        if (!$setting) {
            throw new RuntimeException("No SMS settings found for provider '{$provider->name}'. Please save credentials first.");
        }

        try {
            $driver = $this->makeDriver($provider->name, $setting);
            $resp = $driver->send($to, $message);

            SmsSuccess::query()->create([
                'mobile' => $to,
                'provider' => $provider->name,
                'success_code' => $this->extractSuccessCode($provider->name, $resp),
                'sent_at' => now(),
            ]);

            return [
                'provider' => $provider->name,
                'response' => $resp,
            ];
        } catch (Throwable $e) {
            SmsFailure::query()->create([
                'mobile' => $to,
                'provider' => $provider->name,
                'error_message' => $e->getMessage() !== '' ? $e->getMessage() : get_class($e),
                'failed_at' => now(),
            ]);

            throw $e;
        }
    }

    private function makeDriver(string $providerName, SmsSetting $setting): SmsProviderInterface
    {
        $providerName = strtolower(trim($providerName));

        return match ($providerName) {
            'sms.to'  => new SMSToProvider($setting),
            'twilio'  => new TwilioProvider($setting),
            'infobip' => new InfobipProvider($setting),
            default   => throw new RuntimeException("SMS provider '{$providerName}' is not implemented."),
        };
    }

    private function extractSuccessCode(string $providerName, array $resp): ?string
    {
        $providerName = strtolower(trim($providerName));

        return match ($providerName) {
            'twilio'  => isset($resp['sid']) ? (string)$resp['sid'] : null,
            'infobip' => isset($resp['messages'][0]['messageId']) ? (string)$resp['messages'][0]['messageId'] : null,
            'sms.to'  => isset($resp['message_id']) ? (string)$resp['message_id'] : (isset($resp['id']) ? (string)$resp['id'] : null),
            default   => null,
        };
    }
}
