<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\ClientRequest;
use App\Jobs\ImportClientsJob;
use App\Models\Client;
use App\Models\ImportLog;
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

    public function store(ClientRequest $request)
    {
        $data = $request->validated();

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

    public function update(ClientRequest $request, Client $client)
    {
        $data = $request->validated();

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
     * Import clients from CSV — stores the file and dispatches a background job.
     */
    public function import(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ]);

        $file     = $request->file('file');
        $stored   = $file->store('imports/clients');

        $log = ImportLog::create([
            'type'              => 'clients',
            'status'            => 'pending',
            'original_filename' => $file->getClientOriginalName(),
            'stored_path'       => $stored,
            'user_id'           => $request->user()?->id,
        ]);

        ImportClientsJob::dispatch($log->id);

        Audit::log('app', 'client.import', 'client', 0, ['import_log_id' => $log->id]);

        return redirect()->route('clients.index')
            ->with('status', 'Client import queued. The file is being processed in the background.');
    }

}
