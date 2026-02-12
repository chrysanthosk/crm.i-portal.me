<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Models\ServiceCategory;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ServiceCategoryController extends Controller
{
    public function index()
    {
        $categories = ServiceCategory::query()
            ->orderBy('name')
            ->paginate(25);

        return view('settings.service_categories.index', compact('categories'));
    }

    public function create()
    {
        return view('settings.service_categories.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150', 'unique:service_categories,name'],
            'description' => ['nullable', 'string'],
        ]);

        ServiceCategory::create($data);

        return redirect()->route('settings.service-categories.index')
            ->with('status', 'Service category created.');
    }

    public function edit(ServiceCategory $service_category)
    {
        return view('settings.service_categories.edit', [
            'category' => $service_category,
        ]);
    }

    public function update(Request $request, ServiceCategory $service_category)
    {
        $data = $request->validate([
            'name' => [
                'required', 'string', 'max:150',
                Rule::unique('service_categories', 'name')->ignore($service_category->id),
            ],
            'description' => ['nullable', 'string'],
        ]);

        $service_category->update($data);

        return redirect()->route('settings.service-categories.index')
            ->with('status', 'Service category updated.');
    }

    public function destroy(ServiceCategory $service_category)
    {
        // If category is used by any services, this will fail if FK restrict is enabled
        $service_category->delete();

        return redirect()->route('settings.service-categories.index')
            ->with('status', 'Service category deleted.');
    }
}
