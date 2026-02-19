@php
    $rows = $rows ?? collect();
@endphp

@if($rows->count() === 0)
    <tr>
        <td colspan="6" class="text-center text-muted py-3">No appointments for this date.</td>
    </tr>
@else
    @foreach($rows as $r)
        <tr>
            <td>{{ trim(($r['start_at'] ?? '').' - '.($r['end_at'] ?? '')) }}</td>
            <td>{{ $r['client_name'] ?? '—' }}</td>
            <td>{{ $r['staff_name'] ?? '—' }}</td>
            <td>{{ $r['service_name'] ?? '—' }}</td>
            <td>{{ $r['notes'] ?? '' }}</td>
            <td>
                @if($canManage)
                    <button class="btn btn-sm btn-info" data-action="edit" data-id="{{ (int)$r['id'] }}">
                        <i class="fas fa-edit"></i> Edit
                    </button>

                    <button class="btn btn-sm btn-danger" data-action="delete" data-id="{{ (int)$r['id'] }}">
                        <i class="fas fa-trash"></i> Delete
                    </button>
                @else
                    <span class="text-muted">View only</span>
                @endif
            </td>
        </tr>
    @endforeach
@endif
