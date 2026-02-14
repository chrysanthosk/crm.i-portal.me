@extends('layouts.app')

@section('title', 'Appointments')

@push('styles')
<link href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css" rel="stylesheet"/>
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

    #tableSection { display:none; }
    .modal-lg { max-width: 980px; }

    /* Make select2 look nicer in AdminLTE */
    .select2-container .select2-selection--single { height: calc(2.25rem + 2px); }
    .select2-container--default .select2-selection--single .select2-selection__rendered { line-height: calc(2.25rem); }
    .select2-container--default .select2-selection--single .select2-selection__arrow { height: calc(2.25rem + 2px); }

    /* ✅ Select2 + AdminLTE dark-mode fixes */
    body.dark-mode .select2-container--default .select2-selection--single {
        background-color: #343a40;
        border-color: rgba(255,255,255,.15);
        color: #f8f9fa;
    }
    body.dark-mode .select2-container--default .select2-selection--single .select2-selection__rendered { color: #f8f9fa; }
    body.dark-mode .select2-container--default .select2-selection--single .select2-selection__arrow b {
        border-color: #f8f9fa transparent transparent transparent;
    }
    body.dark-mode .select2-container--default .select2-selection--single .select2-selection__placeholder { color: rgba(255,255,255,.65); }
    body.dark-mode .select2-container--default .select2-dropdown {
        background-color: #343a40;
        border-color: rgba(255,255,255,.15);
        color: #f8f9fa;
    }
    body.dark-mode .select2-container--default .select2-results__option { color: #f8f9fa; }
    body.dark-mode .select2-container--default .select2-results__option--highlighted[aria-selected] {
        background-color: rgba(255,255,255,.12);
        color: #fff;
    }
    body.dark-mode .select2-container--default .select2-results__option[aria-selected=true] {
        background-color: rgba(255,255,255,.18);
        color: #fff;
    }
    body.dark-mode .select2-container--default .select2-search--dropdown .select2-search__field {
        background-color: #2b3035;
        border-color: rgba(255,255,255,.15);
        color: #f8f9fa;
    }
</style>
@endpush

@section('content')
<div class="container-fluid">

    <div class="d-flex align-items-center justify-content-between mb-3">
        <h1 class="h3 mb-0">Appointments</h1>

        <div class="d-flex">
            <div class="btn-group mr-2" role="group" aria-label="View toggle">
                <button id="btnViewCalendar" class="btn btn-outline-primary active">Calendar View</button>
                <button id="btnViewTable" class="btn btn-outline-primary">Table View</button>
            </div>

            <button id="btnExportAppt" class="btn btn-secondary mr-2">
                <i class="fas fa-file-csv"></i> Export CSV
            </button>

            <button id="btnAddAppt" class="btn btn-primary">
                <i class="fas fa-plus"></i> Add Appointment
            </button>
        </div>
    </div>

    <div class="card">
        <div class="card-body">

            {{-- Calendar --}}
            <div id="calendarSection">
                <div id="appointmentsMainCalendar"></div>
            </div>

            {{-- Table --}}
            <div id="tableSection">
                <div class="row mb-2">
                    <div class="col-md-3">
                        <select id="lengthMenu" class="form-control">
                            <option value="10">10 entries</option>
                            <option value="20" selected>20 entries</option>
                            <option value="50">50 entries</option>
                            <option value="100">100 entries</option>
                            <option value="200">200 entries</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select id="filterTodayAll" class="form-control">
                            <option value="today">Today's appointments</option>
                            <option value="all">All appointments</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <input type="text" id="tableSearch" class="form-control" placeholder="Search client / staff / service...">
                    </div>
                </div>

                <table id="appointmentsListTable" class="table table-bordered table-hover">
                    <thead>
                    <tr>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Client</th>
                        <th>Staff</th>
                        <th>Service</th>
                        <th>Status</th>
                        <th>Notes</th>
                        <th style="width:140px;">Action</th>
                    </tr>
                    </thead>
                    <tbody></tbody>
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
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/js/select2.min.js"></script>

<script>
(function(){
    const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

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
          <div class="alert alert-danger mb-0">
            <div class="fw-bold">${title}</div>
            <div class="small">HTTP ${xhr.status}</div>
            <pre class="small mt-2" style="white-space:pre-wrap;max-height:260px;overflow:auto;">${$('<div/>').text(trimmed).html()}</pre>
          </div>
        `);
    }

    // ✅ IMPORTANT: Return local ISO WITHOUT timezone suffix (no "Z", no offset)
    function isoLocal(dt) {
        const yyyy = dt.getFullYear();
        const MM = String(dt.getMonth()+1).padStart(2,'0');
        const dd = String(dt.getDate()).padStart(2,'0');
        const hh = String(dt.getHours()).padStart(2,'0');
        const mm = String(dt.getMinutes()).padStart(2,'0');
        const ss = String(dt.getSeconds()).padStart(2,'0');
        return `${yyyy}-${MM}-${dd}T${hh}:${mm}:${ss}`;
    }

    // ─── View toggle ─────────────────────────────────────────────
    $('#btnViewCalendar').on('click', function(){
        $(this).addClass('active');
        $('#btnViewTable').removeClass('active');
        $('#tableSection').hide();
        $('#calendarSection').show();
    });

    $('#btnViewTable').on('click', function(){
        $(this).addClass('active');
        $('#btnViewCalendar').removeClass('active');
        $('#calendarSection').hide();
        $('#tableSection').show();
        loadTable();
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
            selectable: true,

            editable: true,
            eventStartEditable: true,
            eventDurationEditable: true,
            eventResourceEditable: true,

            resources: resources,
            events: '{{ route('appointments.events') }}',

            eventClassNames: function(arg){
                const now = new Date();
                const s = arg.event.start;
                const e = arg.event.end || new Date(s.getTime() + 30*60000);
                return (now >= s && now < e) ? ['blinking-event'] : [];
            },

            eventDrop: function(info){
                const ev = info.event;
                const res = ev.getResources()[0];
                const payload = {
                    start_at: isoLocal(ev.start),
                    end_at: isoLocal(ev.end || new Date(ev.start.getTime() + 30*60000)),
                    staff_id: res ? res.id : ev.getResources()?.[0]?.id
                };

                ajax(`{{ url('/appointments') }}/${ev.id}/move`, 'PATCH', payload)
                    .done(() => mainCalendar.refetchEvents())
                    .fail((xhr) => {
                        alert(xhr.responseText || 'Could not update appointment.');
                        info.revert();
                    });
            },

            eventResize: function(info){
                const ev = info.event;
                const res = ev.getResources()[0];
                const payload = {
                    start_at: isoLocal(ev.start),
                    end_at: isoLocal(ev.end || new Date(ev.start.getTime() + 30*60000)),
                    staff_id: res ? res.id : ev.getResources()?.[0]?.id
                };

                ajax(`{{ url('/appointments') }}/${ev.id}/move`, 'PATCH', payload)
                    .done(() => mainCalendar.refetchEvents())
                    .fail((xhr) => {
                        alert(xhr.responseText || 'Could not resize appointment.');
                        info.revert();
                    });
            },

            eventClick: function(info){
                openEditModal(info.event.id);
            },

            select: function(selInfo){
                openCreateModal({
                    start_at: isoLocal(selInfo.start),
                    end_at: isoLocal(selInfo.end || new Date(selInfo.start.getTime() + 30*60000)),
                    staff_id: selInfo.resource?.id || ''
                });
            }
        });

        mainCalendar.render();
    }

    fetch('{{ route('appointments.resources') }}')
        .then(r => {
            if (!r.ok) throw new Error('Failed to load resources: HTTP ' + r.status);
            return r.json();
        })
        .then(resources => initCalendar(resources))
        .catch(err => {
            console.error(err);
            alert('Could not load calendar resources. Check console/logs.');
        });

    // ─── Modals ──────────────────────────────────────────────────
    function bindForm($container) {
        // Select2
        $container.find('.select2').select2({ width: '100%', dropdownParent: $container.closest('.modal') });

        // Toggle existing/new client UI
        function syncClientUI() {
            const clientId = $container.find('[name="client_id"]').val();
            const usingExisting = !!clientId;

            $container.find('.new-client-fields').toggle(!usingExisting);
            $container.find('.existing-client-hint').toggle(usingExisting);
        }
        $container.find('[name="client_id"]').on('change', syncClientUI);
        syncClientUI();

        // ✅ Dependent dropdown: Service Category -> Service
        const $form = $container.find('form');
        const servicesUrl = $form.data('services-url');

        const $cat = $container.find('.js-service-category');
        const $svc = $container.find('.js-service');

        function setServiceLoading() {
            $svc.prop('disabled', true);
            $svc.html('<option value="">Loading services...</option>');
            $svc.trigger('change.select2'); // refresh select2
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

            $.getJSON(servicesUrl, { category_id: categoryId })
                .done(function(resp){
                    const list = (resp && resp.data) ? resp.data : [];
                    populateServices(list, selectedId);
                })
                .fail(function(xhr){
                    console.error('servicesByCategory failed', xhr.status, xhr.responseText);
                    setServiceEmpty('Could not load services');
                });
        }

        // initial load (important for edit modal)
        const initialCategory = $cat.data('initial') || $cat.val();
        const initialService = $svc.data('initial') || $svc.val();

        if (initialCategory) {
            // ensure category select shows initial value, then load services
            $cat.val(String(initialCategory)).trigger('change.select2');
            loadServicesForCategory(initialCategory, initialService);
        } else {
            setServiceEmpty('Select category first...');
        }

        // on change
        $cat.off('change.dep').on('change.dep', function(){
            const catId = $(this).val();
            // when category changes, clear service selection
            loadServicesForCategory(catId, '');
        });

        // Submit
        $container.find('form').on('submit', function(e){
            e.preventDefault();

            const $form = $(this);
            const url = $form.attr('action');
            const method = $form.find('input[name="_method"]').val() || $form.attr('method') || 'POST';

            ajax(url, method.toUpperCase(), $form.serialize())
                .done((resp) => {
                    if (resp && resp.success) {
                        $container.closest('.modal').modal('hide');
                        if (mainCalendar) mainCalendar.refetchEvents();
                        if (table && table.ajax) table.ajax.reload();
                    } else {
                        alert('Saved but response was unexpected.');
                    }
                })
                .fail((xhr) => {
                    alert(xhr.responseText || 'Validation failed.');
                });
        });

        // Delete (edit modal only)
        $container.find('[data-action="delete"]').on('click', function(){
            const id = $(this).data('id');
            if (!confirm('Delete this appointment?')) return;

            ajax(`{{ url('/appointments') }}/${id}`, 'DELETE', {})
                .done(() => {
                    $container.closest('.modal').modal('hide');
                    if (mainCalendar) mainCalendar.refetchEvents();
                    if (table && table.ajax) table.ajax.reload();
                })
                .fail((xhr) => alert(xhr.responseText || 'Delete failed.'));
        });
    }

    function openCreateModal(prefill = {}) {
        const $modal = $('#addAppointmentModal');
        const $body = $('#addApptBody').html('<div class="text-center py-5"><i class="fas fa-spinner fa-spin"></i> Loading…</div>');
        $modal.modal('show');

        $.get('{{ route('appointments.create') }}', { modal: 1 })
            .done(function(html){
                $body.html(html);

                // prefill fields if passed (datetime-local needs YYYY-MM-DDTHH:mm)
                if (prefill.start_at) $body.find('[name="start_at"]').val(prefill.start_at.slice(0,16));
                if (prefill.end_at) $body.find('[name="end_at"]').val(prefill.end_at.slice(0,16));
                if (prefill.staff_id) $body.find('[name="staff_id"]').val(prefill.staff_id).trigger('change');

                bindForm($body);
            })
            .fail(function(xhr){
                showAjaxError($body, 'Could not load appointment form', xhr);
            });
    }

    function openEditModal(id) {
        const $modal = $('#editAppointmentModal');
        const $body = $('#editApptBody').html('<div class="text-center py-5"><i class="fas fa-spinner fa-spin"></i> Loading…</div>');
        $modal.modal('show');

        $.get(`{{ url('/appointments') }}/${id}/edit`, { modal: 1 })
            .done(function(html){
                $body.html(html);
                bindForm($body);
            })
            .fail(function(xhr){
                showAjaxError($body, 'Could not load appointment', xhr);
            });
    }

    $('#btnAddAppt').on('click', function(){
        openCreateModal();
    });

    // ─── Table view (DataTables) ──────────────────────────────────
    let table;

    function loadTable(){
        if ($.fn.DataTable.isDataTable('#appointmentsListTable')) {
            table.ajax.reload();
            return;
        }

        table = $('#appointmentsListTable').DataTable({
            ajax: {
                url: '{{ route('appointments.list') }}',
                data: function(d){
                    d.flag = $('#filterTodayAll').val();
                }
            },
            pageLength: parseInt($('#lengthMenu').val(), 10),
            lengthChange: false,
            searching: true,
            columns: [
                { data: 'date' },
                { data: 'time' },
                { data: 'client_name' },
                { data: 'staff_name' },
                { data: 'service_name' },
                { data: 'status' },
                { data: 'notes' },
                {
                    data: null,
                    orderable: false,
                    render: function(data, type, row){
                        return `
                          <button class="btn btn-sm btn-outline-primary mr-1" data-action="edit" data-id="${row.id}">
                            <i class="fas fa-edit"></i>
                          </button>
                          <button class="btn btn-sm btn-outline-danger" data-action="delete" data-id="${row.id}">
                            <i class="fas fa-trash"></i>
                          </button>
                        `;
                    }
                }
            ]
        });

        $('#lengthMenu').off('change').on('change', function(){
            table.page.len(parseInt(this.value, 10)).draw();
        });

        $('#filterTodayAll').off('change').on('change', loadTable);

        $('#tableSearch').off('keyup').on('keyup', function(){
            table.search(this.value).draw();
        });

        $('#appointmentsListTable').on('click', '[data-action="edit"]', function(){
            openEditModal($(this).data('id'));
        });

        $('#appointmentsListTable').on('click', '[data-action="delete"]', function(){
            const id = $(this).data('id');
            if (!confirm('Delete this appointment?')) return;

            ajax(`{{ url('/appointments') }}/${id}`, 'DELETE', {})
                .done(() => table.ajax.reload())
                .fail((xhr) => alert(xhr.responseText || 'Delete failed.'));
        });
    }

    // ─── Export CSV ───────────────────────────────────────────────
    $('#btnExportAppt').on('click', function(){
        const flag = $('#filterTodayAll').val() || 'today';
        window.location = `{{ route('appointments.export') }}?flag=${encodeURIComponent(flag)}`;
    });

})();
</script>
@endpush
