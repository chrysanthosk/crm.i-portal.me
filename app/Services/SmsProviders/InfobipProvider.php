<?php

namespace App\Services\SmsProviders;

use App\Models\SmsSetting;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class InfobipProvider implements SmsProviderInterface
{
    private string $apiKey;
    private string $baseUrl;
    private ?string $from;

    public function __construct(SmsSetting $setting)
    {
        $this->apiKey = trim((string)($setting->api_key ?? ''));

        // In your UI you store Infobip "API Base URL" in api_secret (optional).
        // But Infobip usually requires a *specific* base URL for your account/region.
        $storedBaseUrl = trim((string)($setting->api_secret ?? ''));

        // Optional fallbacks (if you prefer env/config instead of DB):
        $envBaseUrl = trim((string)env('INFOBIP_BASE_URL', ''));
        $cfgBaseUrl = trim((string)config('services.infobip.base_url', ''));

        $this->baseUrl = $storedBaseUrl ?: ($envBaseUrl ?: $cfgBaseUrl);
        $this->from = $setting->sender_id ? trim((string)$setting->sender_id) : null;

        if ($this->apiKey === '') {
            throw new RuntimeException('infobip: missing API key.');
        }

        if ($this->baseUrl === '') {
            // Be explicit: most accounts need the account/region base URL.
            throw new RuntimeException(
                "infobip: missing API Base URL. " .
                "Set it in SMS Settings (API Secret field) or INFOBIP_BASE_URL. " .
                "Infobip provides both API key + API Base URL in the portal."
            );
        }

        $this->baseUrl = rtrim($this->baseUrl, '/');
    }

    public function name(): string
    {
        return 'infobip';
    }

    public function send(string $to, string $message): array
    {
        $to = trim($to);
        $message = trim($message);

        if ($to === '' || $message === '') {
            throw new RuntimeException('infobip: missing to/message.');
        }
        if (empty($this->from)) {
            throw new RuntimeException('infobip: missing sender_id (from).');
        }

        $payload = [
            'messages' => [
                [
                    'from' => $this->from,
                    'destinations' => [['to' => $to]],
                    'text' => $message,
                ],
            ],
        ];

        $resp = Http::timeout(25)
            ->retry(1, 250) // small retry for transient network hiccups
            ->withHeaders([
                // API key auth format: Authorization: App <apiKey>
                'Authorization' => 'App ' . $this->apiKey,
                'Accept' => 'application/json',
            ])
            ->asJson()
            ->post($this->baseUrl . '/sms/2/text/advanced', $payload);

        if (!$resp->successful()) {
            $msg = $this->extractInfobipError($resp->json(), $resp->body());
            throw new RuntimeException("infobip: HTTP {$resp->status()} :: {$msg}");
        }

        $json = $resp->json();
        if (!is_array($json)) {
            throw new RuntimeException('infobip: invalid JSON response.');
        }

        return $json;
    }

    /**
     * Try to pull a human-friendly error from Infobip JSON responses.
     */
    private function extractInfobipError($json, string $rawBody): string
    {
        if (is_array($json)) {
            // Common Infobip error shapes:
            // { "requestError": { "serviceException": { "text": "...", "messageId": "..." } } }
            // { "requestError": { "policyException": { "text": "...", "messageId": "..." } } }
            $reqErr = $json['requestError'] ?? null;
            if (is_array($reqErr)) {
                $svc = $reqErr['serviceException'] ?? null;
                if (is_array($svc)) {
                    $text = $svc['text'] ?? null;
                    $mid  = $svc['messageId'] ?? null;
                    if ($text) return $mid ? "{$text} (messageId={$mid})" : (string)$text;
                }

                $pol = $reqErr['policyException'] ?? null;
                if (is_array($pol)) {
                    $text = $pol['text'] ?? null;
                    $mid  = $pol['messageId'] ?? null;
                    if ($text) return $mid ? "{$text} (messageId={$mid})" : (string)$text;
                }
            }

            // Fallbacks
            if (!empty($json['message'])) return (string)$json['message'];
            if (!empty($json['error'])) return (string)$json['error'];
        }

        $rawBody = trim($rawBody);
        return $rawBody !== '' ? $rawBody : 'unknown error';
    }
}
