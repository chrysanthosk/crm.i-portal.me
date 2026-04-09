<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportLog extends Model
{
    protected $fillable = [
        'type',
        'status',
        'original_filename',
        'stored_path',
        'total_rows',
        'imported_rows',
        'skipped_rows',
        'error_message',
        'user_id',
    ];

    protected $casts = [
        'total_rows'    => 'integer',
        'imported_rows' => 'integer',
        'skipped_rows'  => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function markProcessing(): void
    {
        $this->update(['status' => 'processing']);
    }

    public function markDone(int $imported, int $skipped): void
    {
        $this->update([
            'status'        => 'done',
            'imported_rows' => $imported,
            'skipped_rows'  => $skipped,
        ]);
    }

    public function markFailed(string $message): void
    {
        $this->update([
            'status'        => 'failed',
            'error_message' => $message,
        ]);
    }
}
