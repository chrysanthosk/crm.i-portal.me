<?php

namespace App\Http\Controllers;

use App\Jobs\SendSingleSmsJob;
use App\Models\SmsProvider;
use App\Models\SmsSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BulkSmsController extends Controller
{
    public function index()
    {
        // Active providers for dropdown
        $providers = SmsProvider::query()
            ->where('is_active', 1)
            ->orderBy('priority')
            ->orderBy('id')
            ->get();

        // Clients list
        $clients = DB::table('clients')
            ->select('id', 'first_name', 'last_name', 'mobile')
            ->orderByDesc('created_at')
            ->get();

        return view('bulk_sms.index', [
            'providers' => $providers,
            'clients'   => $clients,
        ]);
    }

    /**
     * POST bulk SMS — dispatches one queued job per recipient and returns immediately.
     */
    public function send(Request $request)
    {
        $data = $request->validate([
            'provider_id'   => ['required', 'integer', 'exists:sms_providers,id'],
            'message'       => ['required', 'string', 'max:165'],
            'manual_number' => ['nullable', 'string', 'max:50'],
            'clients'       => ['nullable', 'array'],
            'clients.*'     => ['integer'],
        ]);

        $providerId = (int) $data['provider_id'];
        $message    = trim((string) $data['message']);

        // Provider must be active
        $provider = SmsProvider::query()
            ->where('id', $providerId)
            ->where('is_active', 1)
            ->first();

        if (!$provider) {
            return back()
                ->withInput()
                ->with('error', 'Invalid provider selected (or provider is inactive).');
        }

        // Provider setting must exist + be enabled
        $setting = SmsSetting::query()
            ->where('provider_id', $provider->id)
            ->where('is_enabled', 1)
            ->first();

        if (!$setting) {
            return back()
                ->withInput()
                ->with('error', 'This SMS provider is not enabled (sms_settings.is_enabled=0) or settings are missing.');
        }

        // Build targets
        $targets = [];

        // 1) Selected clients
        $clientIds = $data['clients'] ?? [];
        if (!empty($clientIds)) {
            $rows = DB::table('clients')
                ->select('id', DB::raw("CONCAT(first_name,' ',last_name) AS name"), 'mobile')
                ->whereIn('id', $clientIds)
                ->get();

            foreach ($rows as $row) {
                $normalized = $this->normalizePhone($row->mobile);
                if ($normalized) {
                    $targets[] = $normalized;
                }
            }
        }

        // 2) Manual number (optional)
        $manual = trim((string)($data['manual_number'] ?? ''));
        if ($manual !== '') {
            $normalized = $this->normalizePhone($manual);
            if ($normalized) {
                $targets[] = $normalized;
            }
        }

        // Remove duplicates
        $targets = array_values(array_unique($targets));

        if (empty($targets)) {
            return back()
                ->withInput()
                ->with('error', 'Please select at least one recipient or enter a valid manual number.');
        }

        foreach ($targets as $to) {
            SendSingleSmsJob::dispatch($to, $message, $providerId, $provider->name);
        }

        $count = count($targets);
        return back()->with('status', "{$count} SMS " . ($count === 1 ? 'job' : 'jobs') . " queued via {$provider->name}. Messages will be sent shortly.");
    }

    /**
     * Phone normalize (Cyprus-first rules, similar to your old PHP page)
     * - strip non-digits
     * - if leading 0 => drop it
     * - if 8 digits => prefix +357
     * - if starts with 357 => add +
     * - else: treat as international and add +
     */
    private function normalizePhone(?string $raw): ?string
    {
        $raw = trim((string)$raw);
        if ($raw === '') return null;

        $num = preg_replace('/\D+/', '', $raw) ?? '';
        $num = trim($num);
        if ($num === '') return null;

        if (str_starts_with($num, '0')) {
            $num = substr($num, 1);
        }

        if (strlen($num) === 8) {
            $num = '357' . $num;
        }

        if (str_starts_with($num, '357')) {
            return '+' . $num;
        }

        return '+' . $num;
    }
}
