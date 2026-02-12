<?php

namespace App\Http\Controllers;

use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\VatType;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use PhpOffice\PhpSpreadsheet\IOFactory;
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
     * Download CSV template
     * columns:
     * category,name,gender,price,vat,duration,waiting,comment
     */
    public function downloadTemplate(): StreamedResponse
    {
        $filename = 'services_import_template.csv';

        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['category', 'name', 'gender', 'price', 'vat', 'duration', 'waiting', 'comment']);
            fputcsv($out, ['Physio', 'Massage 30', 'Both', '30.00', '19', '30', '0', 'Example row']);
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Import CSV/XLSX/XLS
     * - category matched by name (case-insensitive)
     * - vat matched by percent or vat_type name (case-insensitive)
     * - gender normalized to Male/Female/Both, default Both
     * - duplicates skipped by (name, category_id, gender) to match your unique key
     */
    public function import(Request $request)
    {
        $request->validate([
            'import_file' => ['required', 'file', 'max:10240'], // 10MB
        ]);

        $file = $request->file('import_file');
        $ext  = strtolower($file->getClientOriginalExtension());

        if (!in_array($ext, ['csv', 'xlsx', 'xls'], true)) {
            return back()->withErrors(['import_file' => 'Please upload a CSV, XLSX, or XLS file.']);
        }

        // Build lookup maps
        $categories = ServiceCategory::query()->select('id', 'name')->get();
        $catMap = [];
        foreach ($categories as $c) {
            $catMap[mb_strtolower(trim($c->name))] = (int)$c->id;
        }

        $vatTypes = VatType::query()->select('id', 'name', 'vat_percent')->get();
        $vatPercentMap = [];
        $vatNameMap = [];
        foreach ($vatTypes as $v) {
            $vatPercentMap[number_format((float)$v->vat_percent, 2)] = (int)$v->id;
            $vatNameMap[mb_strtolower(trim($v->name))] = (int)$v->id;
        }

        $inserted = 0;
        $skipped  = 0;
        $total    = 0;

        // Helper: normalize gender
        $normalizeGender = function (?string $raw): string {
            $raw = (string)$raw;
            $lettersOnly = mb_strtolower(preg_replace('/[^a-z]/i', '', $raw));
            if ($lettersOnly === 'male') return 'Male';
            if ($lettersOnly === 'female') return 'Female';
            if ($lettersOnly === 'both') return 'Both';
            return 'Both';
        };

        // Read rows
        $rows = [];

        if ($ext === 'csv') {
            $handle = fopen($file->getRealPath(), 'r');
            if (!$handle) {
                return back()->withErrors(['import_file' => 'Could not open uploaded file.']);
            }

            // discard header
            fgetcsv($handle);

            while (($r = fgetcsv($handle)) !== false) {
                $rows[] = $r;
            }
            fclose($handle);
        } else {
            $spreadsheet = IOFactory::load($file->getRealPath());
            $sheet = $spreadsheet->getActiveSheet();
            $arr = $sheet->toArray(null, true, true, true);

            foreach ($arr as $index => $row) {
                if ($index === 1) continue; // header
                $rows[] = [
                    $row['A'] ?? '', // category
                    $row['B'] ?? '', // name
                    $row['C'] ?? '', // gender
                    $row['D'] ?? '', // price
                    $row['E'] ?? '', // vat
                    $row['F'] ?? '', // duration
                    $row['G'] ?? '', // waiting
                    $row['H'] ?? '', // comment
                ];
            }
        }

        foreach ($rows as $r) {
            $total++;

            // Ensure indexes
            for ($i=0; $i<8; $i++) {
                if (!isset($r[$i])) $r[$i] = '';
            }

            [$rawCat, $name, $rawGender, $rawPrice, $rawVat, $rawDur, $rawWait, $comment] = array_map(
                fn($x) => is_string($x) ? trim($x) : $x,
                $r
            );

            if ($rawCat === '' || $name === '') {
                $skipped++;
                continue;
            }

            // Category lookup
            $catKey = mb_strtolower($rawCat);
            if (!isset($catMap[$catKey])) {
                $skipped++;
                continue;
            }
            $category_id = $catMap[$catKey];

            // Gender normalize
            $gender = $normalizeGender($rawGender);

            // Price
            $price = is_numeric($rawPrice) ? (float)$rawPrice : 0.00;

            // VAT lookup by percent or name
            $vat_type_id = 0;
            if (is_numeric($rawVat)) {
                $key = number_format((float)$rawVat, 2);
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

            // Duration / waiting
            $duration = is_numeric($rawDur) ? (int)$rawDur : 0;
            $waiting  = is_numeric($rawWait) ? (int)$rawWait : 0;

            // Duplicate check by your unique key: (name, category_id, gender)
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
                'name' => $name,
                'category_id' => $category_id,
                'price' => $price,
                'vat_type_id' => $vat_type_id,
                'duration' => $duration,
                'waiting' => $waiting,
                'gender' => $gender,
                'comment' => $comment !== '' ? $comment : null,
            ]);

            $inserted++;
        }

        return redirect()
            ->route('services.index')
            ->with('status', "Import complete: {$inserted} added, {$skipped} skipped (rows: {$total}).");
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
