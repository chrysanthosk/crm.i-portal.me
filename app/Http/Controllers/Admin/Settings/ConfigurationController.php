<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\DashboardSetting;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ConfigurationController extends Controller
{
    public function edit()
    {
        $system = Setting::query()->where('key', 'system')->first();

        $systemValue = $system?->value;
        if (!is_array($systemValue)) {
            $systemValue = [];
        }

        // Single-row table (id=1). If empty, we still want safe defaults.
        $dashboard = DashboardSetting::query()->first();

        return view('admin.settings.configuration', [
            'system' => (object)[
                'header_name' => $systemValue['header_name'] ?? config('app.name'),
                'footer_name' => $systemValue['footer_name'] ?? config('app.name'),
            ],
            'dashboard' => $dashboard,
        ]);
    }

    /**
     * PUT settings/configuration/system
     */
    public function updateSystem(Request $request)
    {
        $data = $request->validate([
            'header_name' => ['required', 'string', 'max:255'],
            'footer_name' => ['required', 'string', 'max:255'],
        ]);

        $setting = Setting::query()->firstOrNew(['key' => 'system']);

        $current = $setting->value;
        if (!is_array($current)) {
            $current = [];
        }

        $setting->value = array_merge($current, [
            'header_name' => $data['header_name'],
            'footer_name' => $data['footer_name'],
        ]);

        $setting->save();

        Cache::forget('settings.all');
        Cache::forget('settings.system');

        Audit::log(
            'settings',
            'config.system.update',
            'settings',
            $setting->id,
            $setting->value
        );

        return redirect()
            ->route('settings.config.edit')
            ->with('status', 'System settings saved.');
    }

    /**
     * PUT settings/configuration/company
     */
    public function updateCompany(Request $request)
    {
        $data = $request->validate([
            'dashboard_name'       => ['nullable', 'string', 'max:255'],
            'company_name'         => ['required', 'string', 'max:255'],
            'company_vat_number'   => ['nullable', 'string', 'max:50'],
            'company_phone_number' => ['nullable', 'string', 'max:50'],
            'company_address'      => ['nullable', 'string', 'max:2000'],
        ]);

        $row = DashboardSetting::query()->first() ?: new DashboardSetting();

        $row->dashboard_name       = $data['dashboard_name'] ?? '';
        $row->company_name         = $data['company_name'];
        $row->company_vat_number   = $data['company_vat_number'] ?? null;
        $row->company_phone_number = $data['company_phone_number'] ?? null;
        $row->company_address      = $data['company_address'] ?? null;

        $row->save();

        Cache::forget('dashboard_settings');

        Audit::log(
            'settings',
            'config.company.update',
            'dashboard_settings',
            $row->id,
            $row->toArray()
        );

        return redirect()
            ->route('settings.config.edit')
            ->with('status', 'Company settings saved.');
    }

    /**
     * PUT settings/configuration/sms
     */
    public function updateSms(Request $request)
    {
        $data = $request->validate([
            'sms_appointments_enabled' => ['nullable'],
            'sms_appointments_message' => ['nullable', 'string', 'max:165'],
            'sms_birthdays_enabled'    => ['nullable'],
            'sms_birthdays_message'    => ['nullable', 'string', 'max:165'],
        ]);

        $row = DashboardSetting::query()->first() ?: new DashboardSetting();

        $row->sms_appointments_enabled = $request->boolean('sms_appointments_enabled');
        $row->sms_appointments_message = trim((string)($data['sms_appointments_message'] ?? '')) ?: null;

        $row->sms_birthdays_enabled = $request->boolean('sms_birthdays_enabled');
        $row->sms_birthdays_message = trim((string)($data['sms_birthdays_message'] ?? '')) ?: null;

        // IMPORTANT: do NOT modify counters here. They should be updated by your SMS job/service.
        $row->save();

        Cache::forget('dashboard_settings');

        Audit::log(
            'settings',
            'config.sms.update',
            'dashboard_settings',
            $row->id,
            [
                'sms_appointments_enabled' => (bool)$row->sms_appointments_enabled,
                'sms_appointments_message' => $row->sms_appointments_message,
                'sms_birthdays_enabled'    => (bool)$row->sms_birthdays_enabled,
                'sms_birthdays_message'    => $row->sms_birthdays_message,
            ]
        );

        return redirect()
            ->route('settings.config.edit')
            ->with('status', 'SMS settings saved.');
    }
}
