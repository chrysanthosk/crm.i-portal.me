<?php

namespace App\Http\Controllers;

use App\Models\SmsFailure;
use App\Models\SmsProvider;
use App\Models\SmsSetting;
use App\Models\SmsSuccess;
use App\Services\SmsService;
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
     * POST bulk SMS send-now.
     * Uses SmsService::sendTest(to, message, provider_id override).
     */
    public function send(Request $request, SmsService $sms)
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
                    $targets[] = [
                        'label'  => (string) $row->name,
                        'mobile' => $normalized,
                    ];
                }
            }
        }

        // 2) Manual number (optional)
        $manual = trim((string)($data['manual_number'] ?? ''));
        if ($manual !== '') {
            $normalized = $this->normalizePhone($manual);
            if ($normalized) {
                $targets[] = [
                    'label'  => 'Manual',
                    'mobile' => $normalized,
                ];
            }
        }

        // Remove duplicates by mobile
        $targets = collect($targets)
            ->unique('mobile')
            ->values()
            ->all();

        if (empty($targets)) {
            return back()
                ->withInput()
                ->with('error', 'Please select at least one recipient or enter a valid manual number.');
        }

        $sent = 0;
        $failed = 0;
        $errors = [];
        $usedProviders = []; // track actual providers used by service (in case it falls back)

        foreach ($targets as $t) {
            $to = $t['mobile'];

            try {
                // Use same method as SmsSettingsController (provider override)
                $result = $sms->sendTest($to, $message, $providerId);

                $used = (string)($result['provider'] ?? $provider->name);
                $usedProviders[$used] = true;

                SmsSuccess::create([
                    'mobile'       => $to,
                    'provider'     => $used,
                    'success_code' => (string)($result['success_code'] ?? 'OK'),
                    'sent_at'      => now(),
                ]);

                $sent++;
            } catch (\Throwable $ex) {
                $failed++;

                SmsFailure::create([
                    'mobile'        => $to,
                    'provider'      => $provider->name,
                    'error_message' => $ex->getMessage() ?: get_class($ex),
                    'failed_at'     => now(),
                ]);

                $errors[] = "To {$to}: " . ($ex->getMessage() ?: get_class($ex));
            }
        }

        $usedList = implode(', ', array_keys($usedProviders)) ?: $provider->name;

        // Feedback messages
        if ($sent > 0 && $failed === 0) {
            return back()->with('status', "{$sent} SMS sent successfully via {$usedList}.");
        }

        if ($sent > 0 && $failed > 0) {
            $msg = "{$sent} SMS sent successfully via {$usedList}. {$failed} failed.";
            $sample = array_slice($errors, 0, 3);
            if (!empty($sample)) {
                $msg .= " Errors: " . implode(' | ', $sample);
                if (count($errors) > 3) {
                    $msg .= " …and " . (count($errors) - 3) . " more.";
                }
            }
            return back()->with('error', $msg);
        }

        // All failed
        $msg = "All SMS failed ({$failed}).";
        $sample = array_slice($errors, 0, 3);
        if (!empty($sample)) {
            $msg .= " Errors: " . implode(' | ', $sample);
            if (count($errors) > 3) {
                $msg .= " …and " . (count($errors) - 3) . " more.";
            }
        }
        return back()->with('error', $msg);
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
