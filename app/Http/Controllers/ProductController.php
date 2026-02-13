<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\VatType;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    public function index()
    {
        $products = Product::query()
            ->with(['category', 'purchaseVatType', 'sellVatType'])
            ->orderBy('name')
            ->paginate(50);

        $categories = ProductCategory::query()->orderBy('name')->get();
        $vatTypes   = VatType::query()->orderBy('name')->get();

        return view('products.index', compact('products', 'categories', 'vatTypes'));
    }

    public function create()
    {
        $categories = ProductCategory::query()->orderBy('name')->get();
        $vatTypes   = VatType::query()->orderBy('name')->get();

        return view('products.create', compact('categories', 'vatTypes'));
    }

    public function edit(Product $product)
    {
        $categories = ProductCategory::query()->orderBy('name')->get();
        $vatTypes   = VatType::query()->orderBy('name')->get();

        return view('products.edit', compact('product', 'categories', 'vatTypes'));
    }

    public function store(Request $request)
    {
        $data = $this->validateProduct($request);

        Product::create($data);

        return redirect()
            ->route('products.index')
            ->with('status', 'Product created successfully.');
    }

    public function update(Request $request, Product $product)
    {
        $data = $this->validateProduct($request, (int)$product->id);

        $product->update($data);

        return redirect()
            ->route('products.index')
            ->with('status', 'Product updated successfully.');
    }

    public function destroy(Product $product)
    {
        $product->delete();

        return redirect()
            ->route('products.index')
            ->with('status', 'Product deleted successfully.');
    }

    /**
     * GET /products/export
     */
    public function export()
    {
        $rows = Product::query()
            ->with(['category', 'purchaseVatType', 'sellVatType'])
            ->orderBy('name')
            ->get();

        $filename = 'products_' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');

            fputcsv($out, [
                'category',                 // category name (friendly)
                'category_id',              // category id
                'name',
                'purchase_price',
                'purchase_vat',             // vat name
                'purchase_vat_type_id',     // vat id
                'sell_price',
                'sell_vat',                 // vat name
                'sell_vat_type_id',         // vat id
                'quantity_stock',
                'quantity_in_box',
                'comment',
                'created_at',
                'updated_at',
            ]);

            foreach ($rows as $p) {
                fputcsv($out, [
                    $p->category?->name,
                    $p->category_id,
                    (string)$p->name,
                    (string)$p->purchase_price,
                    $p->purchaseVatType?->name,
                    $p->purchase_vat_type_id,
                    (string)$p->sell_price,
                    $p->sellVatType?->name,
                    $p->sell_vat_type_id,
                    (int)$p->quantity_stock,
                    (int)$p->quantity_in_box,
                    (string)($p->comment ?? ''),
                    optional($p->created_at)->toDateTimeString(),
                    optional($p->updated_at)->toDateTimeString(),
                ]);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * GET /products/template
     */
    public function template()
    {
        $filename = 'products_template.csv';

        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');

            // Accept IDs or names for category and vat columns (import supports both).
            fputcsv($out, [
                'category',          // category name OR id
                'name',
                'purchase_price',
                'purchase_vat',      // vat name OR id
                'sell_price',
                'sell_vat',          // vat name OR id
                'quantity_stock',
                'quantity_in_box',
                'comment',
            ]);

            fputcsv($out, [
                'Supplements',
                'Vitamin C 1000mg',
                '10.00',
                'VAT 19%',
                '15.00',
                'VAT 19%',
                '50',
                '1',
                '',
            ]);

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * POST /products/import
     */
    public function import(Request $request)
    {
        $request->validate([
            'csv' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
        ]);

        $categoriesByName = ProductCategory::query()
            ->get()
            ->keyBy(fn($c) => mb_strtolower(trim((string)$c->name)));

        $vatByName = VatType::query()
            ->get()
            ->keyBy(fn($v) => mb_strtolower(trim((string)$v->name)));

        $path = $request->file('csv')->getRealPath();
        $fh = fopen($path, 'r');

        $header = fgetcsv($fh);
        $header = array_map(fn($h) => strtolower(trim((string)$h)), $header ?: []);

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
            return back()->with('error', 'CSV missing required headers. Please use the template.');
        }

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $errors  = 0;

        while (($row = fgetcsv($fh)) !== false) {
            $categoryRaw = trim((string)($row[$catIdx] ?? ''));
            $name        = trim((string)($row[$nameIdx] ?? ''));

            if ($categoryRaw === '' || $name === '') {
                $skipped++;
                continue;
            }

            $categoryId = $this->categoryToId($categoryRaw, $categoriesByName);
            if (!$categoryId) { $errors++; continue; }

            $purchaseVatRaw = trim((string)($row[$pvIdx] ?? ''));
            $sellVatRaw     = trim((string)($row[$svIdx] ?? ''));

            $purchaseVatId = $this->vatToId($purchaseVatRaw, $vatByName);
            $sellVatId     = $this->vatToId($sellVatRaw, $vatByName);

            if (!$purchaseVatId || !$sellVatId) { $errors++; continue; }

            $data = [
                'category_id' => $categoryId,
                'name' => $name,
                'purchase_price' => (float)($row[$ppIdx] ?? 0),
                'purchase_vat_type_id' => $purchaseVatId,
                'sell_price' => (float)($row[$spIdx] ?? 0),
                'sell_vat_type_id' => $sellVatId,
                'quantity_stock' => (int)($qsIdx !== false ? ($row[$qsIdx] ?? 0) : 0),
                'quantity_in_box' => (int)($qbIdx !== false ? ($row[$qbIdx] ?? 1) : 1),
                'comment' => $cmIdx !== false ? (string)($row[$cmIdx] ?? '') : null,
            ];

            try {
                // Upsert by (category_id, name) — matches your unique constraint
                $existing = Product::query()
                    ->where('category_id', $categoryId)
                    ->where('name', $name)
                    ->first();

                if ($existing) {
                    $existing->update($data);
                    $updated++;
                } else {
                    Product::create($data);
                    $created++;
                }
            } catch (\Throwable $e) {
                $errors++;
            }
        }

        fclose($fh);

        return redirect()
            ->route('products.index')
            ->with('status', "Import completed. Created: {$created}, Updated: {$updated}, Skipped: {$skipped}, Errors: {$errors}");
    }

    private function validateProduct(Request $request, ?int $ignoreId = null): array
    {
        // IMPORTANT: use Rule::unique() instead of building a string rule.
        // We enforce: name is unique inside the selected category.
        $categoryId = $request->input('category_id');

        return $request->validate([
            'category_id' => ['required', 'integer', 'exists:product_categories,id'],

            'name' => [
                'required',
                'string',
                'max:150',
                Rule::unique('products', 'name')
                    ->where(fn($q) => $q->where('category_id', $categoryId))
                    ->ignore($ignoreId),
            ],

            'purchase_price'       => ['required', 'numeric', 'min:0'],
            'purchase_vat_type_id' => ['required', 'integer', 'exists:vat_types,id'],

            'sell_price'       => ['required', 'numeric', 'min:0'],
            'sell_vat_type_id' => ['required', 'integer', 'exists:vat_types,id'],

            'quantity_stock'  => ['required', 'integer', 'min:0'],
            'quantity_in_box' => ['required', 'integer', 'min:1'],

            'comment' => ['nullable', 'string'],
        ]);
    }

    private function categoryToId(string $raw, $categoriesByName): ?int
    {
        if ($raw === '') return null;

        if (ctype_digit($raw)) {
            return (int)$raw;
        }

        $cat = $categoriesByName[mb_strtolower(trim($raw))] ?? null;
        return $cat?->id;
    }

    private function vatToId(string $raw, $vatByName): ?int
    {
        if ($raw === '') return null;

        if (ctype_digit($raw)) {
            return (int)$raw;
        }

        $v = $vatByName[mb_strtolower(trim($raw))] ?? null;
        return $v?->id;
    }
}
