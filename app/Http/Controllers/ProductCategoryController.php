<?php

namespace App\Http\Controllers;

use App\Models\ProductCategory;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductCategoryController extends Controller
{
    public function index()
    {
        $categories = ProductCategory::query()->orderBy('name')->paginate(25);
        return view('product_categories.index', compact('categories'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required','string','max:100', Rule::unique('product_categories','name')],
        ]);

        ProductCategory::create($data);

        return redirect()->route('product_categories.index')->with('status', 'Product category created.');
    }

    public function update(Request $request, ProductCategory $product_category)
    {
        $data = $request->validate([
            'name' => ['required','string','max:100', Rule::unique('product_categories','name')->ignore($product_category->id)],
        ]);

        $product_category->update($data);

        return redirect()->route('product_categories.index')->with('status', 'Product category updated.');
    }

    public function destroy(ProductCategory $product_category)
    {
        $product_category->delete();
        return redirect()->route('product_categories.index')->with('status', 'Product category deleted.');
    }
}
