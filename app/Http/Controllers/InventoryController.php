<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class InventoryController extends Controller
{
    private string $categoryTable;

    public function __construct()
    {
        // Support both possible legacy table names
        $this->categoryTable = Schema::hasTable('product_categories') ? 'product_categories' : 'product_category';
    }

    public function index()
    {
        $alertThreshold = 5;

        $lowCount = DB::table('products')
            ->where('quantity_stock', '<', $alertThreshold)
            ->count();

        $products = DB::table('products as p')
            ->select([
                'p.id',
                'p.category_id',
                DB::raw('c.name as category'),
                'p.name',
                'p.quantity_stock',
                'p.quantity_in_box',
                'p.purchase_price',
                'p.purchase_vat_type_id',
                DB::raw('pv.name as purchase_vat'),
                'p.sell_price',
                'p.sell_vat_type_id',
                DB::raw('sv.name as sell_vat'),
                'p.comment',
            ])
            ->join($this->categoryTable . ' as c', 'p.category_id', '=', 'c.id')
            ->leftJoin('vat_types as pv', 'p.purchase_vat_type_id', '=', 'pv.id')
            ->leftJoin('vat_types as sv', 'p.sell_vat_type_id', '=', 'sv.id')
            ->orderBy('c.name')
            ->orderBy('p.name')
            ->get();

        $cats = DB::table($this->categoryTable)
            ->select(['id', 'name'])
            ->orderBy('name')
            ->get();

        $vats = DB::table('vat_types')
            ->select(['id', 'name'])
            ->orderBy('id')
            ->get();

        return view('inventory.index', [
            'alertThreshold' => $alertThreshold,
            'lowCount' => $lowCount,
            'products' => $products,
            'cats' => $cats,
            'vats' => $vats,
        ]);
    }

    public function save(Request $request)
    {
        $categoryTable = $this->categoryTable;

        $data = $request->validate([
            'id' => ['nullable', 'integer'],
            'category_id' => ['required', 'integer', Rule::exists($categoryTable, 'id')],
            'name' => ['required', 'string', 'max:190'],
            'quantity_stock' => ['required', 'integer', 'min:0'],
            'quantity_in_box' => ['required', 'integer', 'min:1'],
            'purchase_price' => ['required', 'numeric', 'min:0'],
            'purchase_vat_type_id' => ['required', 'integer', Rule::exists('vat_types', 'id')],
            'sell_price' => ['required', 'numeric', 'min:0'],
            'sell_vat_type_id' => ['required', 'integer', Rule::exists('vat_types', 'id')],
            'comment' => ['nullable', 'string'],
        ]);

        $id = (int)($data['id'] ?? 0);
        unset($data['id']);

        if ($id > 0) {
            $product = Product::query()->findOrFail($id);
            $product->update($data);

            Audit::log('app', 'inventory.product.update', 'product', $product->id, [
                'name' => $product->name,
                'category_id' => $product->category_id,
            ]);

            return redirect()->route('inventory.index')->with('status', 'Product updated.');
        }

        $product = Product::create($data);

        Audit::log('app', 'inventory.product.create', 'product', $product->id, [
            'name' => $product->name,
            'category_id' => $product->category_id,
        ]);

        return redirect()->route('inventory.index')->with('status', 'Product created.');
    }
}
