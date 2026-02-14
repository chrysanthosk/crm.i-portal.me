<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Models\SmsSuccess;
use App\Models\SmsFailure;

class SmsLogsController extends Controller
{
    public function index()
    {
        $deliveredCount = (int) SmsSuccess::query()->count();
        $failedCount = (int) SmsFailure::query()->count();
        $sentCount = $deliveredCount + $failedCount;

        // Keep page light: show last 1000 of each
        $successLogs = SmsSuccess::query()
            ->orderByDesc('sent_at')
            ->limit(1000)
            ->get();

        $failureLogs = SmsFailure::query()
            ->orderByDesc('failed_at')
            ->limit(1000)
            ->get();

        return view('admin.settings.sms_logs', [
            'sentCount' => $sentCount,
            'deliveredCount' => $deliveredCount,
            'failedCount' => $failedCount,
            'successLogs' => $successLogs,
            'failureLogs' => $failureLogs,
        ]);
    }
}
