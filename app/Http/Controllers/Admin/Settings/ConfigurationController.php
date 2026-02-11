<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ConfigurationController extends Controller
{
    public function edit()
    {
        $system = Setting::query()->where('key', 'system')->first();

        // Ensure $systemValue is always an array
        $systemValue = $system?->value;

        if (!is_array($systemValue)) {
            $systemValue = [];
        }

        return view('admin.settings.configuration', [
            'system' => (object)[
                'header_name' => $systemValue['header_name'] ?? config('app.name'),
                'footer_name' => $systemValue['footer_name'] ?? config('app.name'),
            ],
        ]);
    }

    public function updateSystem(Request $request)
    {
        $data = $request->validate([
            'header_name' => ['required', 'string', 'max:255'],
            'footer_name' => ['required', 'string', 'max:255'],
        ]);

        $setting = Setting::query()->firstOrNew(['key' => 'system']);

        // Preserve existing keys if any, then overwrite the two we manage here
        $current = $setting->value;
        if (!is_array($current)) {
            $current = [];
        }

        $setting->value = array_merge($current, [
            'header_name' => $data['header_name'],
            'footer_name' => $data['footer_name'],
        ]);

        $setting->save();

        // ✅ Clear cached settings so changes reflect immediately
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
            ->route('admin.settings.config.edit')
            ->with('status', 'System settings saved.');
    }
}
