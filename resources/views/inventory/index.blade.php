@extends('layouts.app')

@section('title', 'Inventory')

@push('styles')
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap4.min.css"/>
@endpush

@section('content')
<div class="container-fluid">

    <div class="d-flex align-items-center justify-content-between mb-3">
        <h1 class="h3 mb-0">Inventory</h1>

        <button
            class="btn btn-success"
            type="button"
            data-toggle="modal"
            data-target="#productModal"
            onclick="openProductModal()"
        >
            <i class="fas fa-plus mr-2"></i> Add Product
        </button>
    </div>

    @if(!empty($lowCount))
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle mr-1"></i>
            {{ $lowCount }} product(s) low in stock (&lt; {{ $alertThreshold }})
        </div>
    @endif

    <div class="card">
        <div class="card-header">
            <strong>Products &amp; Stock</strong>
        </div>

        <div class="card-body">
            <div class="table-responsive">
                <table id="inventoryTable" class="table table-bordered table-striped">
                    <thead>
                    <tr>
                        <th>Category</th>
                        <th>Name</th>
                        <th>In Stock</th>
                        <th>Box Qty</th>
                        <th>Purchase (€ + VAT)</th>
                        <th>Sell (€ + VAT)</th>
                        <th>Comment</th>
                        <th style="width:90px;">Action</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($products as $p)
                        <tr class="{{ ((int)$p->quantity_stock < (int)$alertThreshold) ? 'table-danger' : '' }}">
                            <td>{{ $p->category }}</td>
                            <td>{{ $p->name }}</td>
                            <td>{{ (int)$p->quantity_stock }}</td>
                            <td>{{ (int)$p->quantity_in_box }}</td>
                            <td>
                                {{ number_format((float)$p->purchase_price, 2) }}
                                + {{ $p->purchase_vat ?? '-' }}
                            </td>
                            <td>
                                {{ number_format((float)$p->sell_price, 2) }}
                                + {{ $p->sell_vat ?? '-' }}
                            </td>
                            <td>{{ $p->comment }}</td>
                            <td>
                                <button
                                    class="btn btn-sm btn-info edit-btn"
                                    type="button"
                                    data-id="{{ $p->id }}"
                                    data-cat="{{ $p->category_id }}"
                                    data-name="{{ e($p->name) }}"
                                    data-stock="{{ (int)$p->quantity_stock }}"
                                    data-box="{{ (int)$p->quantity_in_box }}"
                                    data-pp="{{ (float)$p->purchase_price }}"
                                    data-pv="{{ (int)$p->purchase_vat_type_id }}"
                                    data-sp="{{ (float)$p->sell_price }}"
                                    data-sv="{{ (int)$p->sell_vat_type_id }}"
                                    data-comment="{{ e($p->comment ?? '') }}"
                                    title="Edit"
                                >
                                    <i class="fas fa-edit"></i>
                                </button>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

{{-- Add/Edit Product Modal --}}
<div class="modal fade" id="productModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form id="productForm" method="POST" action="{{ route('inventory.save') }}" class="modal-content">
            @csrf
            <input type="hidden" name="id" id="prod_id">

            <div class="modal-header">
                <h5 class="modal-title">Product</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>

            <div class="modal-body">

                <div class="form-group">
                    <label>Category</label>
                    <select name="category_id" id="prod_cat" class="form-control" required>
                        @foreach($cats as $c)
                            <option value="{{ $c->id }}">{{ $c->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label>Name</label>
                    <input name="name" id="prod_name" class="form-control" required>
                </div>

                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label>Stock Qty</label>
                        <input type="number" name="quantity_stock" id="prod_stock" class="form-control" min="0" required>
                    </div>
                    <div class="form-group col-md-6">
                        <label>Qty in Box</label>
                        <input type="number" name="quantity_in_box" id="prod_box" class="form-control" min="1" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label>Purchase Price</label>
                        <input type="text" name="purchase_price" id="prod_pp" class="form-control" required>
                    </div>
                    <div class="form-group col-md-6">
                        <label>Purchase VAT</label>
                        <select name="purchase_vat_type_id" id="prod_pv" class="form-control" required>
                            @foreach($vats as $v)
                                <option value="{{ $v->id }}">{{ $v->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label>Sell Price</label>
                        <input type="text" name="sell_price" id="prod_sp" class="form-control" required>
                    </div>
                    <div class="form-group col-md-6">
                        <label>Sell VAT</label>
                        <select name="sell_vat_type_id" id="prod_sv" class="form-control" required>
                            @foreach($vats as $v)
                                <option value="{{ $v->id }}">{{ $v->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Comment</label>
                    <textarea name="comment" id="prod_comment" class="form-control"></textarea>
                </div>

            </div>

            <div class="modal-footer">
                <button class="btn btn-secondary" type="button" data-dismiss="modal">Cancel</button>
                <button class="btn btn-primary" type="submit">Save Product</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap4.min.js"></script>
<script>
$(function(){
    $('#inventoryTable').DataTable({ pageLength: 25 });

    $('#inventoryTable').on('click', '.edit-btn', function(){
        const b = $(this);
        openProductModal(
            b.data('id'),
            b.data('name'),
            b.data('cat'),
            b.data('stock'),
            b.data('box'),
            b.data('pp'),
            b.data('pv'),
            b.data('sp'),
            b.data('sv'),
            b.data('comment')
        );
    });
});

function openProductModal(
    id = '',
    name = '',
    categoryId = '',
    stock = 0,
    box = 1,
    pp = '',
    pv = '',
    sp = '',
    sv = '',
    comment = ''
) {
    $('#prod_id').val(id);
    $('#prod_name').val(name);
    $('#prod_cat').val(categoryId);
    $('#prod_stock').val(stock);
    $('#prod_box').val(box);
    $('#prod_pp').val(pp);
    $('#prod_pv').val(pv);
    $('#prod_sp').val(sp);
    $('#prod_sv').val(sv);
    $('#prod_comment').val(comment);
    $('#productModal').modal('show');
}
</script>
@endpush
