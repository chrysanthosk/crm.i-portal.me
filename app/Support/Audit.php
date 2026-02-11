<?php

namespace App\Support;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;

class Audit
{
    public static function log(string $category, string $action, ?string $targetType = null, $targetId = null, array $meta = []): void
    {
        try {
            AuditLog::create([
                'user_id' => Auth::id(),
                'category' => $category,
                'action' => $action,
                'target_type' => $targetType,
                'target_id' => $targetId ? (string)$targetId : null,
                'ip' => request()->ip(),
                'meta' => $meta ?: null,
            ]);
        } catch (\Throwable $e) {
            // never break the request due to audit failures
        }
    }
}
