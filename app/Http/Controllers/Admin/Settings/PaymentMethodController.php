<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentMethodController extends Controller
{
    public function index()
    {
        $methods = DB::table('payment_methods')
            ->orderBy('name')
            ->get();

        return view('admin.settings.payment_methods.index', compact('methods'));
    }

    public function create()
    {
        return view('admin.settings.payment_methods.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
        ]);

        // unique check (SQLite-friendly)
        $exists = DB::table('payment_methods')->whereRaw('LOWER(name) = ?', [mb_strtolower($data['name'])])->exists();
        if ($exists) {
            return back()->withInput()->with('error', 'Payment method already exists.');
        }

        DB::table('payment_methods')->insert([
            'name' => trim($data['name']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->route('settings.payment-methods.index')->with('status', 'Payment method created.');
    }

    public function edit($id)
    {
        $method = DB::table('payment_methods')->where('id', $id)->first();
        abort_if(!$method, 404);

        return view('admin.settings.payment_methods.edit', compact('method'));
    }

    public function update(Request $request, $id)
    {
        $method = DB::table('payment_methods')->where('id', $id)->first();
        abort_if(!$method, 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
        ]);

        $exists = DB::table('payment_methods')
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($data['name'])])
            ->where('id', '!=', $id)
            ->exists();

        if ($exists) {
            return back()->withInput()->with('error', 'Payment method already exists.');
        }

        DB::table('payment_methods')->where('id', $id)->update([
            'name' => trim($data['name']),
            'updated_at' => now(),
        ]);

        return redirect()->route('settings.payment-methods.index')->with('status', 'Payment method updated.');
    }

    public function destroy($id)
    {
        $method = DB::table('payment_methods')->where('id', $id)->first();
        abort_if(!$method, 404);

        DB::table('payment_methods')->where('id', $id)->delete();

        return redirect()->route('settings.payment-methods.index')->with('status', 'Payment method deleted.');
    }
}
