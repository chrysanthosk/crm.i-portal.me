<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SupplierController extends Controller
{
    public function index(Request $request)
    {
        $suppliers = Supplier::query()
            ->orderByDesc('created_at')
            ->get();

        return view('suppliers.index', [
            'suppliers' => $suppliers,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateSupplier($request);

        Supplier::create($data);

        return redirect()
            ->route('suppliers.index')
            ->with('status', 'Supplier added successfully.');
    }

    public function update(Request $request, Supplier $supplier)
    {
        $data = $this->validateSupplier($request);

        $supplier->update($data);

        return redirect()
            ->route('suppliers.index')
            ->with('status', 'Supplier updated successfully.');
    }

    public function destroy(Supplier $supplier)
    {
        $supplier->delete();

        return redirect()
            ->route('suppliers.index')
            ->with('status', 'Supplier deleted successfully.');
    }

    /**
     * Export CSV
     */
    public function export(): StreamedResponse
    {
        $filename = 'suppliers_' . now()->format('Ymd_His') . '.csv';

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $suppliers = Supplier::query()->orderBy('name')->get();

        return response()->stream(function () use ($suppliers) {
            $out = fopen('php://output', 'w');

            // UTF-8 BOM for Excel
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, ['name', 'type', 'mobile', 'phone', 'email', 'comment']);

            foreach ($suppliers as $s) {
                fputcsv($out, [
                    $s->name,
                    $s->type,
                    $s->mobile,
                    $s->phone,
                    $s->email,
                    $s->comment,
                ]);
            }

            fclose($out);
        }, 200, $headers);
    }

    /**
     * Download CSV template
     */
    public function template(): StreamedResponse
    {
        $filename = 'suppliers_import_template.csv';

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        return response()->stream(function () {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, ['name', 'type', 'mobile', 'phone', 'email', 'comment']);
            fputcsv($out, ['ACME Supplies', 'Consumables', '+35799999999', '+35722222222', 'sales@acme.com', 'Optional notes']);

            fclose($out);
        }, 200, $headers);
    }

    /**
     * Import CSV (expects header row: name,type,mobile,phone,email,comment)
     */
    public function import(Request $request)
    {
        $request->validate([
            'csv_file' => ['required', 'file', 'mimes:csv,txt', 'max:20480'],
        ]);

        $path = $request->file('csv_file')->getRealPath();
        $handle = fopen($path, 'r');

        if (!$handle) {
            return redirect()->route('suppliers.index')->with('error', 'Could not read the uploaded file.');
        }

        $header = fgetcsv($handle);
        if (!$header) {
            fclose($handle);
            return redirect()->route('suppliers.index')->with('error', 'CSV file is empty.');
        }

        // Normalize header
        $header = array_map(fn($h) => strtolower(trim((string)$h)), $header);

        $required = ['name', 'type'];
        foreach ($required as $col) {
            if (!in_array($col, $header, true)) {
                fclose($handle);
                return redirect()->route('suppliers.index')->with('error', "Missing required column: {$col}");
            }
        }

        $colIndex = array_flip($header);

        $created = 0;
        $skipped = 0;
        $errors  = 0;

        while (($row = fgetcsv($handle)) !== false) {
            // Skip completely empty rows
            if (count(array_filter($row, fn($v) => trim((string)$v) !== '')) === 0) {
                continue;
            }

            $name = trim((string)($row[$colIndex['name']] ?? ''));
            $type = trim((string)($row[$colIndex['type']] ?? ''));

            $mobile  = trim((string)($row[$colIndex['mobile']] ?? ''));
            $phone   = trim((string)($row[$colIndex['phone']] ?? ''));
            $email   = trim((string)($row[$colIndex['email']] ?? ''));
            $comment = trim((string)($row[$colIndex['comment']] ?? ''));

            if ($name === '' || $type === '') {
                $skipped++;
                continue;
            }

            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors++;
                continue;
            }

            Supplier::create([
                'name' => $name,
                'type' => $type,
                'mobile' => $mobile !== '' ? $mobile : null,
                'phone' => $phone !== '' ? $phone : null,
                'email' => $email !== '' ? $email : null,
                'comment' => $comment !== '' ? $comment : null,
            ]);

            $created++;
        }

        fclose($handle);

        $msg = "Import complete. Created: {$created}";
        if ($skipped) $msg .= ", Skipped: {$skipped}";
        if ($errors)  $msg .= ", Invalid rows: {$errors}";

        return redirect()->route('suppliers.index')->with('status', $msg);
    }

    private function validateSupplier(Request $request): array
    {
        return $request->validate([
            'name'    => ['required', 'string', 'max:255'],
            'type'    => ['required', 'string', 'max:100'],
            'mobile'  => ['nullable', 'string', 'max:255'],
            'phone'   => ['nullable', 'string', 'max:50'],
            'email'   => ['nullable', 'email', 'max:255'],
            'comment' => ['nullable', 'string'],
        ]);
    }
}
