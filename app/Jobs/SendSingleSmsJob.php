<?php

namespace App\Jobs;

use App\Models\SmsFailure;
use App\Models\SmsProvider;
use App\Models\SmsSuccess;
use App\Services\SmsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendSingleSmsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 30;

    public function __construct(
        private readonly string $to,
        private readonly string $message,
        private readonly int    $providerId,
        private readonly string $providerName,
    ) {}

    public function handle(SmsService $sms): void
    {
        try {
            $result = $sms->sendTest($this->to, $this->message, $this->providerId);

            SmsSuccess::create([
                'mobile'       => $this->to,
                'provider'     => (string) ($result['provider'] ?? $this->providerName),
                'success_code' => (string) ($result['success_code'] ?? 'OK'),
                'sent_at'      => now(),
            ]);
        } catch (\Throwable $e) {
            SmsFailure::create([
                'mobile'        => $this->to,
                'provider'      => $this->providerName,
                'error_message' => $e->getMessage() ?: get_class($e),
                'failed_at'     => now(),
            ]);

            // Re-throw so the queue marks this job as failed after retries
            throw $e;
        }
    }
}
