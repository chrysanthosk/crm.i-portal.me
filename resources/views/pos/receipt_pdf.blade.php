<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Invoice #{{ $sale_id }}</title>
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      font-family: DejaVu Sans, sans-serif;
      font-size: 13px;
      color: #1a1a1a;
      background: #fff;
      padding: 30px 40px;
    }

    /* ── Header ── */
    .header {
      display: table;
      width: 100%;
      margin-bottom: 28px;
    }
    .header-left, .header-right {
      display: table-cell;
      vertical-align: top;
    }
    .header-right { text-align: right; }

    .company-name {
      font-size: 22px;
      font-weight: bold;
      color: #222;
      margin-bottom: 4px;
    }
    .company-meta {
      font-size: 11px;
      color: #555;
      line-height: 1.6;
    }

    .invoice-title {
      font-size: 28px;
      font-weight: bold;
      color: #2563eb;
      text-transform: uppercase;
      letter-spacing: 2px;
    }
    .invoice-meta {
      font-size: 12px;
      color: #555;
      margin-top: 6px;
      line-height: 1.8;
    }

    /* ── Divider ── */
    .divider {
      border: none;
      border-top: 2px solid #2563eb;
      margin: 18px 0;
    }
    .divider-thin {
      border: none;
      border-top: 1px solid #e5e7eb;
      margin: 12px 0;
    }

    /* ── Bill To / Summary row ── */
    .two-col {
      display: table;
      width: 100%;
      margin-bottom: 20px;
    }
    .two-col-left, .two-col-right {
      display: table-cell;
      vertical-align: top;
      width: 50%;
    }
    .two-col-right { text-align: right; }

    .section-label {
      font-size: 10px;
      font-weight: bold;
      color: #2563eb;
      text-transform: uppercase;
      letter-spacing: 1px;
      margin-bottom: 4px;
    }
    .bill-to-name { font-size: 14px; font-weight: bold; }
    .bill-to-sub  { font-size: 11px; color: #666; line-height: 1.6; }

    /* ── Table ── */
    table {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 16px;
      font-size: 12px;
    }
    thead tr {
      background: #2563eb;
      color: #fff;
    }
    thead th {
      padding: 8px 10px;
      text-align: left;
      font-size: 11px;
      text-transform: uppercase;
      letter-spacing: .5px;
    }
    thead th.right { text-align: right; }

    tbody tr:nth-child(even) { background: #f8fafc; }
    tbody td {
      padding: 8px 10px;
      border-bottom: 1px solid #e5e7eb;
      vertical-align: top;
    }
    tbody td.right { text-align: right; }
    .sub-text { font-size: 10px; color: #888; }

    /* ── Totals block ── */
    .totals-wrap {
      display: table;
      width: 100%;
    }
    .totals-spacer { display: table-cell; width: 55%; }
    .totals-box    { display: table-cell; width: 45%; }

    .totals-table { width: 100%; font-size: 12px; }
    .totals-table td { padding: 5px 8px; }
    .totals-table td.right { text-align: right; }
    .totals-table .grand-row td {
      font-size: 14px;
      font-weight: bold;
      border-top: 2px solid #2563eb;
      padding-top: 8px;
      color: #2563eb;
    }

    /* ── Payments ── */
    .payments-wrap { margin-top: 16px; }
    .payments-wrap table thead tr { background: #374151; }

    /* ── Badges ── */
    .badge {
      display: inline-block;
      padding: 2px 8px;
      border-radius: 4px;
      font-size: 10px;
      font-weight: bold;
    }
    .badge-paid    { background: #d1fae5; color: #065f46; }
    .badge-partial { background: #fef3c7; color: #92400e; }
    .badge-voided  { background: #fee2e2; color: #991b1b; }

    /* ── Section title ── */
    .section-title {
      font-size: 11px;
      font-weight: bold;
      color: #374151;
      text-transform: uppercase;
      letter-spacing: .5px;
      margin-bottom: 6px;
    }

    /* ── Footer ── */
    .footer {
      margin-top: 32px;
      text-align: center;
      font-size: 10px;
      color: #9ca3af;
      border-top: 1px solid #e5e7eb;
      padding-top: 12px;
    }
  </style>
</head>
<body>

  {{-- ── HEADER ── --}}
  <div class="header">
    <div class="header-left">
      <div class="company-name">{{ $company['company_name'] ?? config('app.name') }}</div>
      <div class="company-meta">
        {!! nl2br(e($company['company_address'] ?? '')) !!}
        @if(!empty($company['company_phone_number']))
          <br>Tel: {{ $company['company_phone_number'] }}
        @endif
        @if(!empty($company['company_vat_number']))
          <br>VAT No: {{ $company['company_vat_number'] }}
        @endif
      </div>
    </div>
    <div class="header-right">
      <div class="invoice-title">Invoice</div>
      <div class="invoice-meta">
        <strong>#{{ $sale_id }}</strong><br>
        Date: {{ $saleDate->format('d M Y') }}<br>
        Time: {{ $saleDate->format('H:i') }}
        @if($sale->voided_at)
          <br><span class="badge badge-voided">VOIDED</span>
        @elseif($paymentStatus === 'paid')
          <br><span class="badge badge-paid">PAID</span>
        @else
          <br><span class="badge badge-partial">PARTIAL PAYMENT</span>
        @endif
      </div>
    </div>
  </div>

  <hr class="divider">

  {{-- ── BILL TO / APPOINTMENT ── --}}
  <div class="two-col">
    <div class="two-col-left">
      <div class="section-label">Bill To</div>
      <div class="bill-to-name">{{ $clientName }}</div>
      <div class="bill-to-sub">
        @if(!empty($clientMobile)){{ $clientMobile }}@endif
        @if($isAppt && $apptTime)
          <br>Appointment: {{ $apptTime->format('d M Y, H:i') }}
          @if(!empty($apptService)) — {{ $apptService }}@endif
        @endif
      </div>
    </div>
  </div>

  {{-- ── SERVICES ── --}}
  @if($serviceLines->isNotEmpty())
    <div class="section-title">Services</div>
    <table>
      <thead>
        <tr>
          <th>Description</th>
          <th>Staff</th>
          <th class="right">Qty</th>
          <th class="right">Unit Price</th>
          <th class="right">Total</th>
        </tr>
      </thead>
      <tbody>
        @foreach($serviceLines as $line)
          <tr>
            <td>{{ $line->service?->name ?? ('Service #'.$line->service_id) }}</td>
            <td>{{ $line->staff?->user?->name ?? '—' }}</td>
            <td class="right">{{ (int)$line->quantity }}</td>
            <td class="right">€{{ number_format((float)$line->unit_price, 2) }}</td>
            <td class="right">€{{ number_format((float)$line->line_total, 2) }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  @endif

  {{-- ── PRODUCTS ── --}}
  @if($productLines->isNotEmpty())
    <div class="section-title">Products</div>
    <table>
      <thead>
        <tr>
          <th>Description</th>
          <th>Staff</th>
          <th class="right">Qty</th>
          <th class="right">Unit Price</th>
          <th class="right">Total</th>
        </tr>
      </thead>
      <tbody>
        @foreach($productLines as $line)
          <tr>
            <td>{{ $line->product?->name ?? ('Product #'.$line->product_id) }}</td>
            <td>{{ $line->staff?->user?->name ?? '—' }}</td>
            <td class="right">{{ (int)$line->quantity }}</td>
            <td class="right">€{{ number_format((float)$line->unit_price, 2) }}</td>
            <td class="right">€{{ number_format((float)$line->line_total, 2) }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  @endif

  <hr class="divider-thin">

  {{-- ── TOTALS ── --}}
  <div class="totals-wrap">
    <div class="totals-spacer"></div>
    <div class="totals-box">
      <table class="totals-table">
        <tbody>
          @if((float)$sale->services_subtotal > 0)
          <tr>
            <td>Services subtotal:</td>
            <td class="right">€{{ number_format((float)$sale->services_subtotal, 2) }}</td>
          </tr>
          @endif
          @if((float)$sale->products_subtotal > 0)
          <tr>
            <td>Products subtotal:</td>
            <td class="right">€{{ number_format((float)$sale->products_subtotal, 2) }}</td>
          </tr>
          @endif
          <tr>
            <td>Total VAT:</td>
            <td class="right">€{{ number_format((float)$sale->total_vat, 2) }}</td>
          </tr>
          <tr class="grand-row">
            <td>Grand Total:</td>
            <td class="right">€{{ number_format((float)$sale->grand_total, 2) }}</td>
          </tr>
          <tr>
            <td>Amount Paid:</td>
            <td class="right">€{{ number_format((float)$amountPaid, 2) }}</td>
          </tr>
          @if($changeDue > 0)
          <tr>
            <td>Change:</td>
            <td class="right">€{{ number_format((float)$changeDue, 2) }}</td>
          </tr>
          @endif
          @if($sale->balance_due > 0)
          <tr>
            <td><strong>Balance Due:</strong></td>
            <td class="right"><strong>€{{ number_format((float)$sale->balance_due, 2) }}</strong></td>
          </tr>
          @endif
        </tbody>
      </table>
    </div>
  </div>

  {{-- ── PAYMENTS ── --}}
  @if($payments->isNotEmpty())
    <div class="payments-wrap">
      <div class="section-title">Payment Breakdown</div>
      <table>
        <thead>
          <tr>
            <th>Payment Method</th>
            <th class="right">Amount</th>
          </tr>
        </thead>
        <tbody>
          @foreach($payments as $pay)
            <tr>
              <td>{{ $pay->paymentMethod?->name ?? $pay->method_name ?? '—' }}</td>
              <td class="right">€{{ number_format((float)$pay->amount, 2) }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif

  {{-- ── FOOTER ── --}}
  <div class="footer">
    {{ $company['company_name'] ?? config('app.name') }}
    @if(!empty($company['company_vat_number'])) | VAT: {{ $company['company_vat_number'] }}@endif
    @if(!empty($company['company_phone_number'])) | Tel: {{ $company['company_phone_number'] }}@endif
    <br>Thank you for your business.
  </div>

</body>
</html>
