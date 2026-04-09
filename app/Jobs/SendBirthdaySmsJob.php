<?php

namespace App\Jobs;

use App\Models\Client;
use App\Models\DashboardSetting;
use App\Services\SmsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

class SendBirthdaySmsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 30;

    public function __construct(private readonly int $clientId) {}

    public function handle(SmsService $sms): void
    {
        $client = Client::find($this->clientId);
        if (!$client) {
            return;
        }

        $to = trim((string) ($client->mobile ?? ''));
        if ($to === '') {
            return;
        }

        $today = now();

        // Guard: skip if already sent this year
        if ($client->birthday_sms_last_sent_at) {
            if ($client->birthday_sms_last_sent_at >= $today->copy()->startOfYear()) {
                return;
            }
        } else {
            $cacheKey = "birthday_sms_sent:{$client->id}:{$today->year}";
            if (Cache::get($cacheKey)) {
                return;
            }
        }

        $settings = DashboardSetting::query()->first();
        $body = $this->buildMessage($settings, $client);
        $body = $this->limit165($body);

        try {
            $sms->send($to, $body);

            if ($client->birthday_sms_last_sent_at !== null || \Illuminate\Support\Facades\Schema::hasColumn('clients', 'birthday_sms_last_sent_at')) {
                $client->birthday_sms_last_sent_at = $today;
                $client->save();
            } else {
                Cache::put("birthday_sms_sent:{$client->id}:{$today->year}", true, $today->copy()->endOfYear());
            }

            if ($settings) {
                $settings->increment('sms_sent_birthdays_count');
            }
        } catch (\Throwable $e) {
            throw $e;
        }
    }

    private function buildMessage(?DashboardSetting $settings, Client $client): string
    {
        $tpl = (string) ($settings?->sms_birthdays_message ?? '');
        if (trim($tpl) === '') {
            $tpl = 'Happy Birthday {first_name}! 🎉';
        }

        return strtr($tpl, [
            '{first_name}'   => (string) ($client->first_name ?? ''),
            '{last_name}'    => (string) ($client->last_name ?? ''),
            '{company_name}' => (string) ($settings?->company_name ?? ''),
        ]);
    }

    private function limit165(string $s): string
    {
        $s = trim(preg_replace('/\s+/', ' ', $s));
        return mb_strlen($s) > 165 ? mb_substr($s, 0, 165) : $s;
    }
}
