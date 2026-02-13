@php
$isEdit = ($mode ?? 'create') === 'edit';
$p = $product ?? new \App\Models\Product();
@endphp

<form method="POST" action="{{ $isEdit ? route('products.update', $p) : route('products.store') }}">
    @csrf
    @if($isEdit)
        @method('PUT')
    @endif

    <div class="row">

        <div class="col-md-6 mb-3">
            <label class="form-label">Name</label>
            <input type="text" name="name" class="form-control"
                   value="{{ old('name', $p->name) }}" required maxlength="150">
            @error('name') <div class="text-danger small">{{ $message }}</div> @enderror
        </div>

        <div class="col-md-6 mb-3">
            <label class="form-label">Category</label>
            <select name="category_id" class="form-control" required>
                <option value="">Select category...</option>
                @foreach($categories as $c)
                    <option value="{{ $c->id }}" @selected((string)old('category_id', $p->category_id) === (string)$c->id)>
                        {{ $c->name }}
                    </option>
                @endforeach
            </select>
            @error('category_id') <div class="text-danger small">{{ $message }}</div> @enderror
        </div>

        <div class="col-md-3 mb-3">
            <label class="form-label">Purchase Price (€)</label>
            <input type="number" name="purchase_price" step="0.01" min="0" class="form-control"
                   value="{{ old('purchase_price', $p->purchase_price) }}" required>
            @error('purchase_price') <div class="text-danger small">{{ $message }}</div> @enderror
        </div>

        <div class="col-md-3 mb-3">
            <label class="form-label">Purchase VAT</label>
            <select name="purchase_vat_type_id" class="form-control" required>
                <option value="">Select VAT...</option>
                @foreach($vatTypes as $v)
                    <option value="{{ $v->id }}" @selected((string)old('purchase_vat_type_id', $p->purchase_vat_type_id) === (string)$v->id)>
                        {{ $v->name }} ({{ number_format((float)$v->vat_percent, 2) }}%)
                    </option>
                @endforeach
            </select>
            @error('purchase_vat_type_id') <div class="text-danger small">{{ $message }}</div> @enderror
        </div>

        <div class="col-md-3 mb-3">
            <label class="form-label">Sell Price (€)</label>
            <input type="number" name="sell_price" step="0.01" min="0" class="form-control"
                   value="{{ old('sell_price', $p->sell_price) }}" required>
            @error('sell_price') <div class="text-danger small">{{ $message }}</div> @enderror
        </div>

        <div class="col-md-3 mb-3">
            <label class="form-label">Sell VAT</label>
            <select name="sell_vat_type_id" class="form-control" required>
                <option value="">Select VAT...</option>
                @foreach($vatTypes as $v)
                    <option value="{{ $v->id }}" @selected((string)old('sell_vat_type_id', $p->sell_vat_type_id) === (string)$v->id)>
                        {{ $v->name }} ({{ number_format((float)$v->vat_percent, 2) }}%)
                    </option>
                @endforeach
            </select>
            @error('sell_vat_type_id') <div class="text-danger small">{{ $message }}</div> @enderror
        </div>

        <div class="col-md-3 mb-3">
            <label class="form-label">Stock</label>
            <input type="number" name="quantity_stock" class="form-control"
                   value="{{ old('quantity_stock', (int)($p->quantity_stock ?? 0)) }}" required>
            @error('quantity_stock') <div class="text-danger small">{{ $message }}</div> @enderror
        </div>

        <div class="col-md-3 mb-3">
            <label class="form-label">Qty in Box</label>
            <input type="number" name="quantity_in_box" min="1" class="form-control"
                   value="{{ old('quantity_in_box', (int)($p->quantity_in_box ?? 1)) }}" required>
            @error('quantity_in_box') <div class="text-danger small">{{ $message }}</div> @enderror
        </div>

        <div class="col-md-6 mb-3">
            <label class="form-label">Comment</label>
            <textarea name="comment" class="form-control" rows="2">{{ old('comment', $p->comment) }}</textarea>
            @error('comment') <div class="text-danger small">{{ $message }}</div> @enderror
        </div>

    </div>

    <button type="submit" class="btn btn-primary">
        <i class="fas fa-save mr-2"></i> {{ $isEdit ? 'Save Changes' : 'Create Product' }}
    </button>

    <a href="{{ route('products.index') }}" class="btn btn-outline-secondary ml-2">
        Cancel
    </a>
</form>
