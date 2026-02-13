<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Models\ProductCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductCategoryController extends Controller
{
    public function index()
    {
        // Blade expects $categories and uses pagination links
        $categories = ProductCategory::query()
            ->orderBy('name')
            ->paginate(50);

        return view('settings.product_categories.index', [
            'categories' => $categories,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:product_categories,name'],
        ]);

        ProductCategory::create($data);

        return redirect()
            ->route('settings.product-categories.index')
            ->with('status', 'Product category created successfully.');
    }

    public function update(Request $request, ProductCategory $productCategory)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:product_categories,name,' . $productCategory->id],
        ]);

        $productCategory->update($data);

        return redirect()
            ->route('settings.product-categories.index')
            ->with('status', 'Product category updated successfully.');
    }

    public function destroy(ProductCategory $productCategory)
    {
        // Better UX than a DB FK error
        if ($productCategory->products()->exists()) {
            return redirect()
                ->route('settings.product-categories.index')
                ->with('error', 'Cannot delete category because products exist in this category.');
        }

        $productCategory->delete();

        return redirect()
            ->route('settings.product-categories.index')
            ->with('status', 'Product category deleted successfully.');
    }

    /**
     * GET settings/product-categories/export
     */
    public function export()
    {
        $rows = ProductCategory::query()
            ->orderBy('name')
            ->get(['name', 'created_at', 'updated_at']);

        $filename = 'product_categories_' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');

            // Header
            fputcsv($out, ['name', 'created_at', 'updated_at']);

            foreach ($rows as $r) {
                fputcsv($out, [
                    (string) $r->name,
                    optional($r->created_at)->toDateTimeString(),
                    optional($r->updated_at)->toDateTimeString(),
                ]);
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * GET settings/product-categories/template
     */
    public function template()
    {
        $filename = 'product_categories_template.csv';

        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['name']);
            fputcsv($out, ['Supplements']);
            fputcsv($out, ['Cosmetics']);
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * POST settings/product-categories/import
     */
    public function import(Request $request)
    {
        $request->validate([
            // More tolerant than mimes:csv,txt (which is often inconsistent)
            'csv' => ['required', 'file', 'max:5120', 'mimetypes:text/plain,text/csv,application/csv,application/vnd.ms-excel'],
        ]);

        $file = $request->file('csv');
        if (!$file || !$file->isValid()) {
            return back()->with('error', 'Invalid upload. Please try again.');
        }

        $path = $file->getRealPath();
        $fh = fopen($path, 'r');

        if ($fh === false) {
            return back()->with('error', 'Could not read the uploaded file.');
        }

        // Read header row
        $header = fgetcsv($fh);
        if (!$header || !is_array($header)) {
            fclose($fh);
            return back()->with('error', 'CSV file is empty or missing a header row.');
        }

        // Normalize header values + strip UTF-8 BOM from first header cell (Excel)
        $header = array_map(function ($h) {
            $h = trim((string)$h);
            $h = preg_replace('/^\xEF\xBB\xBF/', '', $h); // BOM
            return strtolower($h);
        }, $header);

        $nameIdx = array_search('name', $header, true);
        if ($nameIdx === false) {
            fclose($fh);
            return back()->with('error', 'CSV header must include: name');
        }

        $created = 0;
        $skipped = 0;
        $errors  = 0;

        DB::beginTransaction();

        try {
            while (($row = fgetcsv($fh)) !== false) {
                $name = trim((string)($row[$nameIdx] ?? ''));

                if ($name === '') {
                    $skipped++;
                    continue;
                }

                // Optional: avoid case-only duplicates (recommended)
                $exists = ProductCategory::query()
                    ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
                    ->exists();

                if ($exists) {
                    $skipped++;
                    continue;
                }

                try {
                    ProductCategory::create(['name' => $name]);
                    $created++;
                } catch (\Throwable $e) {
                    $errors++;
                }
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            fclose($fh);

            return redirect()
                ->route('settings.product-categories.index')
                ->with('error', 'Import failed unexpectedly. Please check the CSV format and try again.');
        }

        fclose($fh);

        return redirect()
            ->route('settings.product-categories.index')
            ->with('status', "Import completed. Created: {$created}, Skipped: {$skipped}, Errors: {$errors}");
    }
}
