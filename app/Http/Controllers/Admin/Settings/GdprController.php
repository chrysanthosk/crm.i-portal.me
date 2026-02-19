<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Support\Audit;
use Illuminate\Http\Request;

class GdprController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string)$request->query('q', ''));

        $clients = Client::query()
            ->when($q !== '', function ($query) use ($q) {
                $like = '%' . $q . '%';
                $query->where(function ($sub) use ($like) {
                    $sub->where('first_name', 'like', $like)
                        ->orWhere('last_name', 'like', $like)
                        ->orWhere('email', 'like', $like)
                        ->orWhere('mobile', 'like', $like);
                });
            })
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return view('settings.gdpr.index', compact('clients', 'q'));
    }

    /**
     * GDPR purge = anonymize client PII (keeps relational integrity).
     */
    public function purgeClient(Request $request, Client $client)
    {
        // Extra safety check: require checkbox
        $request->validate([
            'confirm' => ['required', 'in:1'],
        ]);

        $id = (int)$client->id;

        // Use a unique, non-real email to avoid collisions if email is unique in DB.
        $placeholderEmail = "deleted+{$id}@example.invalid";

        $client->update([
            'first_name' => '[deleted]',
            'last_name'  => '[deleted]',
            'mobile'     => '',
            'email'      => $placeholderEmail,
            'address'    => '',
            'city'       => '',
            'notes'      => '',
            'comments'   => '',
            'dob'        => '1970-01-01',
            'gender'     => 'Other', // keep valid with your Rule::in(['Male','Female','Other'])
        ]);

        Audit::log('gdpr', 'client.purge', 'client', $client->id, [
            'client_id' => $client->id,
            'mode'      => 'anonymize',
        ]);

        return redirect()
            ->route('settings.gdpr.index')
            ->with('status', "Client #{$id} anonymized (GDPR purge).");
    }
}
