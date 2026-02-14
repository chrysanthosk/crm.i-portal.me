<?php

namespace App\Services\SmsProviders;

use App\Models\SmsSetting;
use RuntimeException;

class TwilioProvider implements SmsProviderInterface
{
    private string $accountSid;
    private string $authToken;
    private string $from;
    private string $baseUrl;

    public function __construct(SmsSetting $setting)
    {
        $this->accountSid = trim((string)($setting->api_key ?? ''));
        $this->authToken  = trim((string)($setting->api_secret ?? ''));
        $this->from       = trim((string)($setting->sender_id ?? ''));

        if ($this->accountSid === '' || $this->authToken === '') {
            throw new RuntimeException("twilio: missing Account SID / Auth Token.");
        }
        if ($this->from === '') {
            throw new RuntimeException("twilio: missing Sender ID (From). Use a Twilio phone number (+E.164) or a Messaging Service SID (MG...).");
        }

        // Optional:
        // If you ever want to support Twilio edge/region, you could store a custom base URL in api_secret or add a db field.
        // For now, keep it standard.
        $this->baseUrl = 'https://api.twilio.com';
    }

    public function name(): string
    {
        return 'twilio';
    }

    public function send(string $to, string $body): array
    {
        $to = trim($to);
        $body = trim($body);

        if ($to === '' || $body === '') {
            throw new RuntimeException("twilio: missing to/message.");
        }

        // Twilio endpoint
        $url = rtrim($this->baseUrl, '/') . "/2010-04-01/Accounts/{$this->accountSid}/Messages.json";

        // Twilio expects application/x-www-form-urlencoded
        $postFields = http_build_query([
            'To'   => $to,
            'From' => $this->from,
            'Body' => $body,
        ]);

        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_POST            => true,
            CURLOPT_RETURNTRANSFER  => true,
            CURLOPT_POSTFIELDS      => $postFields,

            // Auth: Basic auth (Account SID as username, Auth Token as password)
            CURLOPT_HTTPAUTH        => CURLAUTH_BASIC,
            CURLOPT_USERPWD         => $this->accountSid . ':' . $this->authToken,

            // Good practice headers
            CURLOPT_HTTPHEADER      => [
                'Accept: application/json',
                'Content-Type: application/x-www-form-urlencoded',
                'User-Agent: CRM-i-Portal/1.0 (Twilio SMS)',
            ],

            // Timeouts / reliability
            CURLOPT_CONNECTTIMEOUT  => 10,
            CURLOPT_TIMEOUT         => 30,

            // Helps with debugging if needed
            CURLOPT_FAILONERROR     => false,
        ]);

        $resp = curl_exec($ch);

        if ($resp === false) {
            $err = curl_error($ch);
            $no  = curl_errno($ch);
            curl_close($ch);
            throw new RuntimeException("twilio: request failed (curl {$no}): {$err}");
        }

        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        $data = json_decode($resp, true);
        if (!is_array($data)) {
            $data = [];
        }

        // Twilio errors are usually JSON with: code, message, more_info, status
        if ($code < 200 || $code >= 300) {
            $msg = $data['message'] ?? $data['error_message'] ?? null;
            $twCode = $data['code'] ?? null;
            $more = $data['more_info'] ?? null;

            $details = [];
            if ($msg) $details[] = $msg;
            if ($twCode !== null) $details[] = "twilio_code={$twCode}";
            if ($more) $details[] = "more_info={$more}";

            $detailStr = !empty($details) ? implode(' | ', $details) : 'unknown error';
            throw new RuntimeException("twilio: API error ({$code}): {$detailStr}");
        }

        // Normal success response includes: sid, status, etc.
        return $data;
    }
}
