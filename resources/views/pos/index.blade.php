@extends('layouts.app')

@section('title', 'POS')

@push('styles')
<link href="https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/css/select2.min.css" rel="stylesheet"/>

<style>
  #cartTable tfoot th { background: rgba(0,0,0,.03); }
  body.dark-mode #cartTable tfoot th { background: rgba(255,255,255,.06); }

  /* --- Select2: AdminLTE form-control (LIGHT) --- */
  .select2-container { width: 100% !important; }
  .select2-container--default .select2-selection--single{
    height: calc(2.25rem + 2px);
    border: 1px solid #ced4da;
    border-radius: .25rem;
    padding: .375rem .75rem;
    background-color: #fff;
    color: #495057;
  }
  .select2-container--default .select2-selection--single .select2-selection__rendered{
    line-height: 1.5;
    padding-left: 0;
    padding-right: 1.25rem;
    color: inherit;
  }
  .select2-container--default .select2-selection--single .select2-selection__arrow{
    height: calc(2.25rem + 2px);
    right: .5rem;
  }
  .select2-container--default .select2-dropdown{
    border: 1px solid #ced4da;
    border-radius: .25rem;
    background: #fff;
    color: #495057;
  }
  .select2-container--default .select2-search--dropdown .select2-search__field{
    border: 1px solid #ced4da;
    border-radius: .25rem;
    padding: .375rem .75rem;
  }
  .select2-container--default .select2-results__option--highlighted[aria-selected]{
    background-color: #007bff;
    color: #fff;
  }

  /* --- Select2: DARK MODE overrides --- */
  body.dark-mode .select2-container--default .select2-selection--single{
    background-color: #343a40;
    border-color: rgba(255,255,255,.12);
    color: rgba(255,255,255,.9);
  }
  body.dark-mode .select2-container--default .select2-selection--single .select2-selection__rendered{
    color: rgba(255,255,255,.9);
  }
  body.dark-mode .select2-container--default .select2-selection--single .select2-selection__arrow b{
    border-color: rgba(255,255,255,.7) transparent transparent transparent;
  }
  body.dark-mode .select2-container--default .select2-dropdown{
    background: #343a40;
    border-color: rgba(255,255,255,.12);
    color: rgba(255,255,255,.9);
  }
  body.dark-mode .select2-container--default .select2-search--dropdown .select2-search__field{
    background: #2f343a;
    border-color: rgba(255,255,255,.12);
    color: rgba(255,255,255,.9);
  }
  body.dark-mode .select2-container--default .select2-results__option{
    color: rgba(255,255,255,.9);
  }
  body.dark-mode .select2-container--default .select2-results__option[aria-selected="true"]{
    background: rgba(255,255,255,.08);
  }
  body.dark-mode .select2-container--default .select2-results__option--highlighted[aria-selected]{
    background-color: #007bff;
    color: #fff;
  }
</style>
@endpush

@section('content')
<div class="container-fluid">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h1 class="h3 mb-0">Point of Sale</h1>
  </div>

  <div class="row">
    <!-- LEFT: ADD ITEM -->
    <div class="col-md-5">
      <div class="card">
        <div class="card-header"><strong>Add Item</strong></div>
        <div class="card-body">
          <form id="addItemForm" onsubmit="return false;">
            <div class="form-group">
              <label>Type</label>
              <select id="itemType" class="form-control">
                <option value="product">Product</option>
                <option value="service">Service</option>
                <option value="appointment">Appointment</option>
              </select>
            </div>

            <div class="form-group">
              <label>Item</label>
              <select id="itemSelect" class="form-control"></select>
            </div>

            <div class="form-group">
              <label>Quantity</label>
              <input type="number" id="itemQty" class="form-control" min="1" value="1">
              <div class="text-muted small mt-1">Appointments always add quantity = 1.</div>
            </div>

            <button id="btnAddItem" class="btn btn-primary btn-block">
              <i class="fas fa-cart-plus"></i> Add to Cart
            </button>
          </form>
        </div>
      </div>
    </div>

    <!-- RIGHT: CART & CHECKOUT -->
    <div class="col-md-7">
      <div class="card">
        <div class="card-header"><strong>Cart</strong></div>
        <div class="card-body p-0">
          <table id="cartTable" class="table table-bordered mb-0">
            <thead>
              <tr>
                <th style="width:50px;">#</th>
                <th style="width:120px;">Type</th>
                <th>Name</th>
                <th style="width:80px;">Qty</th>
                <th style="width:110px;">Unit (€)</th>
                <th style="width:110px;">Line (€)</th>
                <th style="width:60px;"></th>
              </tr>
            </thead>
            <tbody></tbody>
            <tfoot>
              <tr>
                <th colspan="5" class="text-right">VAT (€):</th>
                <th id="cartVat">0.00</th>
                <th></th>
              </tr>
              <tr>
                <th colspan="5" class="text-right">Grand Total (€):</th>
                <th id="cartTotal">0.00</th>
                <th></th>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>

      <div class="card">
        <div class="card-header"><strong>Payment</strong></div>
        <div class="card-body">
          <form id="checkoutForm">
            @csrf
            <input type="hidden" name="items_json" id="itemsJson">

            <div class="form-group">
              <label>Client</label>
              <select name="client_id" id="clientSelect" class="form-control">
                <option value="">Walk-in</option>
                @foreach($clients as $c)
                  <option value="{{ $c->id }}">{{ $c->name }}</option>
                @endforeach
              </select>
            </div>

            <div class="form-group">
              <label>Staff</label>
              <select name="staff_id" class="form-control" required>
                <option value="">-- Select --</option>
                @foreach($staff as $t)
                  <option value="{{ $t->id }}">{{ $t->name }}</option>
                @endforeach
              </select>
            </div>

            <div class="form-group">
              <label>Payment Method</label>
              <select name="payment_method_id" class="form-control" required>
                <option value="">-- Select --</option>
                @foreach($payments as $pm)
                  <option value="{{ $pm->id }}">{{ $pm->name }}</option>
                @endforeach
              </select>
            </div>

            <div class="form-group">
              <label>Amount Tendered (€)</label>
              <input type="number" step="0.01" name="amount_paid" id="amountPaid" class="form-control" required>
            </div>

            <div class="form-group">
              <label>Change (€)</label>
              <input type="text" id="changeDue" class="form-control" readonly>
            </div>

            <button type="submit" class="btn btn-success btn-block">
              <i class="fas fa-check-circle"></i> Complete Sale
            </button>
          </form>

          <div class="text-muted small mt-2">
            If the cart contains an appointment, POS will link the sale to that appointment automatically.
          </div>
        </div>
      </div>

    </div>
  </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/js/select2.min.js"></script>
<script>
$(function () {
  const products     = @json($products);
  const services     = @json($services);
  const appointments = @json($appointments);

  $('#itemSelect, #clientSelect').select2({
    width: '100%',
    placeholder: 'Search…'
  });

  function populateItems(type) {
    let list = [];
    if (type === 'product') list = products;
    else if (type === 'service') list = services;
    else if (type === 'appointment') list = appointments;

    const $sel = $('#itemSelect').empty();

    list.forEach(it => {
      let text, value;

      if (type === 'appointment') {
        text  = `Appt: ${it.client_name} — ${it.service_name} (${it.appointment_date} ${it.start_time})`;
        value = `appointment-${it.id}`;

        $(new Option(text, value, false, false))
          .data('price', it.sell_price)
          .data('vat', it.vat_percent)
          .data('appt_id', it.id)
          .data('service_id', it.service_id)
          .appendTo($sel);
      } else {
        text  = `${it.name} (€${parseFloat(it.sell_price).toFixed(2)})`;
        value = `${type}-${it.id}`;

        $(new Option(text, value, false, false))
          .data('price', it.sell_price)
          .data('vat', it.vat_percent)
          .appendTo($sel);
      }
    });

    $sel.trigger('change');
  }

  $('#itemType').on('change', function () {
    populateItems(this.value);
  }).trigger('change');

  let cart = [];

  function renderCart() {
    const $b = $('#cartTable tbody').empty();
    let vatTotal = 0, grandTotal = 0;

    cart.forEach((it, i) => {
      const unitInc = it.price;
      const vatPct  = it.vat / 100;
      const net     = unitInc / (1 + vatPct);
      const tax     = unitInc - net;

      const lineTot = unitInc * it.qty;
      vatTotal += tax * it.qty;
      grandTotal += lineTot;

      $b.append(`
        <tr>
          <td>${i+1}</td>
          <td>${it.type}</td>
          <td>${$('<div/>').text(it.name).html()}</td>
          <td>${it.qty}</td>
          <td>${unitInc.toFixed(2)}</td>
          <td>${lineTot.toFixed(2)}</td>
          <td>
            <button class="btn btn-sm btn-danger" type="button" onclick="removeItem(${i})">
              <i class="fas fa-trash"></i>
            </button>
          </td>
        </tr>
      `);
    });

    $('#cartVat').text(vatTotal.toFixed(2));
    $('#cartTotal').text(grandTotal.toFixed(2));

    const paid = parseFloat($('#amountPaid').val()) || 0;
    $('#changeDue').val((paid - grandTotal).toFixed(2));
  }

  window.removeItem = (i) => { cart.splice(i, 1); renderCart(); };

  $('#btnAddItem').on('click', function () {
    const val = $('#itemSelect').val();
    if (!val) return;

    const sel = $('#itemSelect option:selected');
    const parts = val.split('-');
    const type = parts[0];
    const id = parts.slice(1).join('-');

    const qty = (type === 'appointment') ? 1 : (parseInt($('#itemQty').val(), 10) || 1);

    const item = {
      type,
      id,
      name: sel.text(),
      qty,
      price: parseFloat(sel.data('price')),
      vat: parseFloat(sel.data('vat'))
    };

    if (type === 'appointment') {
      item.appt_id = sel.data('appt_id');
      item.service_id = sel.data('service_id');
    }

    cart.push(item);
    renderCart();
  });

  $('#amountPaid').on('input', renderCart);

  $('#checkoutForm').on('submit', function (e) {
    e.preventDefault();
    if (!cart.length) return alert('Cart is empty.');

    $('#itemsJson').val(JSON.stringify(cart));

    const $btn = $(this).find('button[type="submit"]').prop('disabled', true);

    $.ajax({
      url: "{{ route('pos.checkout') }}",
      method: "POST",
      data: $(this).serialize(),
    })
    .done(function (resp) {
      if (resp && resp.sale_id) {
        // Try to open receipt in a new tab/window
        let win = null;
        if (resp.receipt_url) {
          win = window.open(resp.receipt_url, '_blank');
        }

        // If popup blocked, show link
        if (resp.receipt_url && (!win || win.closed || typeof win.closed === 'undefined')) {
          alert(
            'Sale completed. Sale ID: ' + resp.sale_id +
            "\n\nPopup blocked. Open receipt manually:\n" + resp.receipt_url
          );
        } else {
          alert('Sale completed. Sale ID: ' + resp.sale_id);
        }

        location.reload();
      } else {
        alert('Saved but response was unexpected.');
        $btn.prop('disabled', false);
      }
    })
    .fail(function (xhr) {
      alert(xhr.responseJSON?.error || xhr.responseText || 'Save failed');
      $btn.prop('disabled', false);
    });
  });
});
</script>
@endpush
