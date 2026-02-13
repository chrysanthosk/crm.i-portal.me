@php
$isEdit = ($mode ?? 'create') === 'edit';
$a = $appointment;

$dtLocal = function($dt) {
    if (!$dt) return '';
    try {
        return \Illuminate\Support\Carbon::parse($dt)->format('Y-m-d\TH:i');
    } catch (\Throwable $e) {
        return '';
    }
};

// derive initial selected category (from old input or from selected service)
$initialServiceId = (string) old('service_id', $a->service_id);
$initialCategoryId = (string) old('service_category_id', '');

if ($initialCategoryId === '' && $initialServiceId !== '') {
    $svc = $services->firstWhere('id', (int) $initialServiceId);
    if ($svc && isset($svc->category_id)) {
        $initialCategoryId = (string) $svc->category_id;
    }
}
@endphp

<form
    method="POST"
    action="{{ $isEdit ? route('appointments.update', $a) : route('appointments.store') }}"
    data-services-url="{{ route('appointments.services') }}"
>
    @csrf
    @if($isEdit)
        @method('PUT')
    @endif

    <div class="row">

        <div class="col-md-6 mb-3">
            <label class="form-label">Staff</label>
            <select name="staff_id" class="form-control select2" required>
                <option value="">Select staff...</option>
                @foreach($staff as $s)
                    @php $label = $s->user?->name ?? ('Staff #'.$s->id); @endphp
                    <option value="{{ $s->id }}" @selected((string)old('staff_id', $a->staff_id) === (string)$s->id)>
                        {{ $label }}
                    </option>
                @endforeach
            </select>
            <div class="text-muted small mt-1">Drag/drop on the calendar will also update staff/time.</div>
        </div>

        <div class="col-md-3 mb-3">
            <label class="form-label">Start</label>
            <input type="datetime-local" name="start_at" class="form-control"
                   value="{{ old('start_at', $dtLocal($a->start_at)) }}" required>
        </div>

        <div class="col-md-3 mb-3">
            <label class="form-label">End</label>
            <input type="datetime-local" name="end_at" class="form-control"
                   value="{{ old('end_at', $dtLocal($a->end_at)) }}" required>
        </div>

        <div class="col-md-6 mb-3">
            <label class="form-label">Existing Client (optional)</label>
            <select name="client_id" class="form-control select2">
                <option value="">— New client —</option>
                @foreach($clients as $c)
                    @php
                        $label = trim(($c->first_name ?? '').' '.($c->last_name ?? ''));
                        $label = $label !== '' ? $label : ($c->email ?? 'Client');
                    @endphp
                    <option value="{{ $c->id }}" @selected((string)old('client_id', $a->client_id) === (string)$c->id)>
                        {{ $label }} — {{ $c->mobile }}
                    </option>
                @endforeach
            </select>

            <div class="existing-client-hint text-muted small mt-1" style="display:none;">
                Existing client selected — new-client fields are ignored.
            </div>
        </div>

        <div class="col-md-3 mb-3 new-client-fields">
            <label class="form-label">New Client First Name</label>
            <input type="text" name="client_first_name" class="form-control"
                   value="{{ old('client_first_name') }}" maxlength="100">
            @error('client_first_name') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
        </div>

        <div class="col-md-3 mb-3 new-client-fields">
            <label class="form-label">New Client Last Name</label>
            <input type="text" name="client_last_name" class="form-control"
                   value="{{ old('client_last_name') }}" maxlength="100">
            @error('client_last_name') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
        </div>

        <div class="col-md-3 mb-3 new-client-fields">
            <label class="form-label">New Client Phone</label>
            <input type="text" name="client_phone" class="form-control"
                   value="{{ old('client_phone') }}" maxlength="20">
            @error('client_phone') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
        </div>

        {{-- ✅ Service Category --}}
        <div class="col-md-6 mb-3">
            <label class="form-label">Service Category</label>
            <select name="service_category_id"
                    class="form-control select2 js-service-category"
                    data-initial="{{ $initialCategoryId }}"
                    required>
                <option value="">Select category...</option>
                @foreach($serviceCategories as $cat)
                    <option value="{{ $cat->id }}" @selected($initialCategoryId === (string)$cat->id)>
                        {{ $cat->name ?? ('Category #'.$cat->id) }}
                    </option>
                @endforeach
            </select>
            @error('service_category_id') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
        </div>

        {{-- ✅ Service (filtered by category via JS) --}}
        <div class="col-md-6 mb-3">
            <label class="form-label">Service</label>
            <select name="service_id"
                    class="form-control select2 js-service"
                    data-initial="{{ $initialServiceId }}"
                    required>
                <option value="">Select service...</option>

                {{-- fallback if JS is disabled --}}
                @foreach($services as $svc)
                    <option value="{{ $svc->id }}" @selected((string)old('service_id', $a->service_id) === (string)$svc->id)>
                        {{ $svc->name ?? ('Service #'.$svc->id) }}
                    </option>
                @endforeach
            </select>
            @error('service_id') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
            <div class="text-muted small mt-1">Choose category first to filter services.</div>
        </div>

        <div class="col-md-3 mb-3">
            <label class="form-label">Status</label>
            <select name="status" class="form-control" required>
                @foreach(['scheduled','confirmed','completed','cancelled','no_show'] as $st)
                    <option value="{{ $st }}" @selected(old('status', $a->status ?? 'scheduled') === $st)>
                        {{ ucfirst(str_replace('_',' ', $st)) }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="col-md-3 mb-3">
            <label class="form-label d-block">Send SMS</label>
            <div class="custom-control custom-switch">
                <input type="checkbox" class="custom-control-input" id="send_sms"
                       name="send_sms" value="1"
                       {{ old('send_sms', $a->send_sms ? '1' : '') ? 'checked' : '' }}>
                <label class="custom-control-label" for="send_sms">Enabled</label>
            </div>
        </div>

        <div class="col-md-6 mb-3">
            <label class="form-label">Notes</label>
            <textarea name="notes" class="form-control" rows="3">{{ old('notes', $a->notes) }}</textarea>
        </div>

        <div class="col-md-6 mb-3">
            <label class="form-label">Internal Notes</label>
            <textarea name="internal_notes" class="form-control" rows="3">{{ old('internal_notes', $a->internal_notes) }}</textarea>
        </div>

    </div>

    <div class="d-flex justify-content-between align-items-center mt-2">
        <div>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save mr-2"></i> {{ $isEdit ? 'Save Changes' : 'Create Appointment' }}
            </button>
            <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">
                Cancel
            </button>
        </div>

        @if($isEdit)
            <button type="button"
                    class="btn btn-outline-danger"
                    data-action="delete"
                    data-id="{{ $a->id }}">
                <i class="fas fa-trash mr-2"></i> Delete
            </button>
        @endif
    </div>
</form>
