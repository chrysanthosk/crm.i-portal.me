@extends('layouts.app')

@section('title', 'Roles')

@section('content')
@php
$isAdminRole = ($selectedRole && ($selectedRole->role_key ?? '') === 'admin');
@endphp

<div class="container-fluid">

    <div class="d-flex align-items-center justify-content-between mb-3">
        <h1 class="h3 mb-0">Roles</h1>
        <a href="{{ route('settings.roles.index', ['role_id' => $selectedRoleId]) }}"
           class="btn btn-outline-secondary">
            <i class="fas fa-sync-alt mr-1"></i> Refresh
        </a>
    </div>

    {{-- Flash --}}
    @if(session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @if($errors->any())
    <div class="alert alert-danger">
        Please fix the errors below.
    </div>
    @endif

    <div class="row">

        {{-- LEFT: Roles table --}}
        <div class="col-lg-5">

            <div class="card">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <strong>Role List</strong>
                    <button class="btn btn-sm btn-primary" type="button" data-toggle="collapse" data-target="#addRoleCollapse">
                        <i class="fas fa-plus mr-1"></i> Add Role
                    </button>
                </div>

                <div class="collapse" id="addRoleCollapse">
                    <div class="card-body border-bottom">
                        <form method="POST" action="{{ route('settings.roles.store') }}">
                            @csrf

                            <div class="form-group">
                                <label class="form-label">Role Name</label>
                                <input class="form-control" name="role_name" value="{{ old('role_name') }}" required>
                                @error('role_name') <div class="text-danger small">{{ $message }}</div> @enderror
                            </div>

                            <div class="form-group">
                                <label class="form-label">Role Key</label>
                                <input class="form-control" name="role_key" value="{{ old('role_key') }}" required>
                                <small class="text-muted">Lowercase letters/numbers/underscore/dash. Example: appointment_manage</small>
                                @error('role_key') <div class="text-danger small">{{ $message }}</div> @enderror
                            </div>

                            <button class="btn btn-primary">
                                <i class="fas fa-save mr-1"></i> Create Role
                            </button>
                        </form>
                    </div>
                </div>

                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover mb-0 align-middle">
                            <thead>
                            <tr>
                                <th style="width:70px;">#</th>
                                <th>Name</th>
                                <th>Key</th>
                                <th style="width:160px;" class="text-right">Actions</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse($roles as $r)
                            <tr class="{{ (int)$selectedRoleId === (int)$r->id ? 'table-active' : '' }}">
                                <td>{{ $r->id }}</td>
                                <td class="font-weight-bold">{{ $r->role_name }}</td>
                                <td class="text-monospace">{{ $r->role_key }}</td>
                                <td class="text-right">

                                    {{-- Select for permissions editor --}}
                                    <a href="{{ route('settings.roles.index', ['role_id' => $r->id]) }}"
                                       class="btn btn-sm btn-outline-secondary"
                                       title="Edit permissions">
                                        <i class="fas fa-sliders-h"></i>
                                    </a>

                                    {{-- Edit role --}}
                                    <button type="button"
                                            class="btn btn-sm btn-outline-primary"
                                            data-toggle="modal"
                                            data-target="#editRoleModal-{{ $r->id }}"
                                            title="Edit role">
                                        <i class="fas fa-edit"></i>
                                    </button>

                                    {{-- Delete role --}}
                                    <form method="POST"
                                          action="{{ route('settings.roles.destroy', $r) }}"
                                          class="d-inline"
                                          onsubmit="return confirm('Delete this role?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete role">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>

                                    {{-- Edit Modal --}}
                                    <div class="modal fade" id="editRoleModal-{{ $r->id }}" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Edit Role</h5>
                                                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                                                </div>

                                                <form method="POST" action="{{ route('settings.roles.update', $r) }}">
                                                    @csrf
                                                    @method('PUT')

                                                    <div class="modal-body">

                                                        <div class="form-group">
                                                            <label class="form-label">Role Name</label>
                                                            <input class="form-control" name="role_name"
                                                                   value="{{ old('role_name', $r->role_name) }}" required>
                                                        </div>

                                                        <div class="form-group">
                                                            <label class="form-label">Role Key</label>
                                                            <input class="form-control" name="role_key"
                                                                   value="{{ old('role_key', $r->role_key) }}" required>
                                                            <small class="text-muted">Lowercase letters/numbers/underscore/dash.</small>
                                                        </div>

                                                    </div>

                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                                                        <button class="btn btn-primary">
                                                            <i class="fas fa-save mr-1"></i> Save
                                                        </button>
                                                    </div>
                                                </form>

                                            </div>
                                        </div>
                                    </div>

                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="4" class="text-center text-muted p-4">No roles found.</td>
                            </tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>

        {{-- RIGHT: Permissions editor --}}
        <div class="col-lg-7">

            <div class="card">
                <div class="card-header">
                    <strong>Permissions</strong>
                </div>

                <div class="card-body">

                    @if(!$selectedRole)
                    <div class="alert alert-warning mb-0">
                        No role selected.
                    </div>
                    @else

                    @if($isAdminRole)
                    <div class="alert alert-info">
                        <strong>Admin role:</strong> shown as having <strong>all permissions</strong>.
                        Saving will re-sync admin to all permissions.
                    </div>
                    @endif

                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div>
                            <div class="text-muted small">Selected Role</div>
                            <div class="h5 mb-0">{{ $selectedRole->role_name }}</div>
                            <div class="text-monospace text-muted small">{{ $selectedRole->role_key }}</div>
                        </div>

                        <div style="min-width: 280px;">
                            <label class="text-muted small mb-1">Switch role</label>
                            <select class="form-control"
                                    onchange="window.location='{{ route('settings.roles.index') }}?role_id='+this.value;">
                                @foreach($roles as $r)
                                <option value="{{ $r->id }}" @selected((int)$selectedRoleId === (int)$r->id)>
                                    {{ $r->role_name }} ({{ $r->role_key }})
                                </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <input type="text" id="permSearch" class="form-control"
                               placeholder="Search permissions… (type to filter)">
                    </div>

                    <div class="d-flex align-items-center mb-3">
                        <button type="button" class="btn btn-sm btn-outline-secondary mr-2" id="btnSelectAll">
                            Select all
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="btnClearAll">
                            Clear all
                        </button>
                    </div>

                    <form method="POST" action="{{ route('settings.roles.permissions.sync', $selectedRole) }}">
                        @csrf

                        <div id="permissionsWrap">
                            @foreach($permissionsGrouped as $group => $perms)
                            @php
                            $gid = 'grp_' . \Illuminate\Support\Str::slug($group);
                            @endphp

                            <div class="border rounded p-3 mb-3 permission-group" data-group="{{ strtolower($group) }}">
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <div class="font-weight-bold">{{ $group }}</div>

                                    <div>
                                        <button type="button"
                                                class="btn btn-xs btn-outline-secondary btn-group-select"
                                                data-target="{{ $gid }}">
                                            Select group
                                        </button>
                                        <button type="button"
                                                class="btn btn-xs btn-outline-secondary btn-group-clear"
                                                data-target="{{ $gid }}">
                                            Clear group
                                        </button>
                                    </div>
                                </div>

                                <div class="row" id="{{ $gid }}">
                                    @foreach($perms as $p)
                                    @php
                                    $checked = $isAdminRole ? true : in_array((int)$p->id, $selectedRolePermissionIds, true);
                                    $text = strtolower(($p->permission_key ?? '') . ' ' . ($p->permission_name ?? '') . ' ' . ($p->permission_group ?? ''));
                                    @endphp
                                    <div class="col-md-6 mb-2 permission-item" data-text="{{ $text }}">
                                        <div class="form-check">
                                            <input class="form-check-input perm-checkbox"
                                                   type="checkbox"
                                                   name="permissions[]"
                                                   value="{{ $p->id }}"
                                                   id="perm-{{ $p->id }}"
                                                   @checked($checked)>
                                            <label class="form-check-label" for="perm-{{ $p->id }}">
                                                <span class="font-weight-bold">{{ $p->permission_name ?? $p->permission_key }}</span>
                                                <div class="text-muted small text-monospace">{{ $p->permission_key }}</div>
                                            </label>
                                        </div>
                                    </div>
                                    @endforeach
                                </div>
                            </div>
                            @endforeach
                        </div>

                        <button class="btn btn-primary">
                            <i class="fas fa-save mr-1"></i> Save Permissions
                        </button>
                    </form>
                    @endif

                </div>
            </div>

        </div>
    </div>

</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const search = document.getElementById('permSearch');
        const wrap = document.getElementById('permissionsWrap');

        function setAll(checked) {
            document.querySelectorAll('.perm-checkbox').forEach(cb => cb.checked = checked);
        }

        const btnSelectAll = document.getElementById('btnSelectAll');
        const btnClearAll = document.getElementById('btnClearAll');

        if (btnSelectAll) btnSelectAll.addEventListener('click', () => setAll(true));
        if (btnClearAll) btnClearAll.addEventListener('click', () => setAll(false));

        // Group select/clear
        document.querySelectorAll('.btn-group-select').forEach(btn => {
            btn.addEventListener('click', () => {
                const target = document.getElementById(btn.dataset.target);
                if (!target) return;
                target.querySelectorAll('.perm-checkbox').forEach(cb => cb.checked = true);
            });
        });

        document.querySelectorAll('.btn-group-clear').forEach(btn => {
            btn.addEventListener('click', () => {
                const target = document.getElementById(btn.dataset.target);
                if (!target) return;
                target.querySelectorAll('.perm-checkbox').forEach(cb => cb.checked = false);
            });
        });

        // Search filter
        if (search && wrap) {
            search.addEventListener('input', function () {
                const q = (this.value || '').trim().toLowerCase();
                const items = wrap.querySelectorAll('.permission-item');
                const groups = wrap.querySelectorAll('.permission-group');

                if (!q) {
                    items.forEach(i => i.style.display = '');
                    groups.forEach(g => g.style.display = '');
                    return;
                }

                items.forEach(i => {
                    const t = i.dataset.text || '';
                    i.style.display = t.includes(q) ? '' : 'none';
                });

                groups.forEach(g => {
                    const visible = g.querySelectorAll('.permission-item:not([style*="display: none"])').length;
                    g.style.display = visible ? '' : 'none';
                });
            });
        }
    });
</script>
@endsection
