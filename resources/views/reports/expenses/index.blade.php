@extends('layouts.app')

@section('content')
@php
  $m = $month; // YYYY-MM
@endphp

<datalist id="expenseNames">
  @foreach($allNames as $n)
    <option value="{{ $n }}"></option>
  @endforeach
</datalist>

<div class="container-fluid">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">Expenses</h1>

    <div class="d-flex align-items-center gap-2">
      <button id="prevM" class="btn btn-sm btn-outline-secondary">&larr; Prev</button>

      <input type="month" id="mPicker" class="form-control form-control-sm"
             style="width:auto" value="{{ $m }}">

      <button id="nextM" class="btn btn-sm btn-outline-secondary">Next &rarr;</button>

      <button id="btnImport" class="btn btn-sm btn-primary ms-3">
        <i class="fas fa-file-import"></i> Import
      </button>
    </div>
  </div>

  <button id="addRow" class="btn btn-primary mb-2">+ Add Row</button>

  <div class="table-responsive">
    <table id="expTbl" class="table table-bordered">
      <thead>
        <tr>
          <th style="min-width:140px;">Date</th>
          <th style="min-width:220px;">Name</th>
          <th style="min-width:120px;">Amt (€)</th>
          <th style="min-width:160px;">Payment</th>
          <th style="min-width:140px;">Cheque No</th>
          <th style="min-width:240px;">Invoice/Reason</th>
        </tr>
      </thead>
      <tbody>
      @foreach($expenses as $e)
        <tr data-id="{{ $e->id }}">
          <td><input type="date" class="form-control form-control-sm e-date" value="{{ $e->date->toDateString() }}"></td>
          <td>
            <input list="expenseNames" class="form-control form-control-sm e-name"
                   value="{{ $e->name }}" placeholder="Start typing…">
          </td>
          <td><input type="number" step="0.01" class="form-control form-control-sm e-amt" value="{{ number_format((float)$e->amount_paid,2,'.','') }}"></td>
          <td><input type="text" class="form-control form-control-sm e-type" value="{{ $e->payment_type }}" placeholder="e.g. Cash, Sepa…"></td>
          <td><input type="text" class="form-control form-control-sm e-cheque" value="{{ $e->cheque_no }}"></td>
          <td><input type="text" class="form-control form-control-sm e-reason" value="{{ $e->invoice_reason }}"></td>
        </tr>
      @endforeach
      </tbody>
      <tfoot>
        <tr>
          <th colspan="2" class="text-end">Monthly Total (€):</th>
          <th id="expMonthTotal" class="text-end">0.00</th>
          <th colspan="3"></th>
        </tr>
      </tfoot>
    </table>
  </div>

  <button id="saveExp" class="btn btn-success">Save Expenses</button>
  <div id="expMsg" class="alert alert-success mt-2 d-none">Saved.</div>
</div>

{{-- Import Modal --}}
<div class="modal fade" id="importModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Import Expenses CSV</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">

        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label">Import Month</label>
            <input type="month" id="importMonth" class="form-control form-control-sm" required>
          </div>
          <div class="col-md-8">
            <label class="form-label">Select CSV File</label>
            <input type="file" id="csvFile" class="form-control form-control-sm" accept=".csv">
            <small class="text-muted">
              Header must include: Date, Name, Amount Paid (amount), Type, Cheque No, Reason
            </small>
          </div>
        </div>

        <hr>

        <h6>Preview</h6>
        <div class="table-responsive">
          <table id="previewTable" class="table table-sm table-bordered d-none">
            <thead>
              <tr>
                <th>Date</th><th>Name</th><th>Amt</th><th>Type</th><th>Cheque No</th><th>Reason</th>
              </tr>
            </thead>
            <tbody></tbody>
          </table>
        </div>

      </div>
      <div class="modal-footer">
        <button id="btnConfirmImport" class="btn btn-primary" disabled>Confirm Import</button>
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
      </div>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script>
(function(){
  const csrf = @json(csrf_token());
  const month = @json($m);

  function go(m){ window.location.href = `?m=${m}`; }
  function pad2(n){ return (n<10?'0':'') + n; }

  document.getElementById('mPicker').addEventListener('change', (e)=>go(e.target.value));

  document.getElementById('prevM').addEventListener('click', ()=>{
    let [y,M] = document.getElementById('mPicker').value.split('-').map(Number);
    M--; if(M<1){M=12;y--;}
    go(`${y}-${pad2(M)}`);
  });
  document.getElementById('nextM').addEventListener('click', ()=>{
    let [y,M] = document.getElementById('mPicker').value.split('-').map(Number);
    M++; if(M>12){M=1;y++;}
    go(`${y}-${pad2(M)}`);
  });

  function recalcTotal(){
    let sum = 0;
    document.querySelectorAll('#expTbl tbody .e-amt').forEach(inp=>{
      sum += parseFloat(inp.value) || 0;
    });
    document.getElementById('expMonthTotal').textContent = sum.toFixed(2);
  }
  recalcTotal();
  document.getElementById('expTbl').addEventListener('input', (e)=>{
    if(e.target.classList.contains('e-amt')) recalcTotal();
  });

  document.getElementById('addRow').addEventListener('click', ()=>{
    const tbody = document.querySelector('#expTbl tbody');
    const tr = document.createElement('tr');
    tr.dataset.id = '';
    tr.innerHTML = `
      <td><input type="date" class="form-control form-control-sm e-date"></td>
      <td><input list="expenseNames" class="form-control form-control-sm e-name" placeholder="Start typing…"></td>
      <td><input type="number" step="0.01" class="form-control form-control-sm e-amt"></td>
      <td><input type="text" class="form-control form-control-sm e-type" placeholder="e.g. Cash, Sepa…"></td>
      <td><input type="text" class="form-control form-control-sm e-cheque"></td>
      <td><input type="text" class="form-control form-control-sm e-reason"></td>
    `;
    tbody.appendChild(tr);
    recalcTotal();
  });

  async function postJson(url, payload){
    const res = await fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type':'application/json',
        'X-CSRF-TOKEN': csrf,
        'Accept':'application/json',
      },
      body: JSON.stringify(payload)
    });
    if(!res.ok){
      const data = await res.json().catch(()=>({}));
      throw data;
    }
    return res.json().catch(()=>({ok:true}));
  }

  document.getElementById('saveExp').addEventListener('click', async ()=>{
    const rows = Array.from(document.querySelectorAll('#expTbl tbody tr')).map(tr=>{
      return {
        id: tr.dataset.id ? parseInt(tr.dataset.id,10) : null,
        date: tr.querySelector('.e-date')?.value || null,
        name: tr.querySelector('.e-name')?.value || '',
        amount_paid: parseFloat(tr.querySelector('.e-amt')?.value) || 0,
        payment_type: tr.querySelector('.e-type')?.value || null,
        cheque_no: tr.querySelector('.e-cheque')?.value || null,
        invoice_reason: tr.querySelector('.e-reason')?.value || null
      };
    });

    try{
      await postJson(@json(route('reports.financial.expenses.save')), rows);
      const msg = document.getElementById('expMsg');
      msg.classList.remove('d-none');
      setTimeout(()=>msg.classList.add('d-none'), 1800);
      setTimeout(()=>window.location.reload(), 500);
    }catch(err){
      console.error(err);
      alert('Save failed');
    }
  });

  // Import
  let imported = [];
  const modalEl = document.getElementById('importModal');
  const modal = new bootstrap.Modal(modalEl);

  document.getElementById('btnImport').addEventListener('click', ()=>{
    imported = [];
    document.getElementById('csvFile').value = '';
    const pt = document.getElementById('previewTable');
    pt.classList.add('d-none');
    pt.querySelector('tbody').innerHTML = '';
    document.getElementById('btnConfirmImport').disabled = true;
    document.getElementById('importMonth').value = document.getElementById('mPicker').value;
    modal.show();
  });

  function parseCSVLine(str){
    return str.split(/,(?=(?:[^"]*"[^"]*")*[^"]*$)/)
      .map(c=>c.replace(/^"|"$/g,'').trim());
  }

  document.getElementById('csvFile').addEventListener('change', (e)=>{
    const file = e.target.files[0];
    if(!file) return;

    const rdr = new FileReader();
    rdr.onload = (ev)=>{
      const lines = String(ev.target.result || '').trim().split(/\r?\n/);
      if(lines.length < 2) return alert('CSV has no data rows');

      let hi = -1, headers = [];
      lines.forEach((ln,i)=>{
        const h = parseCSVLine(ln).map(x=>x.toLowerCase());
        if(hi < 0 && h.includes('date')) { hi=i; headers=h; }
      });
      if(hi < 0) return alert('No header row with "Date" column found');

      const idx = {
        date: headers.indexOf('date'),
        name: headers.indexOf('name'),
        amt: headers.findIndex(h=>h.includes('amount paid') || h === 'amt' || h === 'amount'),
        type: headers.indexOf('type'),
        cheque: headers.indexOf('cheque no'),
        reason: headers.indexOf('reason')
      };

      imported = lines.slice(hi+1).map(l=>{
        const c = parseCSVLine(l);
        let iso=null, raw = c[idx.date] || '';
        if(raw.includes('/')){
          let [D,M,Y] = raw.split('/').map(s=>String(s).trim().padStart(2,'0'));
          iso = `${Y}-${M}-${D}`;
        } else {
          const dt = new Date(raw);
          if(!isNaN(dt)) iso = dt.toISOString().slice(0,10);
        }
        return {
          date: iso,
          name: (c[idx.name]||'').trim(),
          amount_paid: parseFloat((c[idx.amt]||'').replace(/[^0-9.\-]/g,'')) || 0,
          payment_type: (c[idx.type]||'cash').trim(),
          cheque_no: (c[idx.cheque]||'').trim(),
          invoice_reason: (c[idx.reason]||'').trim()
        };
      }).filter(r=>r.date);

      const pt = document.getElementById('previewTable');
      pt.classList.remove('d-none');
      const tb = pt.querySelector('tbody');
      tb.innerHTML = '';
      imported.forEach(r=>{
        const dn = new Date(r.date).toLocaleDateString(undefined,{weekday:'short',day:'numeric',month:'short'});
        tb.insertAdjacentHTML('beforeend', `
          <tr>
            <td>${dn}</td>
            <td>${r.name}</td>
            <td>${Number(r.amount_paid).toFixed(2)}</td>
            <td>${r.payment_type}</td>
            <td>${r.cheque_no}</td>
            <td>${r.invoice_reason}</td>
          </tr>
        `);
      });

      document.getElementById('btnConfirmImport').disabled = imported.length === 0;
    };
    rdr.readAsText(file);
  });

  document.getElementById('btnConfirmImport').addEventListener('click', async ()=>{
    try{
      await postJson(
        @json(route('reports.financial.expenses.import', ['m' => '__M__'])).replace('__M__', document.getElementById('importMonth').value),
        imported
      );
      window.location.reload();
    }catch(err){
      console.error(err);
      alert('Import failed');
    }
  });
})();
</script>
@endpush
