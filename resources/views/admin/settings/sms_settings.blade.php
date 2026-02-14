@extends('layouts.app')

@section('title', 'SMS Settings')

@section('content')
<div class="container-fluid">

    <div class="d-flex align-items-center justify-content-between mb-3">
        <h1 class="h3 mb-0">SMS Settings</h1>

        <div class="d-flex">
            <button class="btn btn-outline-primary mr-2" data-toggle="modal" data-target="#testSmsModal">
                <i class="fas fa-paper-plane mr-1"></i> Send Test SMS
            </button>

            <button class="btn btn-primary" data-toggle="modal" data-target="#providerModal" id="btnAddProvider">
                <i class="fas fa-plus mr-1"></i> Add Provider
            </button>
        </div>
    </div>

    {{-- Providers Table --}}
    <div class="card mb-4">
        <div class="card-header"><strong>SMS Providers</strong></div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-striped mb-0">
                    <thead>
                        <tr>
                            <th style="width:80px;">ID</th>
                            <th>Name</th>
                            <th>Docs URL</th>
                            <th class="text-center" style="width:110px;">Active?</th>
                            <th style="width:220px;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($providers as $p)
                            <tr>
                                <td>{{ $p->id }}</td>
                                <td class="font-weight-bold">{{ $p->name }}</td>
                                <td>
                                    @if(!empty($p->doc_url))
                                        <a href="{{ $p->doc_url }}" target="_blank" rel="noopener">Docs</a>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    <input type="checkbox"
                                           class="toggle-provider-active"
                                           data-id="{{ $p->id }}"
                                           {{ $p->is_active ? 'checked' : '' }}>
                                </td>
                                <td>
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-info btn-edit-provider"
                                        data-id="{{ $p->id }}"
                                        data-name="{{ $p->name }}"
                                        data-doc="{{ $p->doc_url }}"
                                    >
                                        <i class="fas fa-edit"></i> Edit
                                    </button>

                                    <form method="POST"
                                          action="{{ route('settings.sms.providers.delete', $p) }}"
                                          class="d-inline"
                                          onsubmit="return confirm('Delete provider?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-danger">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="text-muted small mt-2">
                Built-in provider keys: <code>sms.to</code>, <code>twilio</code>, <code>infobip</code>.
            </div>
        </div>
    </div>

    {{-- Priority --}}
    <div class="card mb-4">
        <div class="card-header"><strong>Provider Priority</strong></div>
        <div class="card-body">
            <div id="priorityAlert" class="alert alert-success" style="display:none;">
                Priority saved successfully.
            </div>

            <ul id="providerPriorityList" class="list-group">
                @foreach ($providersPriority as $pp)
                    <li class="list-group-item draggable-provider" data-id="{{ $pp->id }}">
                        <i class="fas fa-arrows-alt-v mr-2"></i>
                        {{ $pp->name }}
                    </li>
                @endforeach
            </ul>

            <button id="savePriority" class="btn btn-primary mt-3">
                <i class="fas fa-save mr-1"></i> Save Priority
            </button>
        </div>
    </div>

    {{-- Configure SMS API --}}
    <div class="card">
        <div class="card-header"><strong>Configure SMS API</strong></div>
        <div class="card-body">
            <form method="POST" action="{{ route('settings.sms.settings.save') }}">
                @csrf

                <div class="form-group">
                    <label for="provider_id">SMS Provider <span class="text-danger">*</span></label>
                    <select id="provider_id" name="provider_id" class="form-control" required>
                        <option value="">— Select provider —</option>
                        @foreach($activeProviders as $p)
                            <option value="{{ $p->id }}">{{ $p->name }}</option>
                        @endforeach
                    </select>
                    <div class="text-muted small mt-1">Only active providers appear here.</div>
                </div>

                <div class="form-group">
                    <label for="api_key">API Key <span class="text-danger">*</span></label>
                    <input type="text" id="api_key" name="api_key" class="form-control" required>
                </div>

                <div class="form-group">
                    <label for="api_secret">API Secret</label>
                    <input type="text" id="api_secret" name="api_secret" class="form-control">
                    <div class="text-muted small mt-1">
                        <strong>Twilio:</strong> API Secret = Auth Token. &nbsp;
                        <strong>Infobip:</strong> you may put Base URL here (optional).
                    </div>
                </div>

                <div class="form-group">
                    <label for="sender_id">Sender ID</label>
                    <input type="text" id="sender_id" name="sender_id" class="form-control"
                           placeholder="E.g. MyClinic or +3579xxxxxxx">
                </div>

                <div class="form-check mb-3">
                    <input type="checkbox" id="is_enabled" name="is_enabled" class="form-check-input" value="1">
                    <label for="is_enabled" class="form-check-label">
                        Enable SMS sending
                    </label>
                </div>

                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save mr-1"></i> Save Settings
                </button>
            </form>
        </div>
    </div>

</div>

{{-- Add/Edit Provider Modal --}}
<div class="modal fade" id="providerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="providerModalForm" action="{{ route('settings.sms.providers.save') }}">
                @csrf
                <input type="hidden" name="prov_id" id="prov_id" value="">

                <div class="modal-header">
                    <h5 class="modal-title" id="providerModalLabel">Add SMS Provider</h5>
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                </div>

                <div class="modal-body">
                    <div class="form-group">
                        <label for="prov_name">Provider Name</label>
                        <input type="text" id="prov_name" name="prov_name" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label for="prov_doc">Documentation URL</label>
                        <input type="url" id="prov_doc" name="prov_doc" class="form-control">
                    </div>

                    <div class="text-muted small">
                        Tip: built-in provider keys are <code>sms.to</code>, <code>twilio</code>, <code>infobip</code>.
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Provider</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Test SMS Modal --}}
<div class="modal fade" id="testSmsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('settings.sms.test') }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Send Test SMS</h5>
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                </div>

                <div class="modal-body">

                    <div class="form-group">
                        <label>Force Provider (optional)</label>
                        <select name="provider_id" class="form-control">
                            <option value="">Auto (use enabled providers + priority)</option>
                            @foreach($providers as $p)
                                <option value="{{ $p->id }}">{{ $p->name }}</option>
                            @endforeach
                        </select>
                        <div class="text-muted small mt-1">
                            If selected, the test will use that provider even if priority is different.
                        </div>
                    </div>

                    <div class="form-group">
                        <label>To (E.164 format)</label>
                        <input type="text" name="to" class="form-control" placeholder="+3579xxxxxxx" required>
                        <div class="text-muted small mt-1">Use full international format (e.g. +357...).</div>
                    </div>

                    <div class="form-group">
                        <label>Message</label>
                        <textarea name="message" class="form-control" rows="3" maxlength="165" required>Test message from dashboard</textarea>
                        <div class="text-muted small mt-1">Max 165 chars.</div>
                    </div>

                    <div class="alert alert-info mb-0">
                        Auto mode uses <strong>enabled</strong> providers and <strong>priority</strong>.
                        Forced mode uses the selected provider (credentials must exist).
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-paper-plane mr-1"></i> Send
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/jquery-ui-dist@1.13.3/jquery-ui.min.css">
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/jquery-ui-dist@1.13.3/jquery-ui.min.js"></script>
<script>
$(function(){

    // Add Provider modal (no inline onclick)
    $('#btnAddProvider').on('click', function(){
        $('#prov_id').val('');
        $('#prov_name').val('');
        $('#prov_doc').val('');
        $('#providerModalLabel').text('Add SMS Provider');
    });

    // Edit Provider modal (robust)
    $(document).on('click', '.btn-edit-provider', function(){
        const id = $(this).data('id');
        const name = $(this).data('name') || '';
        const doc = $(this).data('doc') || '';

        $('#prov_id').val(id);
        $('#prov_name').val(name);
        $('#prov_doc').val(doc);
        $('#providerModalLabel').text('Edit SMS Provider');
        $('#providerModal').modal('show');
    });

    // Sortable priority list
    $('#providerPriorityList').sortable({ handle: '.fa-arrows-alt-v' });

    // Save priority
    $('#savePriority').click(function(){
        var order = $('#providerPriorityList .draggable-provider')
            .map((i,el)=>$(el).data('id')).get();

        $.post("{{ route('settings.sms.providers.priority') }}", {
            _token: "{{ csrf_token() }}",
            order: order
        })
        .done(() => $('#priorityAlert').fadeIn().delay(2000).fadeOut())
        .fail(() => alert('Error saving priority'));
    });

    // Toggle is_active
    $('.toggle-provider-active').change(function(){
        const $chk   = $(this),
              id     = $chk.data('id'),
              active = $chk.is(':checked') ? 1 : 0;

        $.post("{{ url('/settings/sms/providers') }}/" + id + "/toggle", {
            _token: "{{ csrf_token() }}",
            is_active: active
        })
        .done(function(){
            location.reload();
        })
        .fail(function(){
            alert('Could not update provider status.');
            $chk.prop('checked', !active);
        });
    });

    // Provider change => load stored settings
    $('#provider_id').on('change', function() {
        const pid = $(this).val();
        if (!pid) {
            $('#api_key, #api_secret, #sender_id').val('');
            $('#is_enabled').prop('checked', false);
            return;
        }

        $.getJSON("{{ url('/settings/sms/providers') }}/" + pid + "/settings")
            .done(function(data) {
                $('#api_key').val(data.api_key || '');
                $('#api_secret').val(data.api_secret || '');
                $('#sender_id').val(data.sender_id || '');
                $('#is_enabled').prop('checked', parseInt(data.is_enabled || 0) === 1);
            })
            .fail(function() {
                alert('Could not load settings for that provider.');
            });
    });

    // trigger on load
    $('#provider_id').trigger('change');
});
</script>
@endpush
