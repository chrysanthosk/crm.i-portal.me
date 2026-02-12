<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Models\VatType;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class VatTypeController extends Controller
{
    public function index()
    {
        $vatTypes = VatType::query()
            ->orderBy('vat_percent')
            ->orderBy('name')
            ->paginate(25);

        return view('settings.vat_types.index', compact('vatTypes'));
    }

    public function create()
    {
        return view('settings.vat_types.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150', 'unique:vat_types,name'],
            'vat_percent' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        VatType::create([
            'name' => $data['name'],
            'vat_percent' => number_format((float)$data['vat_percent'], 2, '.', ''),
        ]);

        return redirect()->route('settings.vat-types.index')
            ->with('status', 'VAT type created.');
    }

    public function edit(VatType $vat_type)
    {
        return view('settings.vat_types.edit', [
            'vatType' => $vat_type,
        ]);
    }

    public function update(Request $request, VatType $vat_type)
    {
        $data = $request->validate([
            'name' => [
                'required', 'string', 'max:150',
                Rule::unique('vat_types', 'name')->ignore($vat_type->id),
            ],
            'vat_percent' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        $vat_type->update([
            'name' => $data['name'],
            'vat_percent' => number_format((float)$data['vat_percent'], 2, '.', ''),
        ]);

        return redirect()->route('settings.vat-types.index')
            ->with('status', 'VAT type updated.');
    }

    public function destroy(VatType $vat_type)
    {
        $vat_type->delete();

        return redirect()->route('settings.vat-types.index')
            ->with('status', 'VAT type deleted.');
    }
}
