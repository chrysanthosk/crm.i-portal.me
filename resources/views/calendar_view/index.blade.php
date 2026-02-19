@extends('layouts.app')

@section('title', 'Calendar View')

@push('styles')
<link href="https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/css/select2.min.css" rel="stylesheet"/>

<style>
    .blinking-event { animation: blink 1s infinite alternate; }
    @keyframes blink { from { opacity: 1; } to { opacity: .35; } }

    #appointmentsMainCalendar {
        border: 1px solid rgba(0,0,0,.15);
        height: 650px;
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
<div class="container-fluid">

    <div class="d-flex align-items-center justify-content-between mb-3">
        <h1 class="h3 mb-0">Calendar View</h1>

        @if($canManage)
            <button id="btnAddAppt" class="btn btn-primary">
                <i class="fas fa-plus"></i> Add Appointment
            </button>
        @endif
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <div id="appointmentsMainCalendar"></div>
            <small class="text-muted d-block mt-2">
                @if($canManage)
                    Drag/resize to reschedule. Click an event to edit. Select a slot to create.
                @else
                    View-only access.
                @endif
            </small>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex align-items-center justify-content-between">
            <h5 class="mb-0">Appointments List</h5>

            <div class="d-flex align-items-center">
                <input type="date" id="todayDate" class="form-control form-control-sm mr-2" value="{{ $today }}">
                <button class="btn btn-sm btn-outline-secondary" id="btnRefreshToday">
                    <i class="fas fa-sync"></i> Refresh
                </button>
            </div>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-bordered mb-0">
                    <thead class="thead-dark">
                        <tr>
                            <th style="width:180px;">Time</th>
                            <th>Client</th>
                            <th>Staff</th>
                            <th>Service</th>
                            <th>Notes</th>
                            <th style="width:220px;">Action</th>
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

    // local ISO without timezone suffix
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
            height: 650,
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'resourceTimeGridDay,resourceTimeGridThreeDay,resourceTimeGridFiveDay'
            },
            views: {
                resourceTimeGridDay: { buttonText: '1 day' },
                resourceTimeGridThreeDay: { type: 'resourceTimeGrid', duration: { days: 3 }, buttonText: '3 days' },
                resourceTimeGridFiveDay: { type: 'resourceTimeGrid', duration: { days: 5 }, buttonText: '5 days' }
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

            // ✅ keep the list in sync when user clicks prev/next/today
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

    // ─── Modals (match appointments page behavior) ────────────────
    function bindForm($container) {
        // Select2
        $container.find('.select2').select2({
            width: '100%',
            dropdownParent: $container.closest('.modal')
        });

        // ✅ Toggle existing/new client UI
        function syncClientUI() {
            const clientId = $container.find('[name="client_id"]').val();
            const usingExisting = !!clientId;

            $container.find('.new-client-fields').toggle(!usingExisting);
            $container.find('.existing-client-hint').toggle(usingExisting);
        }
        $container.find('[name="client_id"]').off('change.clientui').on('change.clientui', syncClientUI);
        syncClientUI();

        // ✅ Dependent dropdown: Service Category -> Service
        const $form = $container.find('form');
        const servicesUrl = $form.data('services-url');

        const $cat = $container.find('.js-service-category');
        const $svc = $container.find('.js-service');

        function setServiceLoading() {
            $svc.prop('disabled', true);
            $svc.html('<option value="">Loading services...</option>');
            $svc.trigger('change.select2');
        }
        function setServiceEmpty(msg) {
            $svc.prop('disabled', false);
            $svc.html(`<option value="">${msg || 'Select service...'}</option>`);
            $svc.trigger('change.select2');
        }
        function populateServices(services, selectedId) {
            const opts = ['<option value="">Select service...</option>'];
            services.forEach(s => {
                const sel = (String(selectedId) !== '' && String(s.id) === String(selectedId)) ? 'selected' : '';
                opts.push(`<option value="${s.id}" ${sel}>${$('<div/>').text(s.name).html()}</option>`);
            });
            $svc.prop('disabled', false);
            $svc.html(opts.join(''));
            $svc.trigger('change.select2');
        }
        function loadServicesForCategory(categoryId, selectedId) {
            if (!servicesUrl) return;

            if (!categoryId) {
                setServiceEmpty('Select category first...');
                return;
            }

            setServiceLoading();
            $.getJSON(servicesUrl, { category_id: categoryId, _t: Date.now() })
                .done(function(resp){
                    const list = (resp && resp.data) ? resp.data : [];
                    populateServices(list, selectedId);
                })
                .fail(function(xhr){
                    console.error('servicesByCategory failed', xhr.status, xhr.responseText);
                    setServiceEmpty('Could not load services');
                });
        }

        const initialCategory = $cat.data('initial') || $cat.val();
        const initialService  = $svc.data('initial') || $svc.val();

        if (initialCategory) {
            $cat.val(String(initialCategory)).trigger('change.select2');
            loadServicesForCategory(initialCategory, initialService);
        } else {
            setServiceEmpty('Select category first...');
        }

        $cat.off('change.dep').on('change.dep', function(){
            loadServicesForCategory($(this).val(), '');
        });

        // ✅ Submit via AJAX (prevents raw JSON full-page response)
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
                .fail((xhr) => {
                    alert(xhr.responseText || 'Validation failed.');
                });
        });

        // ✅ Delete (edit modal only)
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

                // prefill datetime-local expects YYYY-MM-DDTHH:mm
                if (prefill.start_at) $body.find('[name="start_at"]').val(prefill.start_at.slice(0,16));
                if (prefill.end_at)   $body.find('[name="end_at"]').val(prefill.end_at.slice(0,16));
                if (prefill.staff_id) $body.find('[name="staff_id"]').val(prefill.staff_id).trigger('change');

                bindForm($body);
            })
            .fail(function(xhr){
                // showAjaxError expects tbody; here we use div
                console.error('Could not load appointment form', xhr.status, xhr.responseText);
                $body.html('<div class="alert alert-danger mb-0">Could not load appointment form. Check console/logs.</div>');
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
            })
            .fail(function(xhr){
                console.error('Could not load appointment', xhr.status, xhr.responseText);
                $body.html('<div class="alert alert-danger mb-0">Could not load appointment. Check console/logs.</div>');
            });
    }

    $('#btnAddAppt').on('click', function(){
        if (!canManage) return;
        openCreateModal();
    });

    // ✅ Delegated actions for rows loaded via AJAX
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
