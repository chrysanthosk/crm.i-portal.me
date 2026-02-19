<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Z Report #{{ $zr->report_number ?? $zr->id }}</title>
    <style>
        @page { size: 80mm auto; margin: 0; }
        body { width:80mm; margin:0; font-family:monospace; font-size:12px; line-height:1.2; }
        .center { text-align:center; }
        .line { margin:4px 0; border-bottom:1px dashed #000; }
        .section { margin:6px 0; }
        .bold { font-weight:bold; }
        .flex { display:flex; justify-content:space-between; gap:10px; }
    </style>
</head>
<body>

<div class="center bold">{{ $company['company_name'] }}</div>
@if(!empty($company['company_address']))<div class="center">{{ $company['company_address'] }}</div>@endif
@if(!empty($company['company_phone_number']))<div class="center">Tel: {{ $company['company_phone_number'] }}</div>@endif
@if(!empty($company['company_vat_number']))<div class="center">VAT No: {{ $company['company_vat_number'] }}</div>@endif
<div class="line"></div>

<div class="center bold">Z-Report #{{ $zr->report_number ?? $zr->id }}</div>
<div class="center">Period: {{ \Carbon\Carbon::parse($zr->date_from)->format('d/m/Y') }} — {{ \Carbon\Carbon::parse($zr->date_to)->format('d/m/Y') }}</div>
<div class="center">Generated: {{ \Carbon\Carbon::parse($zr->created_at)->format('d/m/Y h:i A') }}</div>
<div class="line"></div>

<div class="section flex">
    <span>Transactions:</span>
    <span>{{ (int)$totals->transactions_count }}</span>
</div>
<div class="section bold flex">
    <span>Total Amount:</span>
    <span>€ {{ number_format((float)$totals->total_transactions, 2) }}</span>
</div>
<div class="line"></div>

<div class="section bold">Services</div>
<div class="flex"><span>Net:</span><span>€ {{ number_format((float)$subtotals->services_net, 2) }}</span></div>
<div class="flex"><span>VAT {{ number_format((float)$servicesVatPct, 2) }}%:</span><span>€ {{ number_format((float)$subtotals->services_vat, 2) }}</span></div>
<div class="line"></div>

<div class="section bold">Products</div>
<div class="flex"><span>Net:</span><span>€ {{ number_format((float)$subtotals->products_net, 2) }}</span></div>
<div class="flex"><span>VAT {{ number_format((float)$productsVatPct, 2) }}%:</span><span>€ {{ number_format((float)$subtotals->products_vat, 2) }}</span></div>
<div class="line"></div>

<div class="section bold">Totals</div>
<div class="flex">
    <span>Net Total:</span>
    <span>€ {{ number_format((float)($subtotals->services_net + $subtotals->products_net), 2) }}</span>
</div>
<div class="flex">
    <span>VAT Total:</span>
    <span>€ {{ number_format((float)($subtotals->services_vat + $subtotals->products_vat), 2) }}</span>
</div>
<div class="line"></div>

<div class="section bold">Total Received by Payment Method</div>
@foreach($payments as $p)
    @php
        $method = is_object($p) ? $p->payment_method : ($p['payment_method'] ?? '');
        $amount = is_object($p) ? $p->amount : ($p['amount'] ?? 0);
    @endphp
    <div class="flex">
        <span>{{ $method }}:</span>
        <span>€ {{ number_format((float)$amount, 2) }}</span>
    </div>
@endforeach

<div class="line"></div>
<div class="center bold">*** End of Z Report ***</div>

<script>
    window.onload = function() { window.print(); };
</script>
</body>
</html>
