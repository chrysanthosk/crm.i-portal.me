<?php

namespace App\Jobs;

use App\Models\ImportLog;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\VatType;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class ImportProductsJob implements ShouldQueue
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

        $categoriesByName = ProductCategory::query()
            ->get()
            ->keyBy(fn($c) => mb_strtolower(trim((string) $c->name)));

        $vatByName = VatType::query()
            ->get()
            ->keyBy(fn($v) => mb_strtolower(trim((string) $v->name)));

        $fh = fopen($fullPath, 'r');
        if (!$fh) {
            $log->markFailed('Cannot open stored file.');
            return;
        }

        $header = fgetcsv($fh);
        $header = array_map(fn($h) => strtolower(trim((string) $h)), $header ?: []);

        $idx = fn($key) => array_search($key, $header, true);

        $catIdx  = $idx('category');
        $nameIdx = $idx('name');
        $ppIdx   = $idx('purchase_price');
        $pvIdx   = $idx('purchase_vat');
        $spIdx   = $idx('sell_price');
        $svIdx   = $idx('sell_vat');
        $qsIdx   = $idx('quantity_stock');
        $qbIdx   = $idx('quantity_in_box');
        $cmIdx   = $idx('comment');

        if ($catIdx === false || $nameIdx === false || $ppIdx === false || $pvIdx === false || $spIdx === false || $svIdx === false) {
            fclose($fh);
            Storage::delete($log->stored_path);
            $log->markFailed('CSV missing required headers. Please use the template.');
            return;
        }

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $errors  = 0;

        try {
            while (($row = fgetcsv($fh)) !== false) {
                $categoryRaw = trim((string) ($row[$catIdx] ?? ''));
                $name        = trim((string) ($row[$nameIdx] ?? ''));

                if ($categoryRaw === '' || $name === '') {
                    $skipped++;
                    continue;
                }

                $categoryKey = mb_strtolower($categoryRaw);
                $category    = $categoriesByName->get($categoryKey);

                // Support ID as well as name
                if (!$category && is_numeric($categoryRaw)) {
                    $category = $categoriesByName->first(fn($c) => $c->id == (int) $categoryRaw);
                }

                if (!$category) {
                    $errors++;
                    continue;
                }

                $purchaseVatRaw = trim((string) ($row[$pvIdx] ?? ''));
                $sellVatRaw     = trim((string) ($row[$svIdx] ?? ''));

                $purchaseVatId = $this->vatToId($purchaseVatRaw, $vatByName);
                $sellVatId     = $this->vatToId($sellVatRaw, $vatByName);

                if (!$purchaseVatId || !$sellVatId) {
                    $errors++;
                    continue;
                }

                $data = [
                    'category_id'           => $category->id,
                    'name'                  => $name,
                    'purchase_price'        => (float) ($row[$ppIdx] ?? 0),
                    'purchase_vat_type_id'  => $purchaseVatId,
                    'sell_price'            => (float) ($row[$spIdx] ?? 0),
                    'sell_vat_type_id'      => $sellVatId,
                    'quantity_stock'        => (int) ($qsIdx !== false ? ($row[$qsIdx] ?? 0) : 0),
                    'quantity_in_box'       => (int) ($qbIdx !== false ? ($row[$qbIdx] ?? 1) : 1),
                    'comment'               => $cmIdx !== false ? (string) ($row[$cmIdx] ?? '') : null,
                ];

                try {
                    $existing = Product::query()
                        ->where('category_id', $category->id)
                        ->where('name', $name)
                        ->first();

                    if ($existing) {
                        $existing->update($data);
                        $updated++;
                    } else {
                        Product::create($data);
                        $created++;
                    }
                } catch (\Throwable) {
                    $errors++;
                }
            }
        } finally {
            fclose($fh);
            Storage::delete($log->stored_path);
        }

        $log->markDone($created + $updated, $skipped + $errors);
    }

    private function vatToId(string $raw, Collection $vatByName): ?int
    {
        if ($raw === '') {
            return null;
        }

        $key = mb_strtolower($raw);
        if ($vatByName->has($key)) {
            return (int) $vatByName->get($key)->id;
        }

        if (is_numeric($raw)) {
            $match = $vatByName->first(fn($v) => $v->id == (int) $raw);
            if ($match) {
                return (int) $match->id;
            }
        }

        return null;
    }
}
