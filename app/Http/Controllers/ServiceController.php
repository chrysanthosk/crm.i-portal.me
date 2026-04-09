<?php

namespace App\Http\Controllers;

use App\Jobs\ImportServicesJob;
use App\Models\ImportLog;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\VatType;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ServiceController extends Controller
{
    public function index()
    {
        $services = Service::query()
            ->with(['category', 'vatType'])
            ->orderByDesc('id')
            ->paginate(25);

        $categories = ServiceCategory::query()->orderBy('name')->get();
        $vatTypes   = VatType::query()->orderBy('vat_percent')->get();

        return view('services.index', compact('services', 'categories', 'vatTypes'));
    }

    public function create()
    {
        $categories = ServiceCategory::query()->orderBy('name')->get();
        $vatTypes   = VatType::query()->orderBy('vat_percent')->get();

        return view('services.create', compact('categories', 'vatTypes'));
    }

    public function store(Request $request)
    {
        $data = $this->validateService($request);
        Service::create($data);

        return redirect()->route('services.index')->with('status', 'Service created.');
    }

    public function edit(Service $service)
    {
        $categories = ServiceCategory::query()->orderBy('name')->get();
        $vatTypes   = VatType::query()->orderBy('vat_percent')->get();

        return view('services.edit', compact('service', 'categories', 'vatTypes'));
    }

    public function update(Request $request, Service $service)
    {
        $data = $this->validateService($request, $service->id);
        $service->update($data);

        return redirect()->route('services.index')->with('status', 'Service updated.');
    }

    public function destroy(Service $service)
    {
        $service->delete();
        return redirect()->route('services.index')->with('status', 'Service deleted.');
    }

    /**
     * EXPORT Services to CSV
     */
    public function export(): StreamedResponse
    {
        $filename = 'services_export_' . now()->format('Ymd_His') . '.csv';

        $query = Service::query()
            ->with(['category', 'vatType'])
            ->orderBy('id');

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');

            // UTF-8 BOM (helps Excel)
            fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));

            // Header row
            fputcsv($out, [
                'id',
                'name',
                'category',
                'gender',
                'price',
                'vat',
                'duration',
                'waiting',
                'comment',
                'created_at',
                'updated_at',
            ]);

            $query->chunk(500, function ($chunk) use ($out) {
                foreach ($chunk as $s) {
                    fputcsv($out, [
                        $s->id,
                        $s->name,
                        $s->category?->name ?? '',
                        $s->gender,
                        number_format((float)$s->price, 2, '.', ''),
                        $s->vatType?->name ?? '',
                        (int)$s->duration,
                        (int)$s->waiting,
                        $s->comment ?? '',
                        optional($s->created_at)->format('Y-m-d H:i:s'),
                        optional($s->updated_at)->format('Y-m-d H:i:s'),
                    ]);
                }
            });

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Download CSV template
     * columns:
     * category,name,gender,price,vat,duration,waiting,comment
     */
    public function downloadTemplate(): StreamedResponse
    {
        $filename = 'services_import_template.csv';

        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($out, ['category', 'name', 'gender', 'price', 'vat', 'duration', 'waiting', 'comment']);
            fputcsv($out, ['Physio', 'Massage 30', 'Both', '30.00', '19', '30', '0', 'Example row']);
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Import CSV/XLSX/XLS — stores the file and dispatches a background job.
     */
    public function import(Request $request)
    {
        $request->validate([
            'import_file' => ['required', 'file', 'max:10240'],
        ]);

        $file = $request->file('import_file');
        $ext  = strtolower($file->getClientOriginalExtension());

        if (!in_array($ext, ['csv', 'xlsx', 'xls'], true)) {
            return back()->withErrors(['import_file' => 'Please upload a CSV, XLSX, or XLS file.']);
        }

        $stored = $file->store('imports/services');

        $log = ImportLog::create([
            'type'              => 'services',
            'status'            => 'pending',
            'original_filename' => $file->getClientOriginalName(),
            'stored_path'       => $stored,
            'user_id'           => $request->user()?->id,
        ]);

        ImportServicesJob::dispatch($log->id, $ext);

        return redirect()
            ->route('services.index')
            ->with('status', 'Service import queued. The file is being processed in the background.');
    }

    private function validateService(Request $request, ?int $ignoreId = null): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:150'],
            'category_id' => ['required', 'integer', 'exists:service_categories,id'],
            'price' => ['required', 'numeric', 'min:0'],
            'vat_type_id' => ['required', 'integer', 'exists:vat_types,id'],
            'duration' => ['required', 'integer', 'min:0'],
            'waiting' => ['required', 'integer', 'min:0'],
            'gender' => ['required', Rule::in(['Male', 'Female', 'Both'])],
            'comment' => ['nullable', 'string'],
        ];

        $rules['name'][] = Rule::unique('services', 'name')
            ->where(fn($q) => $q
                ->where('category_id', $request->input('category_id'))
                ->where('gender', $request->input('gender'))
            )
            ->ignore($ignoreId);

        return $request->validate($rules);
    }
}
