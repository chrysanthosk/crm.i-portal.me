<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 12px; }
        h1 { font-size: 18px; margin-bottom: 8px; }
        .muted { color:#666; }
        table { width:100%; border-collapse: collapse; margin-top:10px; }
        th, td { border:1px solid #ddd; padding:6px; vertical-align: top; }
        th { background:#f5f5f5; }
    </style>
</head>
<body>

<h1>{{ $title }}</h1>

@if(!empty($filters))
    <p class="muted"><strong>Filters:</strong> {{ json_encode($filters) }}</p>
@endif

@php $type = $data['type'] ?? 'empty'; @endphp

@if($type === 'analytics')
    <p><strong>Period:</strong> {{ $data['from'] }} — {{ $data['to'] }}</p>
    <p><strong>Total Revenue:</strong> € {{ number_format((float)$data['totalRevenue'], 2) }}</p>
    <p><strong>Total Appointments:</strong> {{ (int)$data['totalAppointments'] }}</p>

    <h3>Revenue by Day</h3>
    <table>
        <thead><tr><th>Date</th><th>Revenue</th></tr></thead>
        <tbody>
        @foreach(($data['byDay'] ?? []) as $r)
            <tr>
                <td>{{ $r->day ?? $r['day'] ?? '' }}</td>
                <td>€ {{ number_format((float)($r->revenue ?? $r['revenue'] ?? 0), 2) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <h3>Top Staff</h3>
    <table>
        <thead><tr><th>Staff</th><th>Revenue</th></tr></thead>
        <tbody>
        @foreach(($data['topStaff'] ?? []) as $t)
            @php
                $name = $t->name ?? $t['name'] ?? '';
                $rev  = $t->revenue ?? $t['revenue'] ?? 0;
                $label = trim((string)$name);
                if ($label === '') {
                    $sid = $t->staff_id ?? $t['staff_id'] ?? '';
                    $label = $sid ? ('Staff #'.$sid) : 'Staff';
                }
            @endphp
            <tr>
                <td>{{ $label }}</td>
                <td>€ {{ number_format((float)$rev, 2) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

@elseif($type === 'table')
    @php $rows = $data['rows'] ?? []; @endphp
    @if(count($rows))
        @php $cols = array_keys((array)$rows[0]); @endphp
        <table>
            <thead>
            <tr>
                @foreach($cols as $c)<th>{{ $c }}</th>@endforeach
            </tr>
            </thead>
            <tbody>
            @foreach($rows as $row)
                <tr>
                    @foreach((array)$row as $v)
                        <td>{{ is_numeric($v) ? $v : (string)$v }}</td>
                    @endforeach
                </tr>
            @endforeach
            </tbody>
        </table>
    @else
        <p class="muted">No rows.</p>
    @endif
@else
    <p class="muted">{{ $data['message'] ?? 'No data.' }}</p>
@endif

</body>
</html>
