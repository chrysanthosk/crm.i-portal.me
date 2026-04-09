<?php

namespace App\Jobs;

use App\Models\ImportLog;
use App\Models\Supplier;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class ImportSuppliersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 1;
    public int $timeout = 300;

    public function __construct(private readonly int $importLogId) {}

    public function handle(): void
    {
        $log = ImportLog::find($this->importLogId);
        if (!$log) {
            return;
        }

        $log->markProcessing();

        $fullPath = Storage::path($log->stored_path);
        if (!is_readable($fullPath)) {
            $log->markFailed('Stored file not readable.');
            return;
        }

        $handle = fopen($fullPath, 'r');
        if (!$handle) {
            $log->markFailed('Cannot open stored file.');
            return;
        }

        $header = fgetcsv($handle);
        if (!$header) {
            fclose($handle);
            Storage::delete($log->stored_path);
            $log->markFailed('CSV file is empty.');
            return;
        }

        $header   = array_map(fn($h) => strtolower(trim((string) $h)), $header);
        $required = ['name', 'type'];
        foreach ($required as $col) {
            if (!in_array($col, $header, true)) {
                fclose($handle);
                Storage::delete($log->stored_path);
                $log->markFailed("Missing required column: {$col}");
                return;
            }
        }

        $colIndex = array_flip($header);
        $created  = 0;
        $skipped  = 0;
        $errors   = 0;

        try {
            while (($row = fgetcsv($handle)) !== false) {
                if (count(array_filter($row, fn($v) => trim((string) $v) !== '')) === 0) {
                    continue;
                }

                $name  = trim((string) ($row[$colIndex['name']] ?? ''));
                $type  = trim((string) ($row[$colIndex['type']] ?? ''));

                if ($name === '' || $type === '') {
                    $skipped++;
                    continue;
                }

                $mobile  = trim((string) ($row[$colIndex['mobile'] ?? -1] ?? ''));
                $phone   = trim((string) ($row[$colIndex['phone'] ?? -1] ?? ''));
                $email   = trim((string) ($row[$colIndex['email'] ?? -1] ?? ''));
                $comment = trim((string) ($row[$colIndex['comment'] ?? -1] ?? ''));

                if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $errors++;
                    continue;
                }

                Supplier::create([
                    'name'    => $name,
                    'type'    => $type,
                    'mobile'  => $mobile !== '' ? $mobile : null,
                    'phone'   => $phone !== '' ? $phone : null,
                    'email'   => $email !== '' ? $email : null,
                    'comment' => $comment !== '' ? $comment : null,
                ]);

                $created++;
            }
        } finally {
            fclose($handle);
            Storage::delete($log->stored_path);
        }

        $log->markDone($created, $skipped + $errors);
    }
}
