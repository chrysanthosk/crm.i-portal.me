<?php

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

if (! function_exists('system_setting')) {
    function system_setting(string $key, $default = null)
    {
        $system = Cache::remember('settings.system', 300, function () {
            $row = Setting::query()->where('key', 'system')->first();

            $val = $row?->value;

            // With casts, $val should be array. If not, decode safely.
            if (is_string($val)) {
                $decoded = json_decode($val, true);
                $val = is_array($decoded) ? $decoded : [];
            }

            return is_array($val) ? $val : [];
        });

        return $system[$key] ?? $default;
    }
}

if (! function_exists('setting')) {
    // Kept for future use (settings stored one-per-key)
    function setting(string $key, $default = null)
    {
        $all = Cache::remember('settings.all', 300, function () {
            return Setting::query()->pluck('value', 'key')->toArray();
        });

        return $all[$key] ?? $default;
    }
}
