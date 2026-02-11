@extends('layouts.app')

@section('title', 'Admin - Roles')

@section('content')
<div class="container-fluid">

  <div class="d-flex align-items-center justify-content-between mb-3">
    <h1 class="h3 mb-0">Roles</h1>
  </div>

  @if ($errors->any())
    <div class="alert alert-danger">
      <strong>Please fix the errors below.</strong>
      <ul class="mb-0 mt-2">
        @foreach ($errors->all() as $e)
          <li>{{ $e }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  {{-- Role Management --}}
  <div class="card mb-4">
    <div class="card-header d-flex align-items-center justify-content-between">
      <strong>Role Management</strong>

      <button class="btn btn-sm btn-primary" data-toggle="collapse" data-target="#addRoleCollapse" aria-expanded="false">
        <i class="fas fa-plus mr-1"></i> Add Role
      </button>
    </div>

    <div class="collapse" id="addRoleCollapse">
      <div class="card-body border-bottom">
        <form method="POST" action="{{ route('admin.roles.store') }}">
          @csrf

          <div class="row">
            <div class="col-md-4 mb-3">
              <label class="form-label">Role Key</label>
              <input class="form-control" name="role_key" value="{{ old('role_key') }}" required>
              <small class="text-muted">Lowercase letters/numbers/underscore/dash. Example: vat_manage</small>
              @error('role_key') <div class="text-danger small">{{ $message }}</div> @enderror
            </div>

            <div class="col-md-4 mb-3">
              <label class="form-label">Role Name</label>
              <input class="form-control" name="role_name" value="{{ old('role_name') }}" required>
              @error('role_name') <div class="text-danger small">{{ $message }}</div> @enderror
            </div>

            <div class="col-md-4 mb-3">
              <label class="form-label">Role Description</label>
              <input class="form-control" name="role_desc" value="{{ old('role_desc') }}">
              @error('role_desc') <div class="text-danger small">{{ $message }}</div> @enderror
            </div>
          </div>

          <button class="btn btn-primary" type="submit">
            <i class="fas fa-save mr-1"></i> Save Role
          </button>
        </form>
      </div>
    </div>

    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead>
            <tr>
              <th style="width: 80px;">ID</th>
              <th style="width: 220px;">Role Key</th>
              <th>Role Name</th>
              <th>Description</th>
              <th style="width: 220px;" class="text-right">Actions</th>
            </tr>
          </thead>
          <tbody>
            @forelse($roles as $role)
              <tr>
                <td>{{ $role->id }}</td>
                <td><code>{{ $role->role_key }}</code></td>
                <td><strong>{{ $role->role_name }}</strong></td>
                <td>{{ $role->role_desc ?? '-' }}</td>
                <td class="text-right">

                  {{-- Edit -> modal --}}
                  <button
                    type="button"
                    class="btn btn-sm btn-outline-primary"
                    data-toggle="modal"
                    data-target="#editRoleModal"
                    data-role-id="{{ $role->id }}"
                    data-role-key="{{ $role->role_key }}"
                    data-role-name="{{ $role->role_name }}"
                    data-role-desc="{{ $role->role_desc }}"
                  >
                    <i class="fas fa-edit mr-1"></i> Edit
                  </button>

                  {{-- Delete --}}
                  <form method="POST"
                        action="{{ route('admin.roles.destroy', $role->id) }}"
                        class="d-inline"
                        onsubmit="return confirm('Delete role: {{ $role->role_key }} ?')">
                    @csrf
                    @method('DELETE')
                    <button class="btn btn-sm btn-outline-danger" {{ $role->role_key === 'admin' ? 'disabled' : '' }}>
                      <i class="fas fa-trash mr-1"></i> Delete
                    </button>
                  </form>

                </td>
              </tr>
            @empty
              <tr>
                <td colspan="5" class="text-center text-muted py-4">No roles found.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>

  {{-- Assign Permissions --}}
  <div class="card">
    <div class="card-header">
      <strong>Assign Permissions to Role</strong>
    </div>
    <div class="card-body">

      {{-- Select role (GET role_id to reload) --}}
      <form method="GET" action="{{ route('admin.roles.index') }}" class="mb-3">
        <div class="row">
          <div class="col-md-4">
            <label class="form-label">Select Role</label>
            <select class="form-control" name="role_id" onchange="this.form.submit()">
              @foreach($roles as $r)
                <option value="{{ $r->id }}" {{ $selectedRole && (int)$selectedRole->id === (int)$r->id ? 'selected' : '' }}>
                  {{ $r->role_name }} ({{ $r->role_key }})
                </option>
              @endforeach
            </select>
          </div>
        </div>
      </form>

      @if($selectedRole)
        <form method="POST" action="{{ route('admin.roles.permissions.sync', $selectedRole->id) }}">
          @csrf

          <div class="mb-2">
            <span class="badge badge-secondary">Selected:</span>
            <strong class="ml-1">{{ $selectedRole->role_name }}</strong>
            <span class="text-muted">({{ $selectedRole->role_key }})</span>
          </div>

          <div class="border rounded p-3" style="max-height: 360px; overflow:auto;">
            @php($assignedIds = $selectedPermissionIds ?? [])

            @forelse($permissions as $perm)
              <div class="custom-control custom-checkbox">
                <input
                  type="checkbox"
                  class="custom-control-input"
                  id="perm_{{ $perm->id }}"
                  name="permission_ids[]"
                  value="{{ $perm->id }}"
                  {{ in_array($perm->id, $assignedIds, true) ? 'checked' : '' }}
                  {{ $selectedRole->role_key === 'admin' ? 'disabled' : '' }}
                >
                <label class="custom-control-label" for="perm_{{ $perm->id }}">
                  <strong>{{ $perm->permission_key }}</strong>
                  @if(!empty($perm->permission_group))
                    <span class="text-muted"> — {{ $perm->permission_group }}</span>
                  @endif
                </label>
              </div>
            @empty
              <div class="text-muted">No permissions exist yet.</div>
            @endforelse
          </div>

          <div class="mt-3">
            <button class="btn btn-primary" type="submit" {{ $selectedRole->role_key === 'admin' ? 'disabled' : '' }}>
              <i class="fas fa-save mr-1"></i> Save Permissions
            </button>

            @if($selectedRole->role_key === 'admin')
              <span class="text-muted ml-2">Admin role always has full access; editing permissions is disabled.</span>
            @endif
          </div>
        </form>
      @else
        <div class="text-muted">No role selected.</div>
      @endif

    </div>
  </div>

</div>

{{-- Edit Role Modal --}}
<div class="modal fade" id="editRoleModal" tabindex="-1" role="dialog" aria-labelledby="editRoleModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">

      <div class="modal-header">
        <h5 class="modal-title" id="editRoleModalLabel">Edit Role</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>

      <form method="POST" id="editRoleForm" action="#">
        @csrf
        @method('PUT')

        <div class="modal-body">

          <div class="form-group">
            <label>Role Key</label>
            <input type="text" class="form-control" name="role_key" id="edit_role_key" required>
            <small class="text-muted">Admin role key cannot be changed.</small>
          </div>

          <div class="form-group">
            <label>Role Name</label>
            <input type="text" class="form-control" name="role_name" id="edit_role_name" required>
          </div>

          <div class="form-group">
            <label>Role Description</label>
            <input type="text" class="form-control" name="role_desc" id="edit_role_desc">
          </div>

        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">
            <i class="fas fa-save mr-1"></i> Save Changes
          </button>
        </div>
      </form>

    </div>
  </div>
</div>

{{-- Modal JS --}}
<script>
document.addEventListener('DOMContentLoaded', function () {
  $('#editRoleModal').on('show.bs.modal', function (event) {
    var button = $(event.relatedTarget);

    var roleId   = button.data('role-id');
    var roleKey  = button.data('role-key') || '';
    var roleName = button.data('role-name') || '';
    var roleDesc = button.data('role-desc') || '';

    $('#edit_role_key').val(roleKey);
    $('#edit_role_name').val(roleName);
    $('#edit_role_desc').val(roleDesc);

    // Protect admin role key
    if (roleKey === 'admin') {
      $('#edit_role_key').prop('disabled', true);
    } else {
      $('#edit_role_key').prop('disabled', false);
      // Remove hidden field if previously added
      $('#role_key_hidden_admin').remove();
    }

    var actionTemplate = @json(route('admin.roles.update', ['role' => '__ID__']));
    $('#editRoleForm').attr('action', actionTemplate.replace('__ID__', roleId));
  });

  // If role_key is disabled, it won't submit. Add hidden input on submit.
  $('#editRoleForm').on('submit', function () {
    if ($('#edit_role_key').prop('disabled')) {
      var val = $('#edit_role_key').val();
      if (!document.getElementById('role_key_hidden_admin')) {
        var input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'role_key';
        input.value = val;
        input.id = 'role_key_hidden_admin';
        this.appendChild(input);
      }
    }
  });
});
</script>
@endsection
