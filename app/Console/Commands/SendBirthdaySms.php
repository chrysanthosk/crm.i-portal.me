<?php

namespace App\Console\Commands;

use App\Jobs\SendBirthdaySmsJob;
use App\Models\Client;
use App\Models\DashboardSetting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class SendBirthdaySms extends Command
{
    protected $signature = 'sms:send-birthday {--dry-run}';
    protected $description = 'Send birthday SMS to clients (once per year).';

    public function handle(): int
    {
        $settings = DashboardSetting::query()->first();
        if (!$settings || !$settings->sms_birthdays_enabled) {
            $this->info('Birthday SMS disabled.');
            return self::SUCCESS;
        }

        $dry = (bool) $this->option('dry-run');

        $today = now();
        $yearStart = $today->copy()->startOfYear();

        $dobCol    = Schema::hasColumn('clients', 'dob') ? 'dob' : (Schema::hasColumn('clients', 'date_of_birth') ? 'date_of_birth' : null);
        $mobileCol = Schema::hasColumn('clients', 'mobile') ? 'mobile' : (Schema::hasColumn('clients', 'phone_number') ? 'phone_number' : null);

        if (!$dobCol || !$mobileCol) {
            $this->error('Birthday SMS: missing required DOB/mobile columns in clients table.');
            return self::FAILURE;
        }

        $lastSentCol = null;
        if (Schema::hasColumn('clients', 'birthday_sms_last_sent_at')) {
            $lastSentCol = 'birthday_sms_last_sent_at';
        }

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
        $dispatched = 0;

        foreach ($clients as $c) {
            $to = trim((string) ($c->{$mobileCol} ?? ''));
            if ($to === '') {
                continue;
            }

            if ($dry) {
                $this->line("[DRY] Would dispatch birthday SMS job for client #{$c->id}");
                $dispatched++;
                continue;
            }

            SendBirthdaySmsJob::dispatch($c->id);
            $dispatched++;
        }

        $this->info("Done. Dispatched: {$dispatched} birthday SMS job(s).");
        return self::SUCCESS;
    }
}
