@extends('layouts.app')

@section('title', 'Edit Staff')

@section('content')
<div class="container-fluid">

    <div class="d-flex align-items-center justify-content-between mb-3">
        <h1 class="h3 mb-0">Edit Staff</h1>
        <a href="{{ route('staff.index') }}" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left mr-2"></i> Back
        </a>
    </div>

    @if ($errors->any())
    <div class="alert alert-danger">Please fix the errors below.</div>
    @endif

    <div class="card">
        <div class="card-header"><strong>Staff Details</strong></div>

        <div class="card-body">
            <form method="POST" action="{{ route('staff.update', $staffMember) }}">
                @csrf
                @method('PUT')

                <div class="row">

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Linked User (optional)</label>
                        <select name="user_id" class="form-control">
                            <option value="">— None —</option>
                            @foreach($users as $u)
                            <option value="{{ $u->id }}" @selected((string)old('user_id', $staffMember->user_id) === (string)$u->id)>
                            {{ $u->name }} ({{ $u->email }})
                            </option>
                            @endforeach
                        </select>
                        @error('user_id') <div class="text-danger small">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Staff Role</label>
                        <select name="role_id" class="form-control" required>
                            @foreach($roles as $r)
                            <option value="{{ $r->id }}" @selected((string)old('role_id', $staffMember->role_id) === (string)$r->id)>
                            {{ $r->role_name }} ({{ $r->role_key }})
                            </option>
                            @endforeach
                        </select>
                        @error('role_id') <div class="text-danger small">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label">Mobile</label>
                        <input type="text" name="mobile" class="form-control"
                               value="{{ old('mobile', $staffMember->mobile) }}">
                        @error('mobile') <div class="text-danger small">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label">Date of Birth</label>
                        <input type="date" name="dob" class="form-control"
                               value="{{ old('dob', $staffMember->dob ? $staffMember->dob->format('Y-m-d') : '') }}">
                        @error('dob') <div class="text-danger small">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label">Color</label>

                        <div class="d-flex align-items-center">
                            <input type="color" id="color_picker" class="form-control" style="max-width:90px;"
                                   value="{{ old('color', $staffMember->color) }}">
                            <input type="text" id="color_text" name="color" class="form-control ml-2"
                                   value="{{ old('color', $staffMember->color) }}" required>
                        </div>

                        <div class="mt-2">
              <span class="badge" id="color_preview_badge" style="background:{{ old('color', $staffMember->color) }}; color:#fff;">
                <span id="color_preview_text">{{ strtoupper(old('color', $staffMember->color)) }}</span>
              </span>
                        </div>

                        @error('color') <div class="text-danger small">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label d-block">Show in Calendar</label>
                        <div class="custom-control custom-switch">
                            <input type="checkbox" class="custom-control-input" id="show_in_calendar"
                                   name="show_in_calendar" value="1"
                                   {{ old('show_in_calendar', $staffMember->show_in_calendar ? '1' : '') ? 'checked' : '' }}>
                            <label class="custom-control-label" for="show_in_calendar">Enabled</label>
                        </div>
                        @error('show_in_calendar') <div class="text-danger small">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label">Annual Leave Days</label>
                        <input type="number" step="0.1" min="0" max="365"
                               name="annual_leave_days" class="form-control"
                               value="{{ old('annual_leave_days', (string)$staffMember->annual_leave_days) }}">
                        @error('annual_leave_days') <div class="text-danger small">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label">Position</label>
                        <input type="number" min="0" name="position" class="form-control"
                               value="{{ old('position', (string)$staffMember->position) }}">
                        @error('position') <div class="text-danger small">{{ $message }}</div> @enderror
                    </div>

                </div>

                {{-- ── Services / Skills ────────────────────────────── --}}
                <hr>
                <h5 class="mb-3"><i class="fas fa-hands-helping mr-1"></i> Services / Skills</h5>
                <p class="text-muted small mb-2">Tick the services this staff member is certified to perform.</p>

                @if($serviceCategories->isEmpty())
                    <p class="text-muted small">No services found. Add services first.</p>
                @else
                    <div class="row">
                    @foreach($serviceCategories as $cat)
                        @if($cat->services->isNotEmpty())
                        <div class="col-md-4 mb-3">
                            <div class="card h-100">
                                <div class="card-header py-2 px-3">
                                    <strong class="small">{{ $cat->name }}</strong>
                                    <button type="button" class="btn btn-xs btn-link float-right p-0 toggle-cat"
                                            data-cat="{{ $cat->id }}">all</button>
                                </div>
                                <div class="card-body py-2 px-3">
                                    @foreach($cat->services as $svc)
                                    <div class="form-check mb-1">
                                        <input class="form-check-input skill-cb-{{ $cat->id }}"
                                               type="checkbox"
                                               name="service_ids[]"
                                               value="{{ $svc->id }}"
                                               id="svc_{{ $svc->id }}"
                                               @checked(in_array($svc->id, $staffServiceIds))>
                                        <label class="form-check-label small" for="svc_{{ $svc->id }}">
                                            {{ $svc->name }}
                                        </label>
                                    </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                        @endif
                    @endforeach
                    </div>
                @endif

                {{-- ── Working Hours ─────────────────────────────────── --}}
                <hr>
                <h5 class="mb-3"><i class="fas fa-clock mr-1"></i> Working Hours</h5>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th style="width:120px;">Day</th>
                                <th style="width:100px;">Day Off</th>
                                <th style="width:130px;">Start</th>
                                <th style="width:130px;">End</th>
                            </tr>
                        </thead>
                        <tbody>
                        @foreach(\App\Models\StaffAvailability::DAY_NAMES as $dow => $dayName)
                            @php $avail = $availabilityByDay[$dow] ?? null; @endphp
                            <tr>
                                <td><strong>{{ $dayName }}</strong></td>
                                <td>
                                    <div class="form-check">
                                        <input class="form-check-input day-off-cb"
                                               type="checkbox"
                                               name="availability[{{ $dow }}][is_day_off]"
                                               id="dayoff_{{ $dow }}"
                                               data-dow="{{ $dow }}"
                                               @checked($avail?->is_day_off)>
                                        <label class="form-check-label small" for="dayoff_{{ $dow }}">Off</label>
                                    </div>
                                </td>
                                <td>
                                    <input type="time"
                                           class="form-control form-control-sm avail-time-{{ $dow }}"
                                           name="availability[{{ $dow }}][start_time]"
                                           value="{{ $avail?->start_time ?? '09:00' }}"
                                           @if($avail?->is_day_off) disabled @endif>
                                </td>
                                <td>
                                    <input type="time"
                                           class="form-control form-control-sm avail-time-{{ $dow }}"
                                           name="availability[{{ $dow }}][end_time]"
                                           value="{{ $avail?->end_time ?? '18:00' }}"
                                           @if($avail?->is_day_off) disabled @endif>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>

                <hr>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save mr-2"></i> Save Changes
                </button>
            </form>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script>
    (function(){
        const picker = document.getElementById('color_picker');
        const text   = document.getElementById('color_text');
        const badge  = document.getElementById('color_preview_badge');
        const label  = document.getElementById('color_preview_text');

        function normalizeHex(v){
            v = (v || '').trim();
            if (!v) return '#000000';
            if (v[0] !== '#') v = '#'+v;
            if (/^#[0-9A-Fa-f]{6}$/.test(v)) return v;
            return '#000000';
        }

        function syncFrom(value){
            const hex = normalizeHex(value);
            picker.value = hex;
            text.value = hex;
            badge.style.background = hex;
            label.textContent = hex.toUpperCase();
        }

        picker.addEventListener('input', () => syncFrom(picker.value));
        text.addEventListener('input', () => syncFrom(text.value));

        syncFrom(text.value);
    })();

    // Toggle all services in a category
    document.querySelectorAll('.toggle-cat').forEach(btn => {
        btn.addEventListener('click', function () {
            const catId = this.dataset.cat;
            const boxes = document.querySelectorAll('.skill-cb-' + catId);
            const allChecked = [...boxes].every(b => b.checked);
            boxes.forEach(b => b.checked = !allChecked);
        });
    });

    // Day-off toggle disables time inputs
    document.querySelectorAll('.day-off-cb').forEach(cb => {
        cb.addEventListener('change', function () {
            const dow = this.dataset.dow;
            document.querySelectorAll('.avail-time-' + dow).forEach(input => {
                input.disabled = this.checked;
            });
        });
    });
</script>
@endpush
