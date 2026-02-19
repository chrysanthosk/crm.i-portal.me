@extends('layouts.app')

@section('title', 'Suppliers')

@push('styles')
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap4.min.css"/>
@endpush

@section('content')
<div class="row">
  <div class="col-12">
    <div class="d-flex align-items-center justify-content-between mb-3">
      <h3 class="mb-0">Suppliers</h3>

      <div class="btn-group">
        <button class="btn btn-success" data-toggle="modal" data-target="#addSupplierModal">
          <i class="fas fa-plus-circle"></i> Add
        </button>

        <button class="btn btn-info" data-toggle="modal" data-target="#importSuppliersModal">
          <i class="fas fa-file-import"></i> Import
        </button>

        <a class="btn btn-primary" href="{{ route('suppliers.export') }}">
          <i class="fas fa-file-export"></i> Export
        </a>

        <a class="btn btn-outline-secondary" href="{{ route('suppliers.template') }}">
          <i class="fas fa-download"></i> Template
        </a>

        <button class="btn btn-outline-dark" id="printSuppliersBtn">
          <i class="fas fa-print"></i> Print
        </button>
      </div>
    </div>

    <div class="card">
      <div class="card-body">
        <table id="suppliersTable" class="table table-bordered table-striped">
          <thead>
            <tr>
              <th style="width:70px;">ID</th>
              <th>Name</th>
              <th>Type</th>
              <th>Mobile</th>
              <th>Phone</th>
              <th>Email</th>
              <th>Comment</th>
              <th style="width:120px;">Actions</th>
            </tr>
          </thead>
          <tbody>
            @foreach($suppliers as $s)
              <tr
                data-id="{{ $s->id }}"
                data-name="{{ e($s->name) }}"
                data-type="{{ e($s->type) }}"
                data-mobile="{{ e($s->mobile ?? '') }}"
                data-phone="{{ e($s->phone ?? '') }}"
                data-email="{{ e($s->email ?? '') }}"
                data-comment="{{ e($s->comment ?? '') }}"
              >
                <td>{{ $s->id }}</td>
                <td>{{ $s->name }}</td>
                <td>{{ $s->type }}</td>
                <td>{{ $s->mobile }}</td>
                <td>{{ $s->phone }}</td>
                <td>{{ $s->email }}</td>
                <td>{{ $s->comment }}</td>
                <td>
                  <button class="btn btn-sm btn-info editSupplierBtn" title="Edit">
                    <i class="fas fa-edit"></i>
                  </button>

                  <form method="POST" action="{{ route('suppliers.destroy', $s) }}" class="d-inline"
                        onsubmit="return confirm('Are you sure you want to delete this supplier?');">
                    @csrf
                    @method('DELETE')
                    <button class="btn btn-sm btn-danger" title="Delete">
                      <i class="fas fa-trash"></i>
                    </button>
                  </form>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>

  </div>
</div>

{{-- ADD MODAL --}}
<div class="modal fade" id="addSupplierModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="POST" action="{{ route('suppliers.store') }}">
        @csrf
        <div class="modal-header">
          <h5 class="modal-title">Add Supplier</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>

        <div class="modal-body">
          <div class="form-row">
            <div class="form-group col-md-6">
              <label>Name *</label>
              <input type="text" name="name" class="form-control" required maxlength="255" value="{{ old('name') }}">
            </div>

            <div class="form-group col-md-6">
              <label>Type *</label>
              <input type="text" name="type" class="form-control" required maxlength="100" value="{{ old('type') }}">
            </div>

            <div class="form-group col-md-6">
              <label>Mobile</label>
              <input type="text" name="mobile" class="form-control" maxlength="255" value="{{ old('mobile') }}">
            </div>

            <div class="form-group col-md-6">
              <label>Phone</label>
              <input type="text" name="phone" class="form-control" maxlength="50" value="{{ old('phone') }}">
            </div>

            <div class="form-group col-md-6">
              <label>Email</label>
              <input type="email" name="email" class="form-control" maxlength="255" value="{{ old('email') }}">
            </div>

            <div class="form-group col-md-12">
              <label>Comment</label>
              <textarea name="comment" class="form-control" rows="3">{{ old('comment') }}</textarea>
            </div>
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancel</button>
          <button class="btn btn-success"><i class="fas fa-save"></i> Save</button>
        </div>
      </form>
    </div>
  </div>
</div>

{{-- EDIT MODAL --}}
<div class="modal fade" id="editSupplierModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="POST" id="editSupplierForm" action="#">
        @csrf
        @method('PUT')

        <div class="modal-header">
          <h5 class="modal-title">Edit Supplier</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>

        <div class="modal-body">
          <div class="form-row">
            <div class="form-group col-md-6">
              <label>Name *</label>
              <input type="text" name="name" id="edit_name" class="form-control" required maxlength="255">
            </div>

            <div class="form-group col-md-6">
              <label>Type *</label>
              <input type="text" name="type" id="edit_type" class="form-control" required maxlength="100">
            </div>

            <div class="form-group col-md-6">
              <label>Mobile</label>
              <input type="text" name="mobile" id="edit_mobile" class="form-control" maxlength="255">
            </div>

            <div class="form-group col-md-6">
              <label>Phone</label>
              <input type="text" name="phone" id="edit_phone" class="form-control" maxlength="50">
            </div>

            <div class="form-group col-md-6">
              <label>Email</label>
              <input type="email" name="email" id="edit_email" class="form-control" maxlength="255">
            </div>

            <div class="form-group col-md-12">
              <label>Comment</label>
              <textarea name="comment" id="edit_comment" class="form-control" rows="3"></textarea>
            </div>
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancel</button>
          <button class="btn btn-info"><i class="fas fa-save"></i> Update</button>
        </div>
      </form>
    </div>
  </div>
</div>

{{-- IMPORT MODAL --}}
<div class="modal fade" id="importSuppliersModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" action="{{ route('suppliers.import') }}" enctype="multipart/form-data">
        @csrf
        <div class="modal-header">
          <h5 class="modal-title">Import Suppliers (CSV)</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>

        <div class="modal-body">
          <p class="text-muted mb-2">
            CSV must include header columns:
            <code>name,type,mobile,phone,email,comment</code>
          </p>

          <div class="form-group">
            <label>Select CSV file *</label>
            <input type="file" name="csv_file" class="form-control" accept=".csv,text/csv" required>
          </div>

          <div class="alert alert-info mb-0">
            Tip: Download the template first and fill it.
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancel</button>
          <button class="btn btn-info"><i class="fas fa-upload"></i> Import</button>
        </div>
      </form>
    </div>
  </div>
</div>

@endsection

@push('scripts')
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap4.min.js"></script>

<script>
$(function() {
  $('#suppliersTable').DataTable({
    pageLength: 25,
    lengthMenu: [[25, 50, 100, -1], [25, 50, 100, "All"]],
    order: [[0, 'desc']],
    columnDefs: [{ orderable: false, targets: 7 }]
  });

  // Edit modal fill
  $('#suppliersTable').on('click', '.editSupplierBtn', function() {
    const tr = $(this).closest('tr');

    const id      = tr.data('id');
    const name    = tr.data('name');
    const type    = tr.data('type');
    const mobile  = tr.data('mobile');
    const phone   = tr.data('phone');
    const email   = tr.data('email');
    const comment = tr.data('comment');

    $('#edit_name').val(name);
    $('#edit_type').val(type);
    $('#edit_mobile').val(mobile);
    $('#edit_phone').val(phone);
    $('#edit_email').val(email);
    $('#edit_comment').val(comment);

    // Update form action
    const url = "{{ url('/suppliers') }}/" + id;
    $('#editSupplierForm').attr('action', url);

    $('#editSupplierModal').modal('show');
  });

  // Print Suppliers
  $('#printSuppliersBtn').on('click', function() {
    const tableHtml = document.querySelector('#suppliersTable').outerHTML;
    const win = window.open('', '', 'width=900,height=700');

    win.document.write(`
      <html>
        <head>
          <title>Suppliers List</title>
          <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css"/>
          <style>
            th:last-child, td:last-child { display: none; }
            table { width: 100%; border-collapse: collapse; }
            th, td { border: 1px solid #444; padding: 4px; }
          </style>
        </head>
        <body>
          <div class="container">
            <h3 class="my-3">Suppliers List</h3>
            ${tableHtml}
          </div>
        </body>
      </html>
    `);

    win.document.close();
    win.focus();

    setTimeout(() => win.print(), 250);
  });
});
</script>
@endpush
