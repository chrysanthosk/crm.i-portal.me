<?php

namespace App\Services\SmsProviders;

use App\Models\SmsSetting;
use RuntimeException;

class SMSToProvider implements SmsProviderInterface
{
    private string $apiKey;
    private ?string $senderId;

    public function __construct(SmsSetting $setting)
    {
        $this->apiKey = trim((string)($setting->api_key ?? ''));
        $this->senderId = $setting->sender_id ? trim((string)$setting->sender_id) : null;

        if ($this->apiKey === '') {
            throw new RuntimeException("sms.to: missing API key.");
        }
    }

    public function name(): string
    {
        return 'sms.to';
    }

    public function send(string $to, string $body): array
    {
        $to = trim($to);
        $body = trim($body);

        if ($to === '' || $body === '') {
            throw new RuntimeException("sms.to: missing to/message.");
        }

        // NOTE: sms.to endpoint may differ depending on your account/product.
        // If your API returns 404/401, we’ll confirm the exact endpoint from their dashboard.
        $url = 'https://api.sms.to/sms/send';

        $payload = [
            'to' => $to,
            'message' => $body,
        ];

        if ($this->senderId) {
            $payload['sender_id'] = $this->senderId;
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json',
                'Authorization: Bearer ' . $this->apiKey,
            ],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_TIMEOUT => 30,
        ]);

        $resp = curl_exec($ch);
        if ($resp === false) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException("sms.to: HTTP error: {$err}");
        }

        $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        $data = json_decode($resp, true);
        if (!is_array($data)) $data = [];

        if ($code < 200 || $code >= 300) {
            $msg = $data['message'] ?? $data['error'] ?? $data['errors'][0]['message'] ?? ('HTTP '.$code);
            throw new RuntimeException("sms.to: API error ({$code}): {$msg}");
        }

        return $data;
    }
}
