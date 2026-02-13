@extends('layouts.app')

@section('title', 'Products')

@section('content')
<div class="container-fluid">

    <div class="d-flex align-items-center justify-content-between mb-3">
        <h1 class="h3 mb-0">Products</h1>

        <div class="d-flex">
            <a href="{{ route('products.template') }}" class="btn btn-outline-secondary mr-2">
                <i class="fas fa-download mr-1"></i> Template
            </a>

            <a href="{{ route('products.export') }}" class="btn btn-secondary mr-2">
                <i class="fas fa-file-csv mr-1"></i> Export CSV
            </a>

            <button type="button" class="btn btn-outline-primary mr-2" data-toggle="modal" data-target="#importProductsModal">
                <i class="fas fa-file-import mr-1"></i> Import CSV
            </button>

            <a href="{{ route('products.create') }}" class="btn btn-success">
                <i class="fas fa-plus-circle mr-1"></i> Add Product
            </a>
        </div>
    </div>
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

{{-- Import Products Modal --}}
<div class="modal fade" id="importProductsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('products.import') }}" enctype="multipart/form-data" class="modal-content">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title">Import Products (CSV)</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>

            <div class="modal-body">
                <div class="text-muted small mb-2">
                    Use the template for correct headers. Category and VAT can be <strong>name</strong> or <strong>id</strong>.
                </div>

                <input type="file" name="csv" class="form-control" accept=".csv,text/csv" required>
                @error('csv') <div class="text-danger small mt-2">{{ $message }}</div> @enderror
            </div>

            <div class="modal-footer">
                <button class="btn btn-primary">
                    <i class="fas fa-file-import mr-1"></i> Import
                </button>
                <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancel</button>
            </div>
        </form>
    </div>
</div>

{{-- Auto-open Import modal if CSV validation failed --}}
@if ($errors->has('csv'))
    @push('scripts')
        <script>
            $(function () {
                $('#importProductsModal').modal('show');
            });
        </script>
    @endpush
@endif

@endsection
