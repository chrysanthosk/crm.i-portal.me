@extends('layouts.app')

@section('title', 'Bulk SMS')

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card">

            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-sms mr-1"></i> Bulk SMS (Send Now)
                </h3>
            </div>

            <div class="card-body">
                <form method="POST" action="{{ route('bulk_sms.send') }}" id="bulkSmsForm">
                    @csrf

                    <div class="form-row">
                        <div class="form-group col-md-4">
                            <label>SMS Provider <span class="text-danger">*</span></label>
                            <select name="provider_id" class="form-control @error('provider_id') is-invalid @enderror" required>
                                <option value="">— Select —</option>
                                @foreach($providers as $p)
                                    <option value="{{ $p->id }}" {{ (string)old('provider_id') === (string)$p->id ? 'selected' : '' }}>
                                        {{ $p->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('provider_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="form-text text-muted">
                                Must be active and enabled in SMS settings.
                            </small>
                        </div>

                        <div class="form-group col-md-4">
                            <label>Manual Number</label>
                            <input type="text"
                                   name="manual_number"
                                   class="form-control @error('manual_number') is-invalid @enderror"
                                   value="{{ old('manual_number') }}"
                                   placeholder="+35799123456">
                            @error('manual_number')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="form-text text-muted">
                                Optional. If you enter an 8-digit Cyprus number, it becomes +357XXXXXXXX.
                            </small>
                        </div>

                        <div class="form-group col-md-4">
                            <label>Message <span class="text-danger">*</span></label>
                            <textarea name="message"
                                      id="message"
                                      class="form-control @error('message') is-invalid @enderror"
                                      rows="3"
                                      maxlength="165"
                                      required>{{ old('message') }}</textarea>
                            @error('message')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="d-block mt-1">
                                <span id="charCount">0</span>/165
                            </small>
                        </div>
                    </div>

                    <hr>

                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <h5 class="mb-0">Select Clients</h5>
                        <div class="text-muted small">Use search to filter clients quickly.</div>
                    </div>

                    <div class="table-responsive">
                        <table id="clientsTable" class="table table-striped table-bordered">
                            <thead>
                                <tr>
                                    <th style="width:40px;" class="text-center">
                                        <input type="checkbox" id="selectAll">
                                    </th>
                                    <th>Name</th>
                                    <th>Mobile</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php
                                    $selectedClients = old('clients', []);
                                    if (!is_array($selectedClients)) { $selectedClients = []; }
                                    $selectedClients = array_map('strval', $selectedClients);
                                @endphp

                                @foreach($clients as $c)
                                    @php
                                        $fullName = trim(($c->first_name ?? '').' '.($c->last_name ?? ''));
                                        $isChecked = in_array((string)$c->id, $selectedClients, true);
                                    @endphp
                                    <tr>
                                        <td class="text-center">
                                            <input type="checkbox"
                                                   name="clients[]"
                                                   value="{{ $c->id }}"
                                                   class="client-checkbox"
                                                   {{ $isChecked ? 'checked' : '' }}>
                                        </td>
                                        <td>{{ $fullName }}</td>
                                        <td>{{ $c->mobile }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="text-right mt-3">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-paper-plane"></i> Send SMS
                        </button>
                    </div>
                </form>
            </div>

        </div>
    </div>
</div>
@endsection

@push('styles')
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap4.min.css"/>
@endpush

@push('scripts')
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap4.min.js"></script>

<script>
$(function () {
    function updateCharCount() {
        const v = $('#message').val() || '';
        $('#charCount').text(v.length);
    }
    updateCharCount();
    $('#message').on('input', updateCharCount);

    $('#clientsTable').DataTable({
        pageLength: 25,
        order: [[1, 'asc']],
        columnDefs: [{ orderable: false, targets: 0 }]
    });

    $('#selectAll').on('change', function () {
        $('.client-checkbox').prop('checked', this.checked);
    });

    $(document).on('change', '.client-checkbox', function () {
        const total = $('.client-checkbox').length;
        const checked = $('.client-checkbox:checked').length;
        $('#selectAll').prop('checked', total > 0 && total === checked);
    });

    // Initialize selectAll state
    const total = $('.client-checkbox').length;
    const checked = $('.client-checkbox:checked').length;
    $('#selectAll').prop('checked', total > 0 && total === checked);
});
</script>
@endpush
