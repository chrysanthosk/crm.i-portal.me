<?php

namespace App\Services\SmsProviders;

interface SmsProviderInterface
{
    /**
     * Provider key used in DB (e.g. sms.to, twilio, infobip)
     */
    public function name(): string;

    /**
     * Send SMS and return provider response as array.
     */
    public function send(string $to, string $body): array;
}
