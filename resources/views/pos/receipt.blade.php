<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Receipt #{{ $sale_id }}</title>
  <style>
    /* Thermal printer 80mm */
    @page { size:80mm auto; margin:2mm; }
    body {
      width:80mm;
      margin:0; padding:0;
      font-family: "Courier New", monospace;
      font-size:12px;
      line-height:1.2;
      color:#000;
    }
    .header, .footer { text-align:center; }
    .header h2 { margin:0; font-size:14px; }
    .divider { border-top:1px dashed #000; margin:6px 0; }
    .meta small { display:block; }
    table { width:100%; border-collapse:collapse; margin-bottom:6px; }
    th, td { padding:2px 0; vertical-align:top; }
    th { border-bottom:1px solid #000; font-weight:bold; }
    .right { text-align:right; }
    .section-title { font-weight:bold; margin:6px 0 2px; }
    .muted { font-size:10px; }
    @media print {
      .no-print { display:none !important; }
      body { margin:0; }
    }
  </style>
</head>
<body>

  <div class="header">
    <h2>{{ $company['company_name'] ?? '' }}</h2>
    <small>
      {!! nl2br(e($company['company_address'] ?? '')) !!}<br>
      @if(!empty($company['company_phone_number'])) Tel: {{ $company['company_phone_number'] }}<br>@endif
      @if(!empty($company['company_vat_number'])) VAT No: {{ $company['company_vat_number'] }}@endif
    </small>
  </div>

  <div class="divider"></div>

  <div class="meta">
    <strong>Receipt #{{ $sale_id }}</strong>
    @if(!empty($sale->voided_at))
      <div style="margin-top:4px; font-weight:bold; font-size:14px;">
        *** VOIDED ***
      </div>
    @endif
    <br>
    <small>
      Date: {{ $saleDate->format('Y-m-d H:i:s') }}<br>
      Client: {{ $clientName }}
      @if(!empty($clientMobile))
        [{{ $clientMobile }}]
      @endif

      @if($isAppt && $apptTime)
        <br>
        Appointment Time: {{ $apptTime->format('H:i') }}
        @if(!empty($apptService))
          ({{ $apptService }})
        @endif
      @endif
    </small>
  </div>

  <div class="divider"></div>

  {{-- SERVICES --}}
  @if($serviceLines && count($serviceLines))
    <div class="section-title">SERVICES</div>
    <table>
      <thead>
        <tr>
          <th>QTY</th>
          <th>DESC</th>
          <th class="right">TOTAL</th>
        </tr>
      </thead>
      <tbody>
        @foreach($serviceLines as $s)
          <tr>
            <td>{{ (int)$s->quantity }}</td>
            <td>
              {{ $s->name }}
              <span class="muted">(€{{ number_format((float)$s->unit_price, 2) }})</span>
            </td>
            <td class="right">€{{ number_format((float)$s->line_total, 2) }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  @endif

  {{-- PRODUCTS --}}
  @if($productLines && count($productLines))
    <div class="section-title">PRODUCTS</div>
    <table>
      <thead>
        <tr>
          <th>QTY</th>
          <th>DESC</th>
          <th class="right">TOTAL</th>
        </tr>
      </thead>
      <tbody>
        @foreach($productLines as $p)
          <tr>
            <td>{{ (int)$p->quantity }}</td>
            <td>
              {{ $p->name }}
              <span class="muted">(€{{ number_format((float)$p->unit_price, 2) }})</span>
            </td>
            <td class="right">€{{ number_format((float)$p->line_total, 2) }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  @endif

  <div class="divider"></div>

  <div class="section-title">TOTALS</div>
  <table>
    <tbody>
      <tr>
        <td>Total VAT:</td>
        <td class="right">€{{ number_format((float)($sale->total_vat ?? 0), 2) }}</td>
      </tr>
      <tr>
        <td><strong>Grand Total:</strong></td>
        <td class="right"><strong>€{{ number_format((float)($sale->grand_total ?? 0), 2) }}</strong></td>
      </tr>
      <tr>
        <td>Paid:</td>
        <td class="right">€{{ number_format((float)$amountPaid, 2) }}</td>
      </tr>
      <tr>
        <td>Change:</td>
        <td class="right">€{{ number_format((float)$changeDue, 2) }}</td>
      </tr>
    </tbody>
  </table>

  {{-- PAYMENTS --}}
  @if($payments && count($payments))
    <div class="section-title">PAYMENTS</div>
    <table>
      <thead>
        <tr>
          <th>Method</th>
          <th class="right">Amt</th>
        </tr>
      </thead>
      <tbody>
        @foreach($payments as $pay)
          <tr>
            <td>{{ $pay->method_name ?? '' }}</td>
            <td class="right">€{{ number_format((float)$pay->amount, 2) }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  @endif

  <div class="divider"></div>

  <div class="footer">
    {{ $company['company_name'] ?? '' }}
    @if(!empty($company['company_phone_number']))
      | Tel: {{ $company['company_phone_number'] }}
    @endif
  </div>

  <div class="no-print" style="margin-top:10px; text-align:center;">
    <button onclick="window.print()">Print</button>
  </div>

  <script>
    window.onload = function() {
      window.print();
    };
  </script>
</body>
</html>
