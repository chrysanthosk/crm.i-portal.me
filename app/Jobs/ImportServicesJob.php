<?php

namespace App\Jobs;

use App\Models\ImportLog;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\VatType;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ImportServicesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 1;
    public int $timeout = 300;

    public function __construct(
        private readonly int    $importLogId,
        private readonly string $extension,
    ) {}

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

        // Build lookup maps
        $categories = ServiceCategory::query()->select('id', 'name')->get();
        $catMap = [];
        foreach ($categories as $c) {
            $catMap[mb_strtolower(trim($c->name))] = (int) $c->id;
        }

        $vatTypes      = VatType::query()->select('id', 'name', 'vat_percent')->get();
        $vatPercentMap = [];
        $vatNameMap    = [];
        foreach ($vatTypes as $v) {
            $vatPercentMap[number_format((float) $v->vat_percent, 2)] = (int) $v->id;
            $vatNameMap[mb_strtolower(trim($v->name))]                 = (int) $v->id;
        }

        $normalizeGender = function (?string $raw): string {
            $l = mb_strtolower(preg_replace('/[^a-z]/i', '', (string) $raw));
            if ($l === 'male')   return 'Male';
            if ($l === 'female') return 'Female';
            return 'Both';
        };

        $rows = [];

        try {
            if ($this->extension === 'csv') {
                $handle = fopen($fullPath, 'r');
                if (!$handle) {
                    $log->markFailed('Cannot open stored file.');
                    return;
                }
                fgetcsv($handle); // skip header
                while (($r = fgetcsv($handle)) !== false) {
                    $rows[] = $r;
                }
                fclose($handle);
            } else {
                $spreadsheet = IOFactory::load($fullPath);
                $sheet       = $spreadsheet->getActiveSheet();
                $arr         = $sheet->toArray(null, true, true, true);
                foreach ($arr as $index => $row) {
                    if ($index === 1) continue;
                    $rows[] = [
                        $row['A'] ?? '', $row['B'] ?? '', $row['C'] ?? '', $row['D'] ?? '',
                        $row['E'] ?? '', $row['F'] ?? '', $row['G'] ?? '', $row['H'] ?? '',
                    ];
                }
            }
        } catch (\Throwable $e) {
            Storage::delete($log->stored_path);
            $log->markFailed('Could not read file: ' . $e->getMessage());
            return;
        }

        $inserted = 0;
        $skipped  = 0;

        foreach ($rows as $r) {
            for ($i = 0; $i < 8; $i++) {
                if (!isset($r[$i])) $r[$i] = '';
            }

            [$rawCat, $name, $rawGender, $rawPrice, $rawVat, $rawDur, $rawWait, $comment] =
                array_map(fn($x) => is_string($x) ? trim($x) : $x, $r);

            if ($rawCat === '' || $name === '') {
                $skipped++;
                continue;
            }

            $catKey = mb_strtolower($rawCat);
            if (!isset($catMap[$catKey])) {
                $skipped++;
                continue;
            }
            $category_id = $catMap[$catKey];

            $gender = $normalizeGender($rawGender);
            $price  = is_numeric($rawPrice) ? (float) $rawPrice : 0.00;

            $vat_type_id = 0;
            if (is_numeric($rawVat)) {
                $key = number_format((float) $rawVat, 2);
                if (isset($vatPercentMap[$key])) {
                    $vat_type_id = $vatPercentMap[$key];
                }
            } else {
                $vnKey = mb_strtolower($rawVat);
                if (isset($vatNameMap[$vnKey])) {
                    $vat_type_id = $vatNameMap[$vnKey];
                }
            }
            if ($vat_type_id <= 0) {
                $skipped++;
                continue;
            }

            $duration = is_numeric($rawDur) ? (int) $rawDur : 0;
            $waiting  = is_numeric($rawWait) ? (int) $rawWait : 0;

            $exists = Service::query()
                ->where('name', $name)
                ->where('category_id', $category_id)
                ->where('gender', $gender)
                ->exists();

            if ($exists) {
                $skipped++;
                continue;
            }

            Service::create([
                'name'        => $name,
                'category_id' => $category_id,
                'price'       => $price,
                'vat_type_id' => $vat_type_id,
                'duration'    => $duration,
                'waiting'     => $waiting,
                'gender'      => $gender,
                'comment'     => $comment !== '' ? $comment : null,
            ]);

            $inserted++;
        }

        Storage::delete($log->stored_path);
        $total = count($rows);
        $log->update(['total_rows' => $total]);
        $log->markDone($inserted, $skipped);
    }
}
