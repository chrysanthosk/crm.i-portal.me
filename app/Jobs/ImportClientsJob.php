<?php

namespace App\Jobs;

use App\Models\Client;
use App\Models\ImportLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ImportClientsJob implements ShouldQueue
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

        $expected = [
            'registration_date', 'first_name', 'last_name', 'dob',
            'mobile', 'email', 'address', 'city', 'gender', 'notes', 'comments',
        ];

        $header  = null;
        $rowNum  = 0;
        $created = 0;
        $updated = 0;
        $skipped = 0;

        try {
            while (($row = fgetcsv($handle)) !== false) {
                $rowNum++;

                $rowStr = implode('', array_map(fn($v) => trim((string) $v), $row));
                if ($rowStr === '') {
                    continue;
                }

                if ($header === null) {
                    $header = array_map(fn($h) => strtolower(trim((string) $h)), $row);

                    foreach (['first_name', 'last_name', 'dob', 'mobile', 'email', 'gender'] as $req) {
                        if (!in_array($req, $header, true)) {
                            fclose($handle);
                            $log->markFailed("CSV header missing required column: {$req}");
                            return;
                        }
                    }
                    continue;
                }

                $assoc = [];
                foreach ($header as $i => $key) {
                    $assoc[$key] = isset($row[$i]) ? trim((string) $row[$i]) : null;
                }

                $data = [];
                foreach ($expected as $k) {
                    $data[$k] = $assoc[$k] ?? null;
                }

                $data['gender'] = $data['gender'] ? ucfirst(strtolower($data['gender'])) : null;

                if (!empty($data['registration_date'])) {
                    $data['registration_date'] = str_replace('T', ' ', $data['registration_date']);
                } else {
                    $data['registration_date'] = now();
                }

                $validator = validator($data, [
                    'registration_date' => ['nullable', 'date'],
                    'first_name'        => ['required', 'string', 'max:100'],
                    'last_name'         => ['required', 'string', 'max:100'],
                    'dob'               => ['required', 'date'],
                    'mobile'            => ['required', 'string', 'max:20'],
                    'email'             => ['required', 'email', 'max:150'],
                    'address'           => ['nullable', 'string', 'max:255'],
                    'city'              => ['nullable', 'string', 'max:100'],
                    'gender'            => ['required', Rule::in(['Male', 'Female', 'Other'])],
                    'notes'             => ['nullable', 'string', 'max:5000'],
                    'comments'          => ['nullable', 'string', 'max:5000'],
                ]);

                if ($validator->fails()) {
                    $skipped++;
                    continue;
                }

                $validated = $validator->validated();
                $existing  = Client::query()->where('email', $validated['email'])->first();

                if ($existing) {
                    $existing->update($validated);
                    $updated++;
                } else {
                    Client::create($validated);
                    $created++;
                }
            }
        } finally {
            fclose($handle);
            Storage::delete($log->stored_path);
        }

        $log->update(['total_rows' => $rowNum]);
        $log->markDone($created + $updated, $skipped);
    }
}
