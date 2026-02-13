<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\VatType;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    public function index()
    {
        $products = Product::query()
            ->with(['category', 'purchaseVatType', 'sellVatType'])
            ->orderByDesc('id')
            ->paginate(25);

        $categories = ProductCategory::query()->orderBy('name')->get();
        $vatTypes = VatType::query()->orderBy('vat_percent')->get();

        return view('products.index', compact('products', 'categories', 'vatTypes'));
    }

    public function create()
    {
        $categories = ProductCategory::query()->orderBy('name')->get();
        $vatTypes = VatType::query()->orderBy('vat_percent')->get();

        return view('products.create', compact('categories', 'vatTypes'));
    }

    public function store(Request $request)
    {
        $data = $this->validateProduct($request);
        Product::create($data);

        return redirect()->route('products.index')->with('status', 'Product created.');
    }

    public function edit(Product $product)
    {
        $categories = ProductCategory::query()->orderBy('name')->get();
        $vatTypes = VatType::query()->orderBy('vat_percent')->get();

        return view('products.edit', compact('product', 'categories', 'vatTypes'));
    }

    public function update(Request $request, Product $product)
    {
        $data = $this->validateProduct($request, $product->id);
        $product->update($data);

        return redirect()->route('products.index')->with('status', 'Product updated.');
    }

    public function destroy(Product $product)
    {
        $product->delete();
        return redirect()->route('products.index')->with('status', 'Product deleted.');
    }

    private function validateProduct(Request $request, ?int $ignoreId = null): array
    {
        $rules = [
            'category_id' => ['required','integer','exists:product_categories,id'],
            'name' => ['required','string','max:150'],
            'purchase_price' => ['required','numeric','min:0'],
            'purchase_vat_type_id' => ['required','integer','exists:vat_types,id'],
            'sell_price' => ['required','numeric','min:0'],
            'sell_vat_type_id' => ['required','integer','exists:vat_types,id'],
            'quantity_stock' => ['required','integer'],
            'quantity_in_box' => ['required','integer','min:1'],
            'comment' => ['nullable','string'],
        ];

        $rules['name'][] = Rule::unique('products', 'name')
            ->where(fn($q) => $q->where('category_id', $request->input('category_id')))
            ->ignore($ignoreId);

        return $request->validate($rules);
    }
}
