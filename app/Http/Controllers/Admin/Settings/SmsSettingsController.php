<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Models\SmsProvider;
use App\Models\SmsSetting;
use App\Services\SmsService;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SmsSettingsController extends Controller
{
    public function edit()
    {
        $this->ensureDefaultProviders();

        $providers = SmsProvider::query()
            ->orderBy('name')
            ->get();

        $providersPriority = SmsProvider::query()
            ->where('is_active', 1)
            ->orderBy('priority')
            ->orderBy('id')
            ->get();

        // for dropdown: active only
        $activeProviders = SmsProvider::query()
            ->where('is_active', 1)
            ->orderBy('priority')
            ->orderBy('id')
            ->get();

        return view('admin.settings.sms_settings', [
            'providers' => $providers,
            'providersPriority' => $providersPriority,
            'activeProviders' => $activeProviders,
        ]);
    }

    /**
     * Ensure the 3 default providers exist with correct priority order.
     * sms.to first, twilio second, infobip third.
     */
    private function ensureDefaultProviders(): void
    {
        $defaults = [
            ['name' => 'sms.to',  'doc_url' => 'https://github.com/intergo/sms.to-php', 'priority' => 0],
            ['name' => 'twilio',  'doc_url' => 'https://www.twilio.com/docs/sms',     'priority' => 1],
            ['name' => 'infobip', 'doc_url' => 'https://www.infobip.com/docs/sms',     'priority' => 2],
        ];

        DB::transaction(function () use ($defaults) {
            foreach ($defaults as $d) {
                SmsProvider::query()->updateOrCreate(
                    ['name' => $d['name']],
                    [
                        'doc_url' => $d['doc_url'],
                        'is_active' => 1,
                        'priority' => $d['priority'],
                    ]
                );
            }
        });
    }

    /**
     * Create / Update provider from modal
     * POST /settings/sms/providers/save
     */
    public function saveProvider(Request $request)
    {
        $data = $request->validate([
            'prov_id'   => ['nullable', 'integer', 'exists:sms_providers,id'],
            'prov_name' => ['required', 'string', 'max:100'],
            'prov_doc'  => ['nullable', 'string', 'max:255'],
        ]);

        $id = (int)($data['prov_id'] ?? 0);

        if ($id > 0) {
            $provider = SmsProvider::query()->findOrFail($id);
            $provider->update([
                'name' => trim($data['prov_name']),
                'doc_url' => trim((string)($data['prov_doc'] ?? '')),
            ]);

            Audit::log('settings', 'sms.provider.update', 'sms_providers', $provider->id, [
                'name' => $provider->name,
            ]);

            return redirect()->route('settings.sms.edit')->with('status', 'Provider updated.');
        }

        $provider = SmsProvider::query()->create([
            'name' => trim($data['prov_name']),
            'doc_url' => trim((string)($data['prov_doc'] ?? '')),
            'is_active' => 1,
            'priority' => 99,
        ]);

        Audit::log('settings', 'sms.provider.create', 'sms_providers', $provider->id, [
            'name' => $provider->name,
        ]);

        return redirect()->route('settings.sms.edit')->with('status', 'Provider added.');
    }

    /**
     * DELETE /settings/sms/providers/{provider}
     */
    public function deleteProvider(SmsProvider $provider)
    {
        DB::transaction(function () use ($provider) {
            SmsSetting::query()->where('provider_id', $provider->id)->delete();
            $provider->delete();
        });

        Audit::log('settings', 'sms.provider.delete', 'sms_providers', $provider->id, [
            'name' => $provider->name,
        ]);

        return redirect()->route('settings.sms.edit')->with('status', 'Provider deleted.');
    }

    /**
     * POST /settings/sms/providers/{provider}/toggle (AJAX)
     */
    public function toggleProviderActive(Request $request, SmsProvider $provider)
    {
        $data = $request->validate([
            'is_active' => ['required'],
        ]);

        $provider->is_active = (int)$data['is_active'] ? 1 : 0;
        $provider->save();

        Audit::log('settings', 'sms.provider.toggle', 'sms_providers', $provider->id, [
            'is_active' => (int)$provider->is_active,
        ]);

        return response()->json(['ok' => true]);
    }

    /**
     * POST /settings/sms/providers/priority (AJAX)
     */
    public function updatePriority(Request $request)
    {
        $data = $request->validate([
            'order' => ['required', 'array'],
            'order.*' => ['integer', 'exists:sms_providers,id'],
        ]);

        foreach ($data['order'] as $priority => $id) {
            SmsProvider::query()->where('id', (int)$id)->update([
                'priority' => (int)$priority
            ]);
        }

        Audit::log('settings', 'sms.provider.priority', 'sms_providers', 0, [
            'order' => $data['order'],
        ]);

        return response()->json(['ok' => true]);
    }

    /**
     * GET /settings/sms/providers/{provider}/settings (AJAX)
     */
    public function fetchSettings(SmsProvider $provider)
    {
        $row = SmsSetting::query()
            ->where('provider_id', $provider->id)
            ->first();

        return response()->json([
            'api_key'    => $row?->api_key ?? '',
            'api_secret' => $row?->api_secret ?? '',
            'sender_id'  => $row?->sender_id ?? '',
            'is_enabled' => (int)($row?->is_enabled ?? 0),
        ]);
    }

    /**
     * POST /settings/sms/settings/save
     */
    public function saveSettings(Request $request)
    {
        $data = $request->validate([
            'provider_id' => ['required', 'integer', 'exists:sms_providers,id'],
            'api_key' => ['required', 'string'],
            'api_secret' => ['nullable', 'string'],
            'sender_id' => ['nullable', 'string', 'max:50'],
            'is_enabled' => ['nullable'],
        ]);

        $enabled = $request->boolean('is_enabled');

        $setting = SmsSetting::query()->updateOrCreate(
            ['provider_id' => (int)$data['provider_id']],
            [
                'api_key' => $data['api_key'],
                'api_secret' => $data['api_secret'] ?? null,
                'sender_id' => $data['sender_id'] ?? null,
                'is_enabled' => $enabled ? 1 : 0,
            ]
        );

        Audit::log('settings', 'sms.settings.save', 'sms_settings', $setting->id, [
            'provider_id' => (int)$data['provider_id'],
            'is_enabled' => (int)$enabled,
        ]);

        return redirect()->route('settings.sms.edit')->with('status', 'SMS settings saved.');
    }

    /**
     * POST /settings/sms/test
     * - If provider_id is set: force that provider (ignores priority).
     * - Else: uses enabled providers + priority.
     */
    public function sendTestSms(Request $request, SmsService $sms)
    {
        $data = $request->validate([
            'to' => ['required', 'string', 'max:30'],
            'message' => ['required', 'string', 'max:165'],
            'provider_id' => ['nullable', 'integer', 'exists:sms_providers,id'],
        ]);

        try {
            $result = $sms->sendTest(
                trim($data['to']),
                trim($data['message']),
                $data['provider_id'] ? (int)$data['provider_id'] : null
            );

            $used = $result['provider'] ?? 'unknown';
            return redirect()->route('settings.sms.edit')->with('status', "Test SMS sent successfully via {$used}.");
        } catch (\Throwable $e) {
            return redirect()->route('settings.sms.edit')->with('error', 'Test SMS failed: ' . ($e->getMessage() ?: get_class($e)));
        }
    }
}
