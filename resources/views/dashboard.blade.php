@extends('layouts.app')

@section('title', 'Reception Dashboard')

@push('styles')
<link href="https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/css/select2.min.css" rel="stylesheet"/>

<style>
    .blinking-event { animation: blink 1s infinite alternate; }
    @keyframes blink { from { opacity: 1; } to { opacity: .35; } }

    #appointmentsMainCalendar {
        border: 1px solid rgba(0,0,0,.15);
        height: 600px;
        width: 100% !important;
    }
    body.dark-mode #appointmentsMainCalendar { border-color: rgba(255,255,255,.15); }

    .modal-lg { max-width: 980px; }

    /* Select2 AdminLTE */
    .select2-container .select2-selection--single { height: calc(2.25rem + 2px); }
    .select2-container--default .select2-selection--single .select2-selection__rendered { line-height: calc(2.25rem); }
    .select2-container--default .select2-selection--single .select2-selection__arrow { height: calc(2.25rem + 2px); }

    /* Dark mode fixes */
    body.dark-mode .select2-container--default .select2-selection--single {
        background-color: #343a40;
        border-color: rgba(255,255,255,.15);
        color: #f8f9fa;
    }
    body.dark-mode .select2-container--default .select2-selection--single .select2-selection__rendered { color: #f8f9fa; }
    body.dark-mode .select2-container--default .select2-selection--single .select2-selection__arrow b {
        border-color: #f8f9fa transparent transparent transparent;
    }
    body.dark-mode .select2-container--default .select2-dropdown {
        background-color: #343a40;
        border-color: rgba(255,255,255,.15);
        color: #f8f9fa;
    }

    /* KPI change badge */
    .kpi-change { font-size: .75rem; font-weight: 600; }
    .kpi-change.up   { color: #28a745; }
    .kpi-change.down { color: #dc3545; }
    .kpi-change.flat { color: #6c757d; }

    /* Chart card */
    #revenueChart { max-height: 220px; }
</style>
@endpush

@section('content')

{{-- ── ROW 1: Primary KPIs ── --}}
<div class="row mb-3">
    <div class="col-6 col-md-3">
        <div class="small-box bg-info">
            <div class="inner">
                <h3>{{ $stats['todayAppointments'] }}</h3>
                <p>Today's Appointments</p>
            </div>
            <div class="icon"><i class="fas fa-calendar-day"></i></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="small-box bg-success">
            <div class="inner">
                <h3>€{{ number_format($stats['todaySales'], 2) }}</h3>
                <p>Today's Sales</p>
            </div>
            <div class="icon"><i class="fas fa-cash-register"></i></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="small-box bg-primary">
            <div class="inner">
                <h3>{{ $stats['totalClients'] }}</h3>
                <p>Total Clients</p>
            </div>
            <div class="icon"><i class="fas fa-users"></i></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="small-box bg-warning">
            <div class="inner">
                <h3>{{ $stats['upcomingToday'] }}</h3>
                <p>Upcoming Today</p>
            </div>
            <div class="icon"><i class="fas fa-clock"></i></div>
        </div>
    </div>
</div>

{{-- ── ROW 2: Month KPIs ── --}}
<div class="row mb-3">
    <div class="col-6 col-md-3">
        <div class="info-box">
            <span class="info-box-icon bg-success elevation-1"><i class="fas fa-chart-line"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">This Month Revenue</span>
                <span class="info-box-number">€{{ number_format($stats['thisMonthRevenue'], 2) }}</span>
                @if($stats['revenueChange'] !== null)
                    <span class="kpi-change {{ $stats['revenueChange'] >= 0 ? 'up' : 'down' }}">
                        <i class="fas fa-arrow-{{ $stats['revenueChange'] >= 0 ? 'up' : 'down' }}"></i>
                        {{ abs($stats['revenueChange']) }}% vs last month
                    </span>
                @else
                    <span class="kpi-change flat">No data last month</span>
                @endif
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="info-box">
            <span class="info-box-icon bg-secondary elevation-1"><i class="fas fa-history"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Last Month Revenue</span>
                <span class="info-box-number">€{{ number_format($stats['lastMonthRevenue'], 2) }}</span>
                <span class="kpi-change flat">{{ \Carbon\Carbon::today()->subMonth()->format('F Y') }}</span>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="info-box">
            <span class="info-box-icon bg-primary elevation-1"><i class="fas fa-user-plus"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">New Clients</span>
                <span class="info-box-number">{{ $stats['newClientsThisMonth'] }}</span>
                <span class="kpi-change flat">This month</span>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="info-box">
            <span class="info-box-icon bg-info elevation-1"><i class="fas fa-spa"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Services / Products</span>
                <span class="info-box-number">{{ $stats['totalServices'] }} / {{ $stats['totalProducts'] }}</span>
                <span class="kpi-change flat">Active catalogue</span>
            </div>
        </div>
    </div>
</div>

{{-- ── ROW 3: Chart + Top Tables ── --}}
<div class="row mb-3">

    {{-- Revenue chart --}}
    <div class="col-md-8">
        <div class="card card-primary card-outline mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <strong><i class="fas fa-chart-bar mr-1"></i> Revenue — Last 30 Days</strong>
                <span class="badge badge-light" id="chartTotalBadge"></span>
            </div>
            <div class="card-body">
                <canvas id="revenueChart"></canvas>
            </div>
        </div>
    </div>

    {{-- Top Services + Top Staff --}}
    <div class="col-md-4">
        <div class="card card-success card-outline mb-2">
            <div class="card-header py-2">
                <strong><i class="fas fa-spa mr-1"></i> Top Services</strong>
                <small class="text-muted ml-1">(this month)</small>
            </div>
            <div class="card-body p-0">
                @if($topServices->isEmpty())
                    <p class="text-muted small p-3 mb-0">No service sales this month.</p>
                @else
                    <table class="table table-sm mb-0">
                        <thead><tr><th>Service</th><th class="text-right">Bookings</th><th class="text-right">Revenue</th></tr></thead>
                        <tbody>
                            @foreach($topServices as $svc)
                                <tr>
                                    <td class="small">{{ $svc->name }}</td>
                                    <td class="text-right small">{{ $svc->bookings }}</td>
                                    <td class="text-right small">€{{ number_format((float)$svc->revenue, 0) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>

        <div class="card card-info card-outline">
            <div class="card-header py-2">
                <strong><i class="fas fa-user-tie mr-1"></i> Top Staff</strong>
                <small class="text-muted ml-1">(this month)</small>
            </div>
            <div class="card-body p-0">
                @if($topStaff->isEmpty())
                    <p class="text-muted small p-3 mb-0">No appointments this month.</p>
                @else
                    <table class="table table-sm mb-0">
                        <thead><tr><th>Staff</th><th class="text-right">Appts</th><th class="text-right">Done</th></tr></thead>
                        <tbody>
                            @foreach($topStaff as $s)
                                <tr>
                                    <td class="small">{{ $s->name }}</td>
                                    <td class="text-right small">{{ $s->appointments }}</td>
                                    <td class="text-right small">{{ $s->completed }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- ── ROW 4: Calendar + Sidebar ── --}}
<div class="row">
    <div class="col-md-9">
        {{-- FullCalendar Widget --}}
        <div class="card card-primary card-outline mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <strong>Schedule Overview</strong>
                @if($canManage)
                    <button id="btnAddAppt" class="btn btn-sm btn-primary">
                        <i class="fas fa-plus"></i> Add Appointment
                    </button>
                @endif
            </div>
            <div class="card-body p-2">
                <div id="appointmentsMainCalendar"></div>
            </div>
        </div>

        {{-- Today's Appointments List --}}
        <div class="card card-secondary card-outline mb-3">
            <div class="card-header d-flex align-items-center justify-content-between">
                <strong>Today's Appointments</strong>
                <div class="d-flex align-items-center">
                    <input type="date" id="todayDate" class="form-control form-control-sm mr-2" value="{{ $today }}" style="width: auto;">
                    <button class="btn btn-sm btn-outline-secondary" id="btnRefreshToday">
                        <i class="fas fa-sync"></i> Refresh
                    </button>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                    <table class="table table-striped table-bordered mb-0">
                        <thead style="position: sticky; top: 0; z-index: 1;">
                            <tr>
                                <th style="width:140px;">Time</th>
                                <th>Client</th>
                                <th>Staff</th>
                                <th>Service</th>
                                <th>Notes</th>
                                <th style="width:120px;">Action</th>
                            </tr>
                        </thead>
                        <tbody id="todayRowsBody">
                            @include('calendar_view._today_rows', ['rows' => $rows, 'canManage' => $canManage])
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        {{-- Quick Actions --}}
        <div class="card card-success card-outline mb-3">
            <div class="card-header"><strong>Quick Actions</strong></div>
            <div class="card-body d-grid gap-2">
                @if($canManage)
                    <button type="button" class="btn btn-success mb-2 d-block js-open-add-appointment"><i class="fas fa-calendar-plus mr-1"></i> Add Appointment</button>
                @endif
                @if($canPos)
                    <a href="{{ route('pos.index') }}" class="btn btn-primary mb-2 d-block"><i class="fas fa-cash-register mr-1"></i> Open Cashier</a>
                @endif
                @if($canClients)
                    <a href="{{ route('clients.index') }}" class="btn btn-outline-secondary mb-2 d-block"><i class="fas fa-users mr-1"></i> Open Clients</a>
                @endif
                @if($canReports)
                    <a href="{{ route('reports.index', ['report' => 'z_reports']) }}" class="btn btn-outline-warning mb-2 d-block"><i class="fas fa-file-invoice mr-1"></i> Z Reports</a>
                @endif
                <a href="{{ route('calendar_view.index') }}" class="btn btn-outline-info d-block"><i class="fas fa-expand-arrows-alt mr-1"></i> Full Calendar</a>
            </div>
        </div>

        <div class="card card-info card-outline">
            <div class="card-header"><strong>Date & Context</strong></div>
            <div class="card-body">
                <p class="mb-2"><strong>Today:</strong> {{ \Illuminate\Support\Carbon::parse($today)->format('l, d M Y') }}</p>
                <p class="mb-0 text-muted small">Reception Dashboard Mode: Everything you need for daily operations is on this screen.</p>
            </div>
        </div>
    </div>
</div>

{{-- Add Appointment Modal --}}
<div class="modal fade" id="addAppointmentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">New Appointment</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body" id="addApptBody">
                <div class="text-center py-5"><i class="fas fa-spinner fa-spin"></i> Loading…</div>
            </div>
        </div>
    </div>
</div>

{{-- Edit Appointment Modal --}}
<div class="modal fade" id="editAppointmentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Appointment</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body" id="editApptBody">
                <div class="text-center py-5"><i class="fas fa-spinner fa-spin"></i> Loading…</div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/fullcalendar-scheduler@6.1.15/index.global.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>

<script>
(function(){
    const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    const canManage = @json((bool)$canManage);
    const isDark = document.body.classList.contains('dark-mode');

    function ajax(url, method, data) {
        return $.ajax({ url, method, data, headers: { 'X-CSRF-TOKEN': csrf } });
    }

    function showAjaxError($target, title, xhr) {
        const body = (xhr && xhr.responseText) ? String(xhr.responseText) : '';
        $target.html(`
          <tr><td colspan="6">
            <div class="alert alert-danger mb-0">
              <div class="fw-bold">${title}</div>
              <div class="small">HTTP ${xhr.status}</div>
              <pre class="small mt-2" style="white-space:pre-wrap;max-height:260px;overflow:auto;">${$('<div/>').text(body.slice(0,6000)).html()}</pre>
            </div>
          </td></tr>
        `);
    }

    function isoLocal(dt) {
        const p = n => String(n).padStart(2,'0');
        return `${dt.getFullYear()}-${p(dt.getMonth()+1)}-${p(dt.getDate())}T${p(dt.getHours())}:${p(dt.getMinutes())}:${p(dt.getSeconds())}`;
    }

    function ymdFromDate(d) {
        const p = n => String(n).padStart(2,'0');
        return `${d.getFullYear()}-${p(d.getMonth()+1)}-${p(d.getDate())}`;
    }

    // ─── Revenue Chart ───────────────────────────────────────────
    $.getJSON('{{ route('dashboard.charts') }}', function(data) {
        const gridColor  = isDark ? 'rgba(255,255,255,0.08)' : 'rgba(0,0,0,0.06)';
        const labelColor = isDark ? '#ccc' : '#555';

        new Chart(document.getElementById('revenueChart'), {
            type: 'bar',
            data: {
                labels: data.labels,
                datasets: [
                    {
                        label: 'Revenue (€)',
                        data: data.revenue,
                        backgroundColor: 'rgba(40,167,69,0.55)',
                        borderColor: 'rgba(40,167,69,0.9)',
                        borderWidth: 1,
                        borderRadius: 3,
                        yAxisID: 'y',
                        order: 2,
                    },
                    {
                        label: 'Sales Count',
                        data: data.counts,
                        type: 'line',
                        borderColor: 'rgba(0,123,255,0.8)',
                        backgroundColor: 'transparent',
                        pointRadius: 3,
                        pointHoverRadius: 5,
                        tension: 0.3,
                        yAxisID: 'y2',
                        order: 1,
                    }
                ]
            },
            options: {
                responsive: true,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { labels: { color: labelColor, boxWidth: 12, padding: 14 } },
                    tooltip: {
                        callbacks: {
                            label: ctx => ctx.dataset.yAxisID === 'y'
                                ? ` €${ctx.parsed.y.toFixed(2)}`
                                : ` ${ctx.parsed.y} sales`
                        }
                    }
                },
                scales: {
                    x: { ticks: { color: labelColor, maxRotation: 45 }, grid: { color: gridColor } },
                    y: {
                        position: 'left',
                        ticks: { color: labelColor, callback: v => '€' + v },
                        grid: { color: gridColor }
                    },
                    y2: {
                        position: 'right',
                        ticks: { color: 'rgba(0,123,255,0.8)', stepSize: 1 },
                        grid: { drawOnChartArea: false }
                    }
                }
            }
        });

        // Badge: total revenue last 30 days
        const total = data.revenue.reduce((a, b) => a + b, 0);
        document.getElementById('chartTotalBadge').textContent = '30-day total: €' + total.toFixed(2);
    });

    // ─── List Refresh ─────────────────────────────────────────────
    function refreshList(dateVal) {
        const $body = $('#todayRowsBody');
        $body.html(`<tr><td colspan="6" class="text-center py-3 text-muted"><i class="fas fa-spinner fa-spin"></i> Loading…</td></tr>`);
        $.get("{{ route('calendar_view.today_rows') }}", { date: dateVal, _t: Date.now() })
            .done(html => $body.html(html))
            .fail(xhr => showAjaxError($body, 'Could not load appointment list', xhr));
    }

    $('#btnRefreshToday').on('click', () => refreshList($('#todayDate').val()));
    $('#todayDate').on('change', function(){ refreshList($(this).val()); });

    // ─── Calendar ────────────────────────────────────────────────
    let mainCalendar;

    function initCalendar(resources){
        const el = document.getElementById('appointmentsMainCalendar');
        mainCalendar = new FullCalendar.Calendar(el, {
            schedulerLicenseKey: 'GPL-My-Project-Is-Open-Source',
            timeZone: 'local',
            initialView: 'resourceTimeGridDay',
            height: 600,
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'resourceTimeGridDay,resourceTimeGridThreeDay'
            },
            views: {
                resourceTimeGridDay: { buttonText: '1 day' },
                resourceTimeGridThreeDay: { type: 'resourceTimeGrid', duration: { days: 3 }, buttonText: '3 days' }
            },
            slotMinTime: '06:00:00',
            slotMaxTime: '23:00:00',
            nowIndicator: true,
            selectable: canManage,
            editable: canManage,
            eventStartEditable: canManage,
            eventDurationEditable: canManage,
            eventResourceEditable: canManage,
            resources: resources,
            events: "{{ route('appointments.events') }}",

            datesSet: function(info){
                $('#todayDate').val(ymdFromDate(info.start));
                refreshList(ymdFromDate(info.start));
            },

            eventClassNames: function(arg){
                const now = new Date();
                const s = arg.event.start;
                const e = arg.event.end || new Date(s.getTime() + 30*60000);
                return (now >= s && now < e) ? ['blinking-event'] : [];
            },

            eventDrop: function(info){
                if (!canManage) { info.revert(); return; }
                const ev = info.event, res = ev.getResources()[0];
                ajax(`{{ url('/appointments') }}/${ev.id}/move`, 'PATCH', {
                    start_at: isoLocal(ev.start),
                    end_at: isoLocal(ev.end || new Date(ev.start.getTime() + 30*60000)),
                    staff_id: res ? res.id : ''
                }).done(() => { mainCalendar.refetchEvents(); refreshList($('#todayDate').val()); })
                  .fail(xhr => { alert(xhr.responseText || 'Could not update.'); info.revert(); });
            },

            eventResize: function(info){
                if (!canManage) { info.revert(); return; }
                const ev = info.event, res = ev.getResources()[0];
                ajax(`{{ url('/appointments') }}/${ev.id}/move`, 'PATCH', {
                    start_at: isoLocal(ev.start),
                    end_at: isoLocal(ev.end || new Date(ev.start.getTime() + 30*60000)),
                    staff_id: res ? res.id : ''
                }).done(() => { mainCalendar.refetchEvents(); refreshList($('#todayDate').val()); })
                  .fail(xhr => { alert(xhr.responseText || 'Could not resize.'); info.revert(); });
            },

            eventClick: function(info){ if (canManage) openEditModal(info.event.id); },
            select: function(selInfo){
                if (!canManage) return;
                openCreateModal({
                    start_at: isoLocal(selInfo.start),
                    end_at: isoLocal(selInfo.end || new Date(selInfo.start.getTime() + 30*60000)),
                    staff_id: selInfo.resource?.id || ''
                });
            }
        });
        mainCalendar.render();
    }

    fetch("{{ route('appointments.resources') }}")
        .then(r => { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
        .then(resources => initCalendar(resources))
        .catch(err => { console.error(err); alert('Could not load calendar resources.'); });

    // ─── Modals ───────────────────────────────────────────────────
    function bindForm($container) {
        $container.find('.select2').select2({ width: '100%', dropdownParent: $container.closest('.modal') });

        function syncClientUI() {
            const has = !!$container.find('[name="client_id"]').val();
            $container.find('.new-client-fields').toggle(!has);
            $container.find('.existing-client-hint').toggle(has);
        }
        $container.find('[name="client_id"]').off('change.clientui').on('change.clientui', syncClientUI);
        syncClientUI();

        const $form = $container.find('form');
        const servicesUrl = $form.data('services-url');
        const $cat = $container.find('.js-service-category');
        const $svc = $container.find('.js-service');

        function loadServicesForCategory(catId, selectedId) {
            if (!servicesUrl || !catId) return;
            $svc.prop('disabled', true).html('<option value="">Loading...</option>').trigger('change.select2');
            $.getJSON(servicesUrl, { category_id: catId, _t: Date.now() })
                .done(resp => {
                    const list = (resp && resp.data) ? resp.data : [];
                    const opts = ['<option value="">Select service...</option>'];
                    list.forEach(s => {
                        const sel = String(selectedId) !== '' && String(s.id) === String(selectedId) ? 'selected' : '';
                        opts.push(`<option value="${s.id}" ${sel}>${$('<div/>').text(s.name).html()}</option>`);
                    });
                    $svc.prop('disabled', false).html(opts.join('')).trigger('change.select2');
                });
        }

        const initialCategory = $cat.data('initial') || $cat.val();
        const initialService  = $svc.data('initial') || $svc.val();
        if (initialCategory) {
            $cat.val(String(initialCategory)).trigger('change.select2');
            loadServicesForCategory(initialCategory, initialService);
        }
        $cat.off('change.dep').on('change.dep', function(){ loadServicesForCategory($(this).val(), ''); });

        $form.off('submit.ajax').on('submit.ajax', function(e){
            e.preventDefault();
            const url = $form.attr('action');
            const method = ($form.find('input[name="_method"]').val() || $form.attr('method') || 'POST').toUpperCase();
            ajax(url, method, $form.serialize())
                .done(resp => {
                    if (resp && resp.success) {
                        $container.closest('.modal').modal('hide');
                        if (mainCalendar) mainCalendar.refetchEvents();
                        refreshList($('#todayDate').val());
                    } else { alert('Saved but response was unexpected.'); }
                })
                .fail(xhr => alert(xhr.responseText || 'Validation failed.'));
        });

        $container.find('[data-action="delete"]').off('click.delete').on('click.delete', function(){
            const id = $(this).data('id');
            if (!confirm('Delete this appointment?')) return;
            ajax(`{{ url('/appointments') }}/${id}`, 'DELETE', {})
                .done(() => {
                    $container.closest('.modal').modal('hide');
                    if (mainCalendar) mainCalendar.refetchEvents();
                    refreshList($('#todayDate').val());
                })
                .fail(xhr => alert(xhr.responseText || 'Delete failed.'));
        });
    }

    function openCreateModal(prefill = {}) {
        const $body = $('#addApptBody').html('<div class="text-center py-5"><i class="fas fa-spinner fa-spin"></i> Loading…</div>');
        $('#addAppointmentModal').modal('show');
        $.get("{{ route('appointments.create') }}", { modal: 1, _t: Date.now() })
            .done(html => {
                $body.html(html);
                if (prefill.start_at) $body.find('[name="start_at"]').val(prefill.start_at.slice(0,16));
                if (prefill.end_at)   $body.find('[name="end_at"]').val(prefill.end_at.slice(0,16));
                if (prefill.staff_id) $body.find('[name="staff_id"]').val(prefill.staff_id).trigger('change');
                bindForm($body);
            });
    }

    function openEditModal(id) {
        const $body = $('#editApptBody').html('<div class="text-center py-5"><i class="fas fa-spinner fa-spin"></i> Loading…</div>');
        $('#editAppointmentModal').modal('show');
        $.get(`{{ url('/appointments') }}/${id}/edit`, { modal: 1, _t: Date.now() })
            .done(html => { $body.html(html); bindForm($body); });
    }

    $(document).on('click', '#btnAddAppt, .js-open-add-appointment', function(){
        if (canManage) openCreateModal();
    });
    $(document).on('click', '#todayRowsBody [data-action="edit"]', function(){
        if (canManage) openEditModal($(this).data('id'));
    });
    $(document).on('click', '#todayRowsBody [data-action="delete"]', function(){
        if (!canManage) return;
        const id = $(this).data('id');
        if (!confirm('Delete this appointment?')) return;
        ajax(`{{ url('/appointments') }}/${id}`, 'DELETE', {})
            .done(() => { if (mainCalendar) mainCalendar.refetchEvents(); refreshList($('#todayDate').val()); })
            .fail(xhr => alert(xhr.responseText || 'Delete failed.'));
    });

})();
</script>
@endpush
