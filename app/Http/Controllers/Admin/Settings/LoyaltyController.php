<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LoyaltyController extends Controller
{
    public function index(Request $request)
    {
        $tiers = DB::table('loyalty_tiers')
            ->orderBy('sort_order')
            ->orderBy('points_min')
            ->get();

        $clients = DB::table('clients as c')
            ->leftJoin('client_loyalty as cl', 'cl.client_id', '=', 'c.id')
            ->select([
                'c.id',
                DB::raw("TRIM(COALESCE(c.first_name,'') || ' ' || COALESCE(c.last_name,'')) as client_name"),
                DB::raw("COALESCE(cl.points_balance,0) as points"),
            ])
            ->orderByDesc('points')
            ->orderBy('client_name')
            ->get();

        // tier name per client (computed in PHP to stay SQLite-friendly)
        $tiersArr = $tiers->map(fn($t) => [
            'name' => $t->name,
            'points_min' => (int)$t->points_min,
        ])->values()->all();

        $clients = $clients->map(function ($c) use ($tiersArr) {
            $tierName = null;
            foreach ($tiersArr as $t) {
                if ((int)$c->points >= (int)$t['points_min']) {
                    $tierName = $t['name'];
                }
            }
            $c->tier = $tierName ?: 'Unranked';
            return $c;
        });

        $pointsPerEuro = DB::table('loyalty_settings')->where('key', 'points_per_euro')->value('value');
        if ($pointsPerEuro === null) $pointsPerEuro = '1';

        return view('admin.settings.loyalty.index', [
            'tiers' => $tiers,
            'clients' => $clients,
            'pointsPerEuro' => $pointsPerEuro,
        ]);
    }

    public function saveSettings(Request $request)
    {
        $data = $request->validate([
            'points_per_euro' => ['required', 'numeric', 'min:0'],
        ]);

        DB::table('loyalty_settings')->updateOrInsert(
            ['key' => 'points_per_euro'],
            ['value' => (string)$data['points_per_euro']]
        );

        return back()->with('status', 'Loyalty settings saved.');
    }

    public function saveTier(Request $request)
    {
        $data = $request->validate([
            'id' => ['nullable', 'integer'],
            'name' => ['required', 'string', 'max:50'],
            'points_min' => ['required', 'integer', 'min:0'],
            'benefits' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        $payload = [
            'name' => trim($data['name']),
            'points_min' => (int)$data['points_min'],
            'benefits' => $data['benefits'] ?? null,
            'sort_order' => (int)($data['sort_order'] ?? 0),
            'updated_at' => now(),
        ];

        if (!empty($data['id'])) {
            DB::table('loyalty_tiers')->where('id', $data['id'])->update($payload);
            return back()->with('status', 'Tier updated.');
        }

        $payload['created_at'] = now();
        DB::table('loyalty_tiers')->insert($payload);
        return back()->with('status', 'Tier created.');
    }

    public function deleteTier($tier)
    {
        DB::table('loyalty_tiers')->where('id', $tier)->delete();
        return back()->with('status', 'Tier deleted.');
    }

    public function adjust(Request $request)
    {
        $data = $request->validate([
            'client_id' => ['required', 'integer'],
            'change' => ['required', 'integer'],
            'reason' => ['required', 'string', 'max:100'],
        ]);

        $clientId = (int)$data['client_id'];
        $change = (int)$data['change'];
        $reason = trim($data['reason']);

        DB::transaction(function () use ($clientId, $change, $reason) {

            DB::table('client_loyalty')->updateOrInsert(
                ['client_id' => $clientId],
                ['points_balance' => DB::raw('COALESCE(points_balance,0)'), 'updated_at' => now()]
            );

            DB::table('client_loyalty')->where('client_id', $clientId)->update([
                'points_balance' => DB::raw('COALESCE(points_balance,0) + ' . $change),
                'updated_at' => now(),
            ]);

            DB::table('loyalty_transactions')->insert([
                'client_id' => $clientId,
                'change' => $change,
                'reason' => $reason,
                'reference_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        return back()->with('status', 'Points adjusted.');
    }
}
