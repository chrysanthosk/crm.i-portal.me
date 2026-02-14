<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\DashboardSetting;
use App\Services\SmsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class SendBirthdaySms extends Command
{
    protected $signature = 'sms:send-birthday {--dry-run}';
    protected $description = 'Send birthday SMS to clients (once per year).';

    public function handle(SmsService $sms): int
    {
        $settings = DashboardSetting::query()->first();
        if (!$settings || !$settings->sms_birthdays_enabled) {
            $this->info('Birthday SMS disabled.');
            return self::SUCCESS;
        }

        $dry = (bool) $this->option('dry-run');

        $today = now();
        $yearStart = $today->copy()->startOfYear();

        // Column compatibility (your model uses dob/mobile)
        $dobCol = Schema::hasColumn('clients', 'dob') ? 'dob' : (Schema::hasColumn('clients', 'date_of_birth') ? 'date_of_birth' : null);
        $mobileCol = Schema::hasColumn('clients', 'mobile') ? 'mobile' : (Schema::hasColumn('clients', 'phone_number') ? 'phone_number' : null);

        if (!$dobCol || !$mobileCol) {
            $this->error('Birthday SMS: missing required DOB/mobile columns in clients table.');
            return self::FAILURE;
        }

        $lastSentCol = null;
        if (Schema::hasColumn('clients', 'birthday_sms_last_sent_at')) $lastSentCol = 'birthday_sms_last_sent_at';
        if (Schema::hasColumn('clients', 'sms_birthday_last_sent_at')) $lastSentCol = 'sms_birthday_last_sent_at';

        $clientsQ = Client::query()
            ->whereNotNull($dobCol)
            ->whereMonth($dobCol, $today->month)
            ->whereDay($dobCol, $today->day)
            ->whereNotNull($mobileCol);

        if ($lastSentCol) {
            $clientsQ->where(function ($q) use ($lastSentCol, $yearStart) {
                $q->whereNull($lastSentCol)
                  ->orWhere($lastSentCol, '<', $yearStart);
            });
        }

        $clients = $clientsQ->get();

        $sent = 0;

        foreach ($clients as $c) {
            $to = trim((string)($c->{$mobileCol} ?? ''));
            if ($to === '') continue;

            // Fallback if no DB tracking column exists: cache per-year per-client
            if (!$lastSentCol) {
                $cacheKey = "birthday_sms_sent:{$c->id}:{$today->year}";
                if (Cache::get($cacheKey)) {
                    continue;
                }
            }

            $body = $this->buildMessage($settings, $c);
            $body = $this->limit165($body);

            if ($dry) {
                $this->line("[DRY] To={$to} :: {$body}");
                $sent++;
                continue;
            }

            try {
                $sms->send($to, $body);

                if ($lastSentCol) {
                    $c->{$lastSentCol} = now();
                    $c->save();
                } else {
                    Cache::put($cacheKey, true, now()->copy()->endOfYear());
                }

                $settings->increment('sms_sent_birthdays_count');
                $sent++;
            } catch (\Throwable $e) {
                $this->error("Failed sending to {$to}: " . ($e->getMessage() ?: get_class($e)));
            }
        }

        $this->info("Done. Sent: {$sent}");
        return self::SUCCESS;
    }

    private function buildMessage(DashboardSetting $settings, Client $c): string
    {
        $tpl = (string)($settings->sms_birthdays_message ?? '');
        if (trim($tpl) === '') {
            $tpl = 'Happy Birthday {first_name}! 🎉';
        }

        $company = (string)($settings->company_name ?? '');

        return strtr($tpl, [
            '{first_name}' => (string)($c->first_name ?? ''),
            '{last_name}' => (string)($c->last_name ?? ''),
            '{company_name}' => $company,
        ]);
    }

    private function limit165(string $s): string
    {
        $s = trim(preg_replace('/\s+/', ' ', $s));
        return mb_strlen($s) > 165 ? mb_substr($s, 0, 165) : $s;
    }
}
