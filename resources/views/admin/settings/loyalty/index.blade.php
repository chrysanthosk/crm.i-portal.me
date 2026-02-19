@extends('layouts.app')
@section('title','Loyalty & Rewards')

@push('styles')
<link href="https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/css/select2.min.css" rel="stylesheet"/>
<style>
  .nav-tabs .nav-link { cursor: pointer; }
  body.dark-mode .modal-content { background: #343a40; color: rgba(255,255,255,.9); }
  body.dark-mode .modal-header, body.dark-mode .modal-footer { border-color: rgba(255,255,255,.12); }
</style>
@endpush

@section('content')
<div class="container-fluid">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">Loyalty &amp; Rewards</h1>
  </div>

  <div class="card">
    <div class="card-body">

      <ul class="nav nav-tabs mb-3" id="loyaltyTabs">
        <li class="nav-item">
          <a class="nav-link active" data-toggle="tab" href="#tabTiers">Tier Configuration</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" data-toggle="tab" href="#tabClients">Client Balances</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" data-toggle="tab" href="#tabSettings">Settings</a>
        </li>
      </ul>

      <div class="tab-content">

        {{-- TIERS --}}
        <div class="tab-pane fade show active" id="tabTiers">
          <button class="btn btn-primary mb-2" type="button" onclick="openTierModal()">
            <i class="fas fa-plus"></i> Add Tier
          </button>

          <div class="table-responsive">
            <table class="table table-bordered">
              <thead>
                <tr>
                  <th style="width:90px;">Order</th>
                  <th>Name</th>
                  <th style="width:130px;">Min Points</th>
                  <th>Benefits</th>
                  <th style="width:220px;">Action</th>
                </tr>
              </thead>
              <tbody>
                @forelse($tiers as $t)
                  <tr
                    data-id="{{ $t->id }}"
                    data-name="{{ $t->name }}"
                    data-points_min="{{ $t->points_min }}"
                    data-benefits="{{ $t->benefits }}"
                    data-sort_order="{{ $t->sort_order }}"
                  >
                    <td>{{ $t->sort_order }}</td>
                    <td>{{ $t->name }}</td>
                    <td>{{ (int)$t->points_min }}</td>
                    <td>{{ $t->benefits }}</td>
                    <td>
                      <button class="btn btn-sm btn-info" type="button" onclick="openTierModal({{ $t->id }})">Edit</button>

                      <form method="POST" action="{{ route('settings.loyalty.tiers.delete', $t->id) }}"
                            class="d-inline" onsubmit="return confirm('Delete this tier?')">
                        @csrf @method('DELETE')
                        <button class="btn btn-sm btn-danger" type="submit">Delete</button>
                      </form>
                    </td>
                  </tr>
                @empty
                  <tr><td colspan="5" class="text-center text-muted p-4">No tiers yet.</td></tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </div>

        {{-- CLIENTS --}}
        <div class="tab-pane fade" id="tabClients">
          <button class="btn btn-secondary mb-2" type="button" onclick="openAdjustModal()">
            <i class="fas fa-sliders-h"></i> Manual Adjustment
          </button>

          <div class="table-responsive">
            <table class="table table-bordered">
              <thead>
                <tr>
                  <th>Client</th>
                  <th style="width:140px;">Points</th>
                  <th style="width:160px;">Tier</th>
                  <th style="width:160px;">Action</th>
                </tr>
              </thead>
              <tbody>
                @forelse($clients as $c)
                  <tr data-client="{{ $c->id }}" data-points="{{ (int)$c->points }}">
                    <td>{{ $c->client_name }}</td>
                    <td>{{ (int)$c->points }}</td>
                    <td>{{ $c->tier }}</td>
                    <td>
                      <button class="btn btn-sm btn-primary" type="button" onclick="openAdjustModal({{ $c->id }})">
                        Adjust
                      </button>
                    </td>
                  </tr>
                @empty
                  <tr><td colspan="4" class="text-center text-muted p-4">No clients found.</td></tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </div>

        {{-- SETTINGS --}}
        <div class="tab-pane fade" id="tabSettings">
          <form method="POST" action="{{ route('settings.loyalty.settings.save') }}">
            @csrf
            <div class="form-group">
              <label>Points per €1 spent</label>
              <input type="number" step="0.01" name="points_per_euro" class="form-control"
                     required value="{{ old('points_per_euro', $pointsPerEuro) }}">
            </div>
            <button class="btn btn-success"><i class="fas fa-save"></i> Save Settings</button>
          </form>
        </div>

      </div>
    </div>
  </div>
</div>

{{-- Tier Modal --}}
<div class="modal fade" id="tierModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <form method="POST" action="{{ route('settings.loyalty.tiers.save') }}" class="modal-content">
      @csrf
      <input type="hidden" name="id" id="tier_id">
      <div class="modal-header">
        <h5 class="modal-title">Tier</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>

      <div class="modal-body">
        <div class="form-group">
          <label>Name</label>
          <input name="name" id="tier_name" class="form-control" required maxlength="50">
        </div>
        <div class="form-group">
          <label>Min Points</label>
          <input type="number" name="points_min" id="tier_min" class="form-control" required min="0">
        </div>
        <div class="form-group">
          <label>Benefits</label>
          <input name="benefits" id="tier_ben" class="form-control" maxlength="255">
        </div>
        <div class="form-group">
          <label>Sort Order</label>
          <input type="number" name="sort_order" id="tier_order" class="form-control" value="0">
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
        <button class="btn btn-primary" type="submit">Save Tier</button>
      </div>
    </form>
  </div>
</div>

{{-- Adjust Modal --}}
<div class="modal fade" id="adjustModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <form method="POST" action="{{ route('settings.loyalty.adjust') }}" class="modal-content">
      @csrf
      <div class="modal-header">
        <h5 class="modal-title">Adjust Points</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>

      <div class="modal-body">
        <div class="form-group">
          <label>Client</label>
          <select name="client_id" id="adj_client_select" class="form-control" required>
            <option value=""></option>
            @foreach($clients as $c)
              <option value="{{ $c->id }}" data-points="{{ (int)$c->points }}">
                {{ $c->client_name }} ({{ (int)$c->points }} pts)
              </option>
            @endforeach
          </select>
        </div>

        <div class="form-group">
          <label>Current Balance</label>
          <input id="adj_current_balance" class="form-control" readonly>
        </div>

        <div class="form-group">
          <label>Change (± points)</label>
          <input type="number" name="change" id="adj_points" class="form-control" required>
        </div>

        <div class="form-group">
          <label>Reason</label>
          <input name="reason" id="adj_reason" class="form-control" placeholder="e.g. manual adjustment" required maxlength="100">
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
        <button class="btn btn-primary" type="submit">Apply</button>
      </div>
    </form>
  </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/js/select2.min.js"></script>
<script>
$(function(){
  // select2
  $('#adj_client_select').select2({
    width: '100%',
    placeholder: 'Search & select client…',
    dropdownParent: $('#adjustModal')
  }).on('change', function(){
    const pts = $('option:selected', this).data('points') || 0;
    $('#adj_current_balance').val(pts);
  });

  // persist tab with hash
  if (location.hash) {
    $('a[href="' + location.hash + '"]').tab('show');
  }
  $('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
    history.replaceState(null, null, $(e.target).attr('href'));
  });
});

window.openTierModal = function(id){
  const $modal = $('#tierModal');

  if (!id) {
    $('#tier_id').val('');
    $('#tier_name').val('');
    $('#tier_min').val(0);
    $('#tier_ben').val('');
    $('#tier_order').val(0);
    $modal.modal('show');
    return;
  }

  const $row = $('tr[data-id="'+id+'"]');
  $('#tier_id').val(id);
  $('#tier_name').val($row.data('name'));
  $('#tier_min').val($row.data('points_min'));
  $('#tier_ben').val($row.data('benefits'));
  $('#tier_order').val($row.data('sort_order'));
  $modal.modal('show');
};

window.openAdjustModal = function(clientId){
  $('#adj_current_balance').val('');
  if (clientId) {
    $('#adj_client_select').val(clientId).trigger('change');
  } else {
    $('#adj_client_select').val('').trigger('change');
  }
  $('#adjustModal').modal('show');
};
</script>
@endpush
