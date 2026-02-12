<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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
        $data = $request->validate([
            'registration_date' => ['nullable', 'date'],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name'  => ['required', 'string', 'max:100'],
            'dob'        => ['required', 'date'],
            'mobile'     => ['required', 'string', 'max:20'],
            'email'      => ['required', 'email', 'max:150'],
            'address'    => ['nullable', 'string', 'max:255'],
            'city'       => ['nullable', 'string', 'max:100'],
            'gender'     => ['required', Rule::in(['Male','Female','Other'])],
            'notes'      => ['nullable', 'string'],
            'comments'   => ['nullable', 'string'],
        ]);

        if (empty($data['registration_date'])) {
            $data['registration_date'] = now();
        }

        $client = Client::create($data);

        Audit::log('admin', 'client.create', 'client', $client->id, [
            'email' => $client->email,
            'mobile' => $client->mobile,
            'name' => $client->first_name . ' ' . $client->last_name,
        ]);

        return redirect()->route('clients.index')->with('status', 'Client created.');
    }

    public function edit(Client $client)
    {
        return view('clients.edit', compact('client'));
    }

    public function update(Request $request, Client $client)
    {
        $data = $request->validate([
            'registration_date' => ['nullable', 'date'],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name'  => ['required', 'string', 'max:100'],
            'dob'        => ['required', 'date'],
            'mobile'     => ['required', 'string', 'max:20'],
            'email'      => ['required', 'email', 'max:150'],
            'address'    => ['nullable', 'string', 'max:255'],
            'city'       => ['nullable', 'string', 'max:100'],
            'gender'     => ['required', Rule::in(['Male','Female','Other'])],
            'notes'      => ['nullable', 'string'],
            'comments'   => ['nullable', 'string'],
        ]);

        if (empty($data['registration_date'])) {
            $data['registration_date'] = $client->registration_date ?? now();
        }

        $client->update($data);

        Audit::log('admin', 'client.update', 'client', $client->id, [
            'email' => $client->email,
            'mobile' => $client->mobile,
            'name' => $client->first_name . ' ' . $client->last_name,
        ]);

        return redirect()->route('clients.index')->with('status', 'Client updated.');
    }

    public function destroy(Request $request, Client $client)
    {
        Audit::log('admin', 'client.delete', 'client', $client->id, [
            'email' => $client->email,
            'mobile' => $client->mobile,
            'name' => $client->first_name . ' ' . $client->last_name,
        ]);

        $client->delete();

        return redirect()->route('clients.index')->with('status', 'Client deleted.');
    }
}
