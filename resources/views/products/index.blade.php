@extends('layouts.app')

@section('title', 'Products')

@section('content')
<div class="container-fluid">

    <div class="d-flex align-items-center justify-content-between mb-3">
        <h1 class="h3 mb-0">Products</h1>
        <a href="{{ route('products.create') }}" class="btn btn-success">
            <i class="fas fa-plus-circle mr-1"></i> Add Product
        </a>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger">Please fix the errors below.</div>
    @endif

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="card">
        <div class="card-header"><strong>Product List</strong></div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover mb-0 align-middle">
                    <thead>
                        <tr>
                            <th style="width:70px;">#</th>
                            <th>Name</th>
                            <th style="width:180px;">Category</th>
                            <th style="width:120px;">Buy</th>
                            <th style="width:140px;">Buy VAT</th>
                            <th style="width:120px;">Sell</th>
                            <th style="width:140px;">Sell VAT</th>
                            <th style="width:110px;">Stock</th>
                            <th style="width:120px;">Box Qty</th>
                            <th>Comment</th>
                            <th style="width:160px;" class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($products as $p)
                        <tr>
                            <td>{{ $p->id }}</td>
                            <td class="fw-bold">{{ $p->name }}</td>
                            <td>{{ $p->category?->name ?? '—' }}</td>

                            <td>€ {{ number_format((float)$p->purchase_price, 2) }}</td>
                            <td>{{ $p->purchaseVatType?->name ?? '—' }}</td>

                            <td>€ {{ number_format((float)$p->sell_price, 2) }}</td>
                            <td>{{ $p->sellVatType?->name ?? '—' }}</td>

                            <td>{{ (int)$p->quantity_stock }}</td>
                            <td>{{ (int)$p->quantity_in_box }}</td>

                            <td class="text-muted">{{ $p->comment }}</td>

                            <td class="text-end">
                                <a href="{{ route('products.edit', $p) }}" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-edit"></i>
                                </a>

                                <form method="POST" action="{{ route('products.destroy', $p) }}"
                                      class="d-inline"
                                      onsubmit="return confirm('Delete this product?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="11" class="text-center text-muted p-4">No products found.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if(method_exists($products, 'links'))
            <div class="card-footer">{{ $products->links() }}</div>
        @endif
    </div>

</div>
@endsection
