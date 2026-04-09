<?php

namespace App\Http\Controllers;

use App\Jobs\ImportSuppliersJob;
use App\Models\ImportLog;
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
     * Import CSV — stores the file and dispatches a background job.
     */
    public function import(Request $request)
    {
        $request->validate([
            'csv_file' => ['required', 'file', 'mimes:csv,txt', 'max:20480'],
        ]);

        $file   = $request->file('csv_file');
        $stored = $file->store('imports/suppliers');

        $log = ImportLog::create([
            'type'              => 'suppliers',
            'status'            => 'pending',
            'original_filename' => $file->getClientOriginalName(),
            'stored_path'       => $stored,
            'user_id'           => $request->user()?->id,
        ]);

        ImportSuppliersJob::dispatch($log->id);

        return redirect()->route('suppliers.index')
            ->with('status', 'Supplier import queued. The file is being processed in the background.');
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
