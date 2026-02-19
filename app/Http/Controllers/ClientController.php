<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ClientController extends Controller
{
    public function index()
    {
        $clients = Client::query()->orderByDesc('id')->paginate(20);
        return view('clients.index', compact('clients'));
    }

    public function create()
    {
        return view('clients.create');
    }

    public function store(Request $request)
    {
        $data = $this->validateClient($request);

        if (empty($data['registration_date'])) {
            $data['registration_date'] = now();
        }

        $client = Client::create($data);

        Audit::log('app', 'client.create', 'client', $client->id, [
            'email'  => $client->email,
            'mobile' => $client->mobile,
            'name'   => $client->first_name . ' ' . $client->last_name,
        ]);

        return redirect()->route('clients.index')->with('status', 'Client created.');
    }

    public function edit(Client $client)
    {
        return view('clients.edit', compact('client'));
    }

    public function update(Request $request, Client $client)
    {
        $data = $this->validateClient($request);

        if (empty($data['registration_date'])) {
            $data['registration_date'] = $client->registration_date ?? now();
        }

        $client->update($data);

        Audit::log('app', 'client.update', 'client', $client->id, [
            'email'  => $client->email,
            'mobile' => $client->mobile,
            'name'   => $client->first_name . ' ' . $client->last_name,
        ]);

        return redirect()->route('clients.index')->with('status', 'Client updated.');
    }

    public function destroy(Request $request, Client $client)
    {
        Audit::log('app', 'client.delete', 'client', $client->id, [
            'email'  => $client->email,
            'mobile' => $client->mobile,
            'name'   => $client->first_name . ' ' . $client->last_name,
        ]);

        $client->delete();

        return redirect()->route('clients.index')->with('status', 'Client deleted.');
    }

    /**
     * EXPORT clients to CSV
     */
    public function export(): StreamedResponse
    {
        $filename = 'clients_export_' . now()->format('Ymd_His') . '.csv';

        $query = Client::query()->orderBy('id');

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF)); // Excel-friendly BOM

            fputcsv($out, [
                'id',
                'registration_date',
                'first_name',
                'last_name',
                'dob',
                'mobile',
                'email',
                'address',
                'city',
                'gender',
                'notes',
                'comments',
                'created_at',
                'updated_at',
            ]);

            $query->chunk(500, function ($chunk) use ($out) {
                foreach ($chunk as $c) {
                    fputcsv($out, [
                        $c->id,
                        optional($c->registration_date)->format('Y-m-d H:i:s'),
                        $c->first_name,
                        $c->last_name,
                        optional($c->dob)->format('Y-m-d'),
                        $c->mobile,
                        $c->email,
                        $c->address ?? '',
                        $c->city ?? '',
                        $c->gender,
                        $c->notes ?? '',
                        $c->comments ?? '',
                        optional($c->created_at)->format('Y-m-d H:i:s'),
                        optional($c->updated_at)->format('Y-m-d H:i:s'),
                    ]);
                }
            });

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Download CSV import template.
     */
    public function downloadTemplate()
    {
        $headers = [
            'registration_date', // YYYY-MM-DD HH:MM (optional)
            'first_name',
            'last_name',
            'dob',               // YYYY-MM-DD
            'mobile',
            'email',
            'address',
            'city',
            'gender',            // Male/Female/Other
            'notes',
            'comments',
        ];

        $example = [
            '2026-02-19 10:00',
            'John',
            'Doe',
            '1990-05-15',
            '+35799123456',
            'john.doe@example.com',
            '1 Example Street',
            'Limassol',
            'Male',
            'Some notes',
            'Some comments',
        ];

        $out = fopen('php://temp', 'w+');
        fputcsv($out, $headers);
        fputcsv($out, $example);
        rewind($out);

        $csv = stream_get_contents($out);
        fclose($out);

        $filename = 'clients_import_template.csv';

        return response($csv, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    /**
     * Import clients from CSV.
     * - Creates new clients
     * - Updates existing clients by email (if exists)
     */
    public function import(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ]);

        $path = $request->file('file')->getRealPath();
        if (!$path || !is_readable($path)) {
            return back()->with('error', 'Upload failed: file not readable.');
        }

        $handle = fopen($path, 'r');
        if (!$handle) {
            return back()->with('error', 'Upload failed: cannot open file.');
        }

        $header = null;
        $rowNum = 0;

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $errors  = [];

        // Expected headers (case-insensitive)
        $expected = [
            'registration_date',
            'first_name',
            'last_name',
            'dob',
            'mobile',
            'email',
            'address',
            'city',
            'gender',
            'notes',
            'comments',
        ];

        while (($row = fgetcsv($handle)) !== false) {
            $rowNum++;

            // skip completely empty lines
            $rowStr = implode('', array_map(fn($v) => trim((string)$v), $row));
            if ($rowStr === '') {
                continue;
            }

            // first non-empty line is header
            if ($header === null) {
                $header = array_map(fn($h) => strtolower(trim((string)$h)), $row);

                // Basic header validation: must contain required columns
                foreach (['first_name','last_name','dob','mobile','email','gender'] as $req) {
                    if (!in_array($req, $header, true)) {
                        fclose($handle);
                        return back()->with('error', "CSV header missing required column: {$req}");
                    }
                }

                continue;
            }

            // Map row to associative array based on header
            $assoc = [];
            foreach ($header as $i => $key) {
                $assoc[$key] = isset($row[$i]) ? trim((string)$row[$i]) : null;
            }

            // Normalize keys to the ones we expect (ignore unknown columns)
            $data = [];
            foreach ($expected as $k) {
                $data[$k] = $assoc[$k] ?? null;
            }

            // Normalize/clean inputs
            $data['gender'] = $data['gender'] ? ucfirst(strtolower($data['gender'])) : null;

            // Convert registration_date if provided:
            // accept "YYYY-MM-DD HH:MM" or "YYYY-MM-DDTHH:MM"
            if (!empty($data['registration_date'])) {
                $data['registration_date'] = str_replace('T', ' ', $data['registration_date']);
            } else {
                $data['registration_date'] = now();
            }

            // Validate this row with same rules as form (but allow nullable registration_date)
            $validator = validator($data, [
                'registration_date' => ['nullable', 'date'],
                'first_name'        => ['required', 'string', 'max:100'],
                'last_name'         => ['required', 'string', 'max:100'],
                'dob'               => ['required', 'date'],
                'mobile'            => ['required', 'string', 'max:20'],
                'email'             => ['required', 'email', 'max:150'],
                'address'           => ['nullable', 'string', 'max:255'],
                'city'              => ['nullable', 'string', 'max:100'],
                'gender'            => ['required', Rule::in(['Male','Female','Other'])],
                'notes'             => ['nullable', 'string'],
                'comments'          => ['nullable', 'string'],
            ]);

            if ($validator->fails()) {
                $skipped++;
                if (count($errors) < 30) {
                    $errors[] = "Row {$rowNum}: " . implode(' | ', $validator->errors()->all());
                }
                continue;
            }

            $validated = $validator->validated();

            // Upsert by email
            $existing = Client::query()->where('email', $validated['email'])->first();

            if ($existing) {
                $existing->update($validated);
                $updated++;
            } else {
                Client::create($validated);
                $created++;
            }
        }

        fclose($handle);

        Audit::log('app', 'client.import', 'client', 0, [
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
        ]);

        $msg = "Import finished. Created: {$created}, Updated: {$updated}, Skipped: {$skipped}.";
        if (!empty($errors)) {
            return redirect()->route('clients.index')
                ->with('status', $msg)
                ->with('import_errors', $errors);
        }

        return redirect()->route('clients.index')->with('status', $msg);
    }

    private function validateClient(Request $request): array
    {
        return $request->validate([
            'registration_date' => ['nullable', 'date'],
            'first_name'        => ['required', 'string', 'max:100'],
            'last_name'         => ['required', 'string', 'max:100'],
            'dob'               => ['required', 'date'],
            'mobile'            => ['required', 'string', 'max:20'],
            'email'             => ['required', 'email', 'max:150'],
            'address'           => ['nullable', 'string', 'max:255'],
            'city'              => ['nullable', 'string', 'max:100'],
            'gender'            => ['required', Rule::in(['Male','Female','Other'])],
            'notes'             => ['nullable', 'string'],
            'comments'          => ['nullable', 'string'],
        ]);
    }
}
