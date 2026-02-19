@extends('layouts.app')

@section('title', 'Sales History')

@section('content')
<div class="container-fluid">

  <div class="d-flex align-items-center justify-content-between mb-3">
    <h1 class="h3 mb-0">Sales History</h1>

    <div>
      <a href="{{ route('pos.index') }}" class="btn btn-primary">
        <i class="fas fa-cash-register"></i> Back to POS
      </a>
    </div>
  </div>

  @if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
  @endif
  @if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
  @endif

  <div class="card mb-3">
    <div class="card-header"><strong>Filters</strong></div>
    <div class="card-body">
      <form method="GET" action="{{ route('pos.sales.index') }}">
        <div class="row">
          <div class="col-md-3">
            <label>Search client</label>
            <input type="text" name="search" class="form-control" value="{{ $search }}" placeholder="Client name...">
          </div>

          <div class="col-md-2">
            <label>From</label>
            <input type="date" name="from" class="form-control" value="{{ $from }}">
          </div>

          <div class="col-md-2">
            <label>To</label>
            <input type="date" name="to" class="form-control" value="{{ $to }}">
          </div>

          <div class="col-md-3">
            <label>Payment method</label>
            <select name="payment_method_id" class="form-control">
              <option value="">-- Any --</option>
              @foreach($paymentMethods as $pm)
                <option value="{{ $pm->id }}" @selected((string)$pmId === (string)$pm->id)>
                  {{ $pm->name }}
                </option>
              @endforeach
            </select>
          </div>

          <div class="col-md-2">
            <label>Limit</label>
            <select name="limit" class="form-control">
              <option value="50"  @selected($limit === '50')>50</option>
              <option value="100" @selected($limit === '100')>100</option>
              <option value="200" @selected($limit === '200')>200</option>
              <option value="300" @selected($limit === '300')>300</option>
              <option value="all" @selected($limit === 'all')>All</option>
            </select>
          </div>
        </div>

        <div class="row mt-3">
          <div class="col-md-3">
            <div class="custom-control custom-checkbox">
              <input
                type="checkbox"
                class="custom-control-input"
                id="showVoided"
                name="show_voided"
                value="1"
                @checked($showVoided === '1')
              >
              <label class="custom-control-label" for="showVoided">Show voided</label>
            </div>
          </div>
        </div>

        <div class="mt-3 d-flex gap-2">
          <button class="btn btn-secondary" type="submit">
            <i class="fas fa-filter"></i> Apply
          </button>
          <a class="btn btn-light" href="{{ route('pos.sales.index') }}">
            Reset
          </a>
        </div>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="card-header"><strong>Sales</strong></div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-bordered table-striped mb-0">
          <thead>
            <tr>
              <th style="width:80px;">ID</th>
              <th style="width:170px;">Date</th>
              <th style="width:200px;">Client</th>
              <th>Services</th>
              <th>Products</th>
              <th style="width:120px;" class="text-right">Total (€)</th>
              <th style="width:160px;">Payments</th>
              <th style="width:140px;">Action</th>
            </tr>
          </thead>
          <tbody>
            @forelse($sales as $s)
              @php
                $sid = (int)$s->id;
                $svc = $serviceLinesBySale[$sid] ?? collect();
                $prd = $productLinesBySale[$sid] ?? collect();
                $pays = $paymentsBySale[$sid] ?? collect();
                $isVoided = !empty($s->is_voided);
              @endphp

              <tr class="{{ $isVoided ? 'text-muted' : '' }}">
                <td>
                  {{ $sid }}
                  @if($isVoided)
                    <div><span class="badge badge-secondary">VOIDED</span></div>
                  @endif
                </td>
                <td>{{ $s->display_date->format('Y-m-d H:i') }}</td>
                <td>{{ $s->client_name }}</td>

                <td>
                  @if($svc->count())
                    @foreach($svc as $line)
                      <div>
                        {{ (int)$line->quantity }}× {{ $line->name }}
                        <span class="text-muted">(@ €{{ number_format((float)$line->unit_price, 2) }})</span>
                      </div>
                    @endforeach
                  @else
                    <span class="text-muted">—</span>
                  @endif
                </td>

                <td>
                  @if($prd->count())
                    @foreach($prd as $line)
                      <div>
                        {{ (int)$line->quantity }}× {{ $line->name }}
                        <span class="text-muted">(@ €{{ number_format((float)$line->unit_price, 2) }})</span>
                      </div>
                    @endforeach
                  @else
                    <span class="text-muted">—</span>
                  @endif
                </td>

                <td class="text-right">
                  € {{ number_format((float)$s->grand_total, 2) }}
                </td>

                <td>
                  @if($pays->count())
                    @foreach($pays as $p)
                      <div>
                        {{ $p->method_name ?: '—' }} – €{{ number_format((float)$p->amount, 2) }}
                      </div>
                    @endforeach
                  @else
                    <span class="text-muted">—</span>
                  @endif
                </td>

                <td>
                  <a
                    href="{{ route('pos.receipt', ['sale' => $sid]) }}"
                    target="_blank"
                    class="btn btn-sm btn-secondary"
                    title="Re-print receipt"
                  >
                    <i class="fas fa-print"></i>
                  </a>

                  @if(!$isVoided)
                    <form
                      action="{{ route('pos.sales.void', ['sale' => $sid]) }}"
                      method="POST"
                      class="d-inline"
                      onsubmit="return confirmVoidSale({{ $sid }});"
                    >
                      @csrf
                      <input type="hidden" name="void_reason" id="void_reason_{{ $sid }}" value="">
                      <button type="submit" class="btn btn-sm btn-warning" title="Void sale">
                        <i class="fas fa-ban"></i>
                      </button>
                    </form>
                  @endif
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="8" class="text-center text-muted p-4">
                  No sales found.
                </td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>

    @if($limit !== 'all')
      <div class="card-footer">
        {{ $sales->links() }}
      </div>
    @endif
  </div>

</div>

<script>
  function confirmVoidSale(id) {
    const reason = prompt('VOID sale #' + id + '?\n\nReason (optional):', 'Mistake');
    if (reason === null) return false; // user cancelled
    document.getElementById('void_reason_' + id).value = reason || 'Mistake';
    return confirm('Confirm VOID for sale #' + id + '?');
  }
</script>
@endsection
