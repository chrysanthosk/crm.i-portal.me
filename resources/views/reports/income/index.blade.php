@extends('layouts.app')

@section('content')
@php $m = $month; @endphp

<div class="container-fluid">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">Income</h1>

    <div class="d-flex align-items-center gap-2">
      <button id="prevMonth" class="btn btn-sm btn-outline-secondary">&larr; Prev</button>

      <input type="month" id="monthPicker" class="form-control form-control-sm" style="width:auto"
             value="{{ $m }}">

      <button id="nextMonth" class="btn btn-sm btn-outline-secondary">Next &rarr;</button>

      <button id="btnImport" class="btn btn-sm btn-primary ms-3">
        <i class="fas fa-file-import"></i> Import
      </button>
    </div>
  </div>

  <div class="table-responsive">
    <table class="table table-bordered" id="incomeTbl">
      <thead>
        <tr>
          <th style="min-width:160px;">Date</th>
          <th style="min-width:120px;">Cash (€)</th>
          <th style="min-width:120px;">Revolut (€)</th>
          <th style="min-width:120px;">Visa (€)</th>
          <th style="min-width:120px;">Other (€)</th>
          <th style="min-width:140px;">Daily Total (€)</th>
        </tr>
      </thead>
      <tbody>
      @foreach($dates as $d)
        @php
          $r = $rows[$d] ?? null;
          $cash = (float)($r->cash ?? 0);
          $rev  = (float)($r->revolut ?? 0);
          $visa = (float)($r->visa ?? 0);
          $oth  = (float)($r->other ?? 0);
          $sum  = $cash+$rev+$visa+$oth;
        @endphp
        <tr data-date="{{ $d }}">
          <td>{{ \Carbon\Carbon::parse($d)->format('D, j M') }}</td>
          <td><input type="number" step="0.01" class="form-control form-control-sm income-cash" value="{{ number_format($cash,2,'.','') }}"></td>
          <td><input type="number" step="0.01" class="form-control form-control-sm income-revolut" value="{{ number_format($rev,2,'.','') }}"></td>
          <td><input type="number" step="0.01" class="form-control form-control-sm income-visa" value="{{ number_format($visa,2,'.','') }}"></td>
          <td><input type="number" step="0.01" class="form-control form-control-sm income-other" value="{{ number_format($oth,2,'.','') }}"></td>
          <td class="daily-total text-end">{{ number_format($sum,2) }}</td>
        </tr>
      @endforeach
      </tbody>
      <tfoot>
        <tr>
          <th colspan="5" class="text-end">Monthly Total (€):</th>
          <th id="monthTotal" class="text-end">0.00</th>
        </tr>
      </tfoot>
    </table>
  </div>

  <button id="btnSaveIncome" class="btn btn-success">Save All</button>
  <div id="incomeMsg" class="alert alert-success mt-2 d-none">Saved.</div>
</div>

{{-- Import Modal --}}
<div class="modal fade" id="importModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Import Income CSV</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">

        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label">Import Month</label>
            <input type="month" id="importMonth" class="form-control form-control-sm" required>
          </div>
          <div class="col-md-8">
            <label class="form-label">CSV File</label>
            <input type="file" id="csvFile" class="form-control form-control-sm" accept=".csv">
            <small class="text-muted">
              Header must include Date,Cash...,Visa..., plus Revolut... and/or Other...
            </small>
          </div>
        </div>

        <hr>

        <h6>Preview</h6>
        <div class="table-responsive">
          <table id="previewTable" class="table table-sm table-bordered d-none">
            <thead>
              <tr>
                <th>Day</th><th>Cash</th><th>Revolut</th><th>Visa</th><th>Other</th><th>Daily Total</th>
              </tr>
            </thead>
            <tbody></tbody>
            <tfoot>
              <tr>
                <th colspan="5" class="text-end">Month Total</th>
                <th id="previewMonthTotal" class="text-end">0.00</th>
              </tr>
            </tfoot>
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

  function pad2(n){ return (n<10?'0':'')+n; }
  function goTo(month){ window.location.href = `?m=${month}`; }

  function recalcMain(){
    let total = 0;
    document.querySelectorAll('#incomeTbl tbody tr').forEach(tr=>{
      let sum = 0;
      tr.querySelectorAll('input').forEach(inp=>{
        sum += parseFloat(inp.value) || 0;
      });
      tr.querySelector('.daily-total').textContent = sum.toFixed(2);
      total += sum;
    });
    document.getElementById('monthTotal').textContent = total.toFixed(2);
  }
  recalcMain();
  document.querySelector('#incomeTbl tbody').addEventListener('input', recalcMain);

  document.getElementById('monthPicker')?.addEventListener('change', (e)=>goTo(e.target.value));
  document.getElementById('prevMonth')?.addEventListener('click', ()=>{
    let [y,m] = document.getElementById('monthPicker').value.split('-').map(Number);
    m--; if(m<1){m=12;y--;}
    goTo(`${y}-${pad2(m)}`);
  });
  document.getElementById('nextMonth')?.addEventListener('click', ()=>{
    let [y,m] = document.getElementById('monthPicker').value.split('-').map(Number);
    m++; if(m>12){m=1;y++;}
    goTo(`${y}-${pad2(m)}`);
  });

  async function postJson(url, payload){
    const res = await fetch(url, {
      method:'POST',
      headers:{
        'Content-Type':'application/json',
        'X-CSRF-TOKEN': csrf,
        'Accept':'application/json'
      },
      body: JSON.stringify(payload)
    });
    if(!res.ok){
      const data = await res.json().catch(()=>({}));
      throw data;
    }
    return res.json().catch(()=>({ok:true}));
  }

  document.getElementById('btnSaveIncome')?.addEventListener('click', async ()=>{
    const rows = Array.from(document.querySelectorAll('#incomeTbl tbody tr')).map(tr=>{
      const date = tr.dataset.date;
      return {
        date,
        cash: parseFloat(tr.querySelector('.income-cash').value) || 0,
        revolut: parseFloat(tr.querySelector('.income-revolut').value) || 0,
        visa: parseFloat(tr.querySelector('.income-visa').value) || 0,
        other: parseFloat(tr.querySelector('.income-other').value) || 0,
      };
    });

    try{
      await postJson(@json(route('reports.financial.income.save')), rows);
      const msg = document.getElementById('incomeMsg');
      msg.classList.remove('d-none');
      setTimeout(()=>msg.classList.add('d-none'), 1800);
    }catch(err){
      console.error(err);
      alert('Save failed');
    }
  });

  // Import
  let imported = [];
  const modalEl = document.getElementById('importModal');
  const modal = new bootstrap.Modal(modalEl);

  document.getElementById('btnImport')?.addEventListener('click', ()=>{
    imported = [];
    document.getElementById('importMonth').value = document.getElementById('monthPicker').value;
    document.getElementById('csvFile').value = '';
    const pt = document.getElementById('previewTable');
    pt.classList.add('d-none');
    pt.querySelector('tbody').innerHTML = '';
    document.getElementById('btnConfirmImport').disabled = true;
    modal.show();
  });

  const MONTHS = {
    january:1, february:2, march:3, april:4,
    may:5, june:6, july:7, august:8,
    september:9, october:10, november:11, december:12
  };

  function parseLine(str){
    const m = str.match(/(".*?"|[^",]+)(?=,|$)/g);
    return (m || []).map(c=>c.replace(/^"|"$/g,'').trim());
  }
  const parseAmt = (txt) => parseFloat((txt||'').replace(/[^0-9.\-]/g,'')) || 0;

  document.getElementById('csvFile')?.addEventListener('change', (e)=>{
    const file = e.target.files[0];
    if(!file) return;

    const reader = new FileReader();
    reader.onload = ev => {
      const lines = String(ev.target.result || '').trim().split(/\r?\n/);
      if(lines.length < 2) return alert('Empty or invalid CSV');

      const headers = parseLine(lines.shift()).map(h=>h.toLowerCase());
      const idx = {
        date: headers.findIndex(h=>h==='date'),
        cash: headers.findIndex(h=>h.startsWith('cash')),
        visa: headers.findIndex(h=>h.startsWith('visa')),
        revolut: headers.findIndex(h=>h.startsWith('revolut')),
        other: headers.findIndex(h=>h.startsWith('other')),
      };

      const [impY, impM] = document.getElementById('importMonth').value.split('-').map(Number);

      imported = lines.map(line=>{
        const cols = parseLine(line);
        const raw = cols[idx.date] || '';
        const mm = raw.match(/,\s*(\d{1,2})\s+([A-Za-z]+)/);
        if(!mm) return null;
        const day = parseInt(mm[1],10);
        const monName = (mm[2]||'').toLowerCase();
        const monNum = MONTHS[monName];
        if(!monNum || monNum !== impM) return null;

        const dd = day<10 ? '0'+day : ''+day;
        const mm2 = impM<10 ? '0'+impM : ''+impM;
        const iso = `${impY}-${mm2}-${dd}`;

        return {
          date: iso,
          cash: parseAmt(cols[idx.cash]),
          revolut: idx.revolut >= 0 ? parseAmt(cols[idx.revolut]) : 0,
          visa: parseAmt(cols[idx.visa]),
          other: idx.other >= 0 ? parseAmt(cols[idx.other]) : 0
        };
      }).filter(x=>x);

      const pt = document.getElementById('previewTable');
      const tb = pt.querySelector('tbody');
      tb.innerHTML = '';
      let sum=0;

      imported.forEach(r=>{
        const dn = new Date(r.date).toLocaleDateString(undefined,{weekday:'short', day:'numeric', month:'short'});
        const daily = r.cash + r.revolut + r.visa + r.other;
        sum += daily;
        tb.insertAdjacentHTML('beforeend', `
          <tr>
            <td>${dn}</td>
            <td>${r.cash.toFixed(2)}</td>
            <td>${r.revolut.toFixed(2)}</td>
            <td>${r.visa.toFixed(2)}</td>
            <td>${r.other.toFixed(2)}</td>
            <td>${daily.toFixed(2)}</td>
          </tr>
        `);
      });

      pt.classList.toggle('d-none', imported.length===0);
      document.getElementById('previewMonthTotal').textContent = sum.toFixed(2);
      document.getElementById('btnConfirmImport').disabled = imported.length===0;
    };
    reader.readAsText(file);
  });

  document.getElementById('btnConfirmImport')?.addEventListener('click', async ()=>{
    try{
      await postJson(
        @json(route('reports.financial.income.import', ['m' => '__M__'])).replace('__M__', document.getElementById('importMonth').value),
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
