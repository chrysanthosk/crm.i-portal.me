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
</style>
@endpush

@section('content')
<div class="alert alert-light border mb-3">
    <div class="d-flex justify-content-between align-items-start flex-wrap">
        <div>
            <div class="text-muted small text-uppercase">{{ $roleExperience['label'] ?? 'Workspace' }}</div>
            <div>{{ $roleExperience['summary'] ?? 'Daily operations overview.' }}</div>
        </div>
        <span class="badge badge-secondary mt-2 mt-md-0">role: {{ $roleKey }}</span>
    </div>
</div>

<div class="row mb-3">
    <div class="col-md-3">
        <div class="small-box bg-info">
            <div class="inner">
                <h3>{{ $stats['todayAppointments'] }}</h3>
                <p>Today's Appointments</p>
            </div>
            <div class="icon"><i class="fas fa-calendar-day"></i></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="small-box bg-success">
            <div class="inner">
                <h3>€{{ number_format($stats['todaySales'], 2) }}</h3>
                <p>Today's Sales</p>
            </div>
            <div class="icon"><i class="fas fa-cash-register"></i></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="small-box bg-primary">
            <div class="inner">
                <h3>{{ $stats['totalClients'] }}</h3>
                <p>Total Clients</p>
            </div>
            <div class="icon"><i class="fas fa-users"></i></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="small-box bg-warning">
            <div class="inner">
                <h3>{{ $stats['totalServices'] }}</h3>
                <p>Services</p>
            </div>
            <div class="icon"><i class="fas fa-spa"></i></div>
        </div>
    </div>
</div>

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
                        <thead class="thead-dark" style="position: sticky; top: 0; z-index: 1;">
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

<script>
(function(){
    const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    const canManage = @json((bool)$canManage);

    function ajax(url, method, data) {
        return $.ajax({
            url,
            method,
            data,
            headers: { 'X-CSRF-TOKEN': csrf }
        });
    }

    function showAjaxError($target, title, xhr) {
        console.error(title, xhr.status, xhr.responseText);
        const body = (xhr && xhr.responseText) ? String(xhr.responseText) : '';
        const trimmed = body.slice(0, 6000);

        $target.html(`
          <tr>
            <td colspan="6">
              <div class="alert alert-danger mb-0">
                <div class="fw-bold">${title}</div>
                <div class="small">HTTP ${xhr.status}</div>
                <pre class="small mt-2" style="white-space:pre-wrap;max-height:260px;overflow:auto;">${$('<div/>').text(trimmed).html()}</pre>
              </div>
            </td>
          </tr>
        `);
    }

    function isoLocal(dt) {
        const yyyy = dt.getFullYear();
        const MM = String(dt.getMonth()+1).padStart(2,'0');
        const dd = String(dt.getDate()).padStart(2,'0');
        const hh = String(dt.getHours()).padStart(2,'0');
        const mm = String(dt.getMinutes()).padStart(2,'0');
        const ss = String(dt.getSeconds()).padStart(2,'0');
        return `${yyyy}-${MM}-${dd}T${hh}:${mm}:${ss}`;
    }

    function ymdFromDate(d) {
        const yyyy = d.getFullYear();
        const MM = String(d.getMonth()+1).padStart(2,'0');
        const dd = String(d.getDate()).padStart(2,'0');
        return `${yyyy}-${MM}-${dd}`;
    }

    // ─── List Refresh ────────────────────────────────────────────
    function refreshList(dateVal) {
        const $body = $('#todayRowsBody');
        $body.html(`<tr><td colspan="6" class="text-center py-3 text-muted"><i class="fas fa-spinner fa-spin"></i> Loading…</td></tr>`);

        $.get("{{ route('calendar_view.today_rows') }}", { date: dateVal, _t: Date.now() })
            .done(function(html){
                $body.html(html);
            })
            .fail(function(xhr){
                showAjaxError($body, 'Could not load appointment list', xhr);
            });
    }

    $('#btnRefreshToday').on('click', function(){
        refreshList($('#todayDate').val());
    });

    $('#todayDate').on('change', function(){
        refreshList($(this).val());
    });

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
                const d = info.start;
                const dateVal = ymdFromDate(d);
                $('#todayDate').val(dateVal);
                refreshList(dateVal);
            },

            eventClassNames: function(arg){
                const now = new Date();
                const s = arg.event.start;
                const e = arg.event.end || new Date(s.getTime() + 30*60000);
                return (now >= s && now < e) ? ['blinking-event'] : [];
            },

            eventDrop: function(info){
                if (!canManage) { info.revert(); return; }
                const ev = info.event;
                const res = ev.getResources()[0];
                const payload = {
                    start_at: isoLocal(ev.start),
                    end_at: isoLocal(ev.end || new Date(ev.start.getTime() + 30*60000)),
                    staff_id: res ? res.id : ''
                };
                ajax(`{{ url('/appointments') }}/${ev.id}/move`, 'PATCH', payload)
                    .done(() => {
                        mainCalendar.refetchEvents();
                        refreshList($('#todayDate').val());
                    })
                    .fail((xhr) => {
                        alert(xhr.responseText || 'Could not update appointment.');
                        info.revert();
                    });
            },

            eventResize: function(info){
                if (!canManage) { info.revert(); return; }
                const ev = info.event;
                const res = ev.getResources()[0];
                const payload = {
                    start_at: isoLocal(ev.start),
                    end_at: isoLocal(ev.end || new Date(ev.start.getTime() + 30*60000)),
                    staff_id: res ? res.id : ''
                };
                ajax(`{{ url('/appointments') }}/${ev.id}/move`, 'PATCH', payload)
                    .done(() => {
                        mainCalendar.refetchEvents();
                        refreshList($('#todayDate').val());
                    })
                    .fail((xhr) => {
                        alert(xhr.responseText || 'Could not resize appointment.');
                        info.revert();
                    });
            },

            eventClick: function(info){
                if (!canManage) return;
                openEditModal(info.event.id);
            },

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
        .then(r => {
            if (!r.ok) throw new Error('Failed to load resources: HTTP ' + r.status);
            return r.json();
        })
        .then(resources => initCalendar(resources))
        .catch(err => {
            console.error(err);
            alert('Could not load calendar resources. Check console/logs.');
        });

    // ─── Modals ────────────────
    function bindForm($container) {
        $container.find('.select2').select2({
            width: '100%',
            dropdownParent: $container.closest('.modal')
        });

        function syncClientUI() {
            const clientId = $container.find('[name="client_id"]').val();
            const usingExisting = !!clientId;
            $container.find('.new-client-fields').toggle(!usingExisting);
            $container.find('.existing-client-hint').toggle(usingExisting);
        }
        $container.find('[name="client_id"]').off('change.clientui').on('change.clientui', syncClientUI);
        syncClientUI();

        const $form = $container.find('form');
        const servicesUrl = $form.data('services-url');
        const $cat = $container.find('.js-service-category');
        const $svc = $container.find('.js-service');

        function loadServicesForCategory(categoryId, selectedId) {
            if (!servicesUrl || !categoryId) return;
            $svc.prop('disabled', true).html('<option value="">Loading...</option>').trigger('change.select2');
            $.getJSON(servicesUrl, { category_id: categoryId, _t: Date.now() })
                .done(function(resp){
                    const list = (resp && resp.data) ? resp.data : [];
                    const opts = ['<option value="">Select service...</option>'];
                    list.forEach(s => {
                        const sel = (String(selectedId) !== '' && String(s.id) === String(selectedId)) ? 'selected' : '';
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

        $cat.off('change.dep').on('change.dep', function(){
            loadServicesForCategory($(this).val(), '');
        });

        $form.off('submit.ajax').on('submit.ajax', function(e){
            e.preventDefault();
            const url = $form.attr('action');
            const method = ($form.find('input[name="_method"]').val() || $form.attr('method') || 'POST').toUpperCase();

            ajax(url, method, $form.serialize())
                .done((resp) => {
                    if (resp && resp.success) {
                        $container.closest('.modal').modal('hide');
                        if (mainCalendar) mainCalendar.refetchEvents();
                        refreshList($('#todayDate').val());
                    } else {
                        alert('Saved but response was unexpected.');
                    }
                })
                .fail((xhr) => alert(xhr.responseText || 'Validation failed.'));
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
                .fail((xhr) => alert(xhr.responseText || 'Delete failed.'));
        });
    }

    function openCreateModal(prefill = {}) {
        const $modal = $('#addAppointmentModal');
        const $body = $('#addApptBody').html('<div class="text-center py-5"><i class="fas fa-spinner fa-spin"></i> Loading…</div>');
        $modal.modal('show');
        $.get("{{ route('appointments.create') }}", { modal: 1, _t: Date.now() })
            .done(function(html){
                $body.html(html);
                if (prefill.start_at) $body.find('[name="start_at"]').val(prefill.start_at.slice(0,16));
                if (prefill.end_at)   $body.find('[name="end_at"]').val(prefill.end_at.slice(0,16));
                if (prefill.staff_id) $body.find('[name="staff_id"]').val(prefill.staff_id).trigger('change');
                bindForm($body);
            });
    }

    function openEditModal(id) {
        const $modal = $('#editAppointmentModal');
        const $body = $('#editApptBody').html('<div class="text-center py-5"><i class="fas fa-spinner fa-spin"></i> Loading…</div>');
        $modal.modal('show');
        $.get(`{{ url('/appointments') }}/${id}/edit`, { modal: 1, _t: Date.now() })
            .done(function(html){
                $body.html(html);
                bindForm($body);
            });
    }

    $(document).on('click', '#btnAddAppt, .js-open-add-appointment', function(){
        if (!canManage) return;
        openCreateModal();
    });

    $(document).on('click', '#todayRowsBody [data-action="edit"]', function(){
        if (!canManage) return;
        openEditModal($(this).data('id'));
    });

    $(document).on('click', '#todayRowsBody [data-action="delete"]', function(){
        if (!canManage) return;
        const id = $(this).data('id');
        if (!confirm('Delete this appointment?')) return;
        ajax(`{{ url('/appointments') }}/${id}`, 'DELETE', {})
            .done(() => {
                if (mainCalendar) mainCalendar.refetchEvents();
                refreshList($('#todayDate').val());
            })
            .fail((xhr) => alert(xhr.responseText || 'Delete failed.'));
    });

})();
</script>
@endpush
