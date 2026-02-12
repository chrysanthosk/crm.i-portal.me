@php
$isEdit = ($mode ?? 'create') === 'edit';
$s = $service ?? new \App\Models\Service();
@endphp

<form method="POST" action="{{ $isEdit ? route('services.update', $s) : route('services.store') }}">
    @csrf
    @if($isEdit)
    @method('PUT')
    @endif

    <div class="row">
        <div class="col-md-6 mb-3">
            <label class="form-label">Name</label>
            <input type="text" name="name" class="form-control"
                   value="{{ old('name', $s->name) }}" required maxlength="150">
            @error('name') <div class="text-danger small">{{ $message }}</div> @enderror
        </div>

        <div class="col-md-6 mb-3">
            <label class="form-label">Category</label>
            <select name="category_id" class="form-control" required>
                <option value="">Select category...</option>
                @foreach($categories as $c)
                <option value="{{ $c->id }}" @selected((string)old('category_id', $s->category_id) === (string)$c->id)>
                {{ $c->name }}
                </option>
                @endforeach
            </select>
            @error('category_id') <div class="text-danger small">{{ $message }}</div> @enderror
        </div>

        <div class="col-md-3 mb-3">
            <label class="form-label">Price (€)</label>
            <input type="number" name="price" step="0.01" min="0" class="form-control"
                   value="{{ old('price', $s->price) }}" required>
            @error('price') <div class="text-danger small">{{ $message }}</div> @enderror
        </div>

        <div class="col-md-3 mb-3">
            <label class="form-label">VAT Type</label>
            <select name="vat_type_id" class="form-control" required>
                <option value="">Select VAT...</option>
                @foreach($vatTypes as $v)
                <option value="{{ $v->id }}" @selected((string)old('vat_type_id', $s->vat_type_id) === (string)$v->id)>
                {{ $v->name }} ({{ number_format((float)$v->vat_percent, 2) }}%)
                </option>
                @endforeach
            </select>
            @error('vat_type_id') <div class="text-danger small">{{ $message }}</div> @enderror
        </div>

        <div class="col-md-3 mb-3">
            <label class="form-label">Duration (min)</label>
            <input type="number" name="duration" min="0" class="form-control"
                   value="{{ old('duration', (int)($s->duration ?? 0)) }}" required>
            @error('duration') <div class="text-danger small">{{ $message }}</div> @enderror
        </div>

        <div class="col-md-3 mb-3">
            <label class="form-label">Waiting (min)</label>
            <input type="number" name="waiting" min="0" class="form-control"
                   value="{{ old('waiting', (int)($s->waiting ?? 0)) }}" required>
            @error('waiting') <div class="text-danger small">{{ $message }}</div> @enderror
        </div>

        <div class="col-md-4 mb-3">
            <label class="form-label">Gender</label>
            <select name="gender" class="form-control" required>
                @foreach(['Male','Female','Both'] as $g)
                <option value="{{ $g }}" @selected(old('gender', $s->gender ?? 'Both') === $g)>
                {{ $g }}
                </option>
                @endforeach
            </select>
            @error('gender') <div class="text-danger small">{{ $message }}</div> @enderror
        </div>

        <div class="col-md-8 mb-3">
            <label class="form-label">Comment</label>
            <textarea name="comment" class="form-control" rows="2">{{ old('comment', $s->comment) }}</textarea>
            @error('comment') <div class="text-danger small">{{ $message }}</div> @enderror
        </div>
    </div>

    <button type="submit" class="btn btn-primary">
        <i class="fas fa-save mr-2"></i> {{ $isEdit ? 'Save Changes' : 'Create Service' }}
    </button>

    <a href="{{ route('services.index') }}" class="btn btn-outline-secondary ml-2">
        Cancel
    </a>
</form>
