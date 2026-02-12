@extends('layouts.app')

@section('title', 'Edit Staff')

@section('content')
<div class="container-fluid">

    <div class="d-flex align-items-center justify-content-between mb-3">
        <h1 class="h3 mb-0">Edit Staff</h1>
        <a href="{{ route('admin.staff.index') }}" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-2"></i> Back
        </a>
    </div>

    @if ($errors->any())
    <div class="alert alert-danger">Please fix the errors below.</div>
    @endif

    <div class="card">
        <div class="card-header">
            <strong>Staff Details</strong>
        </div>

        <div class="card-body">
            <form method="POST" action="{{ route('admin.staff.update', $staffMember) }}">
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

                    {{-- ✅ Color picker + live code + preview --}}
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Color</label>

                        @php
                        $initialColor = old('color', $staffMember->color ?? '#000000');
                        @endphp

                        <div class="d-flex align-items-center">
                            <input type="color"
                                   id="color_picker"
                                   class="form-control p-1"
                                   style="width: 64px; height: 38px;"
                                   value="{{ $initialColor }}">

                            <input type="text"
                                   id="color_text"
                                   name="color"
                                   class="form-control ml-2"
                                   value="{{ $initialColor }}"
                                   placeholder="#000000"
                                   required>

                            <span id="color_badge"
                                  class="badge ml-2"
                                  style="background: {{ $initialColor }}; min-width: 84px;">
                                {{ $initialColor }}
                            </span>
                        </div>

                        @error('color') <div class="text-danger small">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label d-block">Show in Calendar</label>
                        <div class="custom-control custom-switch">
                            <input type="checkbox"
                                   class="custom-control-input"
                                   id="show_in_calendar"
                                   name="show_in_calendar"
                                   value="1"
                                   {{ old('show_in_calendar', $staffMember->show_in_calendar ? '1' : '') ? 'checked' : '' }}>
                            <label class="custom-control-label" for="show_in_calendar">Enabled</label>
                        </div>
                        @error('show_in_calendar') <div class="text-danger small">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label">Annual Leave Days</label>
                        <input type="number" step="0.1" min="0" max="365"
                               name="annual_leave_days"
                               class="form-control"
                               value="{{ old('annual_leave_days', (string)$staffMember->annual_leave_days) }}">
                        @error('annual_leave_days') <div class="text-danger small">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label">Position</label>
                        <input type="number" min="0"
                               name="position"
                               class="form-control"
                               value="{{ old('position', (string)$staffMember->position) }}">
                        @error('position') <div class="text-danger small">{{ $message }}</div> @enderror
                    </div>

                </div>

                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save me-2"></i> Save Changes
                </button>
            </form>
        </div>
    </div>

</div>

{{-- ✅ Live sync script --}}
<script>
    (function () {
        const picker = document.getElementById('color_picker');
        const text   = document.getElementById('color_text');
        const badge  = document.getElementById('color_badge');

        if (!picker || !text || !badge) return;

        const normalizeHex = (v) => {
            if (!v) return null;
            v = v.trim();
            if (!v.startsWith('#')) v = '#' + v;
            if (!/^#[0-9A-Fa-f]{6}$/.test(v)) return null;
            return v.toUpperCase();
        };

        const applyColor = (hex) => {
            badge.style.background = hex;
            badge.textContent = hex;
            text.value = hex;
            picker.value = hex;
        };

        picker.addEventListener('input', () => {
            const hex = normalizeHex(picker.value);
            if (hex) applyColor(hex);
        });

        text.addEventListener('input', () => {
            const hex = normalizeHex(text.value);
            if (hex) applyColor(hex);
            else badge.textContent = text.value.trim();
        });

        const init = normalizeHex(text.value) || '#000000';
        applyColor(init);
    })();
</script>
@endsection
