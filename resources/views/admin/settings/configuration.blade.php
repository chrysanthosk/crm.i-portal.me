@extends('layouts.app')

@section('title', 'Configuration')

@section('content')
@php
    $d = $dashboard; // may be null
@endphp

<div class="container-fluid">

    <div class="d-flex align-items-center justify-content-between mb-3">
        <h1 class="h3 mb-0">Configuration</h1>
    </div>

    <div class="accordion" id="configAccordion">

        {{-- 1) SYSTEM --}}
        <div class="card">
            <div class="card-header" id="headingSystem">
                <h2 class="mb-0">
                    <button class="btn btn-link text-left w-100 d-flex justify-content-between align-items-center"
                            type="button"
                            data-toggle="collapse"
                            data-target="#collapseSystem"
                            aria-expanded="true"
                            aria-controls="collapseSystem">
                        <span><strong>System</strong></span>
                        <i class="fas fa-chevron-down"></i>
                    </button>
                </h2>
            </div>

            <div id="collapseSystem" class="collapse show" aria-labelledby="headingSystem" data-parent="#configAccordion">
                <div class="card-body">

                    <form method="POST" action="{{ route('settings.config.system.update') }}">
                        @csrf
                        @method('PUT')

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Header Display Name</label>
                                <input class="form-control" name="header_name"
                                       value="{{ old('header_name', $system->header_name ?? config('app.name')) }}" required>
                                @error('header_name') <div class="text-danger small">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">Footer Display Name</label>
                                <input class="form-control" name="footer_name"
                                       value="{{ old('footer_name', $system->footer_name ?? config('app.name')) }}" required>
                                @error('footer_name') <div class="text-danger small">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        <button class="btn btn-primary">
                            <i class="fas fa-save mr-2"></i> Save System
                        </button>
                    </form>

                </div>
            </div>
        </div>

        {{-- 2) DASHBOARD & COMPANY --}}
        <div class="card">
            <div class="card-header" id="headingCompany">
                <h2 class="mb-0">
                    <button class="btn btn-link text-left w-100 d-flex justify-content-between align-items-center collapsed"
                            type="button"
                            data-toggle="collapse"
                            data-target="#collapseCompany"
                            aria-expanded="false"
                            aria-controls="collapseCompany">
                        <span><strong>Dashboard & Company</strong></span>
                        <i class="fas fa-chevron-down"></i>
                    </button>
                </h2>
            </div>

            <div id="collapseCompany" class="collapse" aria-labelledby="headingCompany" data-parent="#configAccordion">
                <div class="card-body">

                    <form method="POST" action="{{ route('settings.config.company.update') }}">
                        @csrf
                        @method('PUT')

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Dashboard Name</label>
                                <input class="form-control" name="dashboard_name"
                                       value="{{ old('dashboard_name', $d->dashboard_name ?? '') }}"
                                       placeholder="e.g. MedSkin Dashboard">
                                @error('dashboard_name') <div class="text-danger small">{{ $message }}</div> @enderror
                                <div class="text-muted small mt-1">Optional UI label.</div>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">Company Name</label>
                                <input class="form-control" name="company_name"
                                       value="{{ old('company_name', $d->company_name ?? '') }}"
                                       required>
                                @error('company_name') <div class="text-danger small">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-md-4 mb-3">
                                <label class="form-label">Company VAT Number</label>
                                <input class="form-control" name="company_vat_number"
                                       value="{{ old('company_vat_number', $d->company_vat_number ?? '') }}"
                                       placeholder="e.g. CY123...">
                                @error('company_vat_number') <div class="text-danger small">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-md-4 mb-3">
                                <label class="form-label">Company Phone Number</label>
                                <input class="form-control" name="company_phone_number"
                                       value="{{ old('company_phone_number', $d->company_phone_number ?? '') }}"
                                       placeholder="+357 ...">
                                @error('company_phone_number') <div class="text-danger small">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-md-12 mb-3">
                                <label class="form-label">Company Address</label>
                                <textarea class="form-control" name="company_address" rows="3"
                                          placeholder="Street, City, Postcode, Country">{{ old('company_address', $d->company_address ?? '') }}</textarea>
                                @error('company_address') <div class="text-danger small">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        <button class="btn btn-primary">
                            <i class="fas fa-save mr-2"></i> Save Company
                        </button>
                    </form>

                </div>
            </div>
        </div>

        {{-- 3) SMS --}}
        <div class="card">
            <div class="card-header" id="headingSms">
                <h2 class="mb-0">
                    <button class="btn btn-link text-left w-100 d-flex justify-content-between align-items-center collapsed"
                            type="button"
                            data-toggle="collapse"
                            data-target="#collapseSms"
                            aria-expanded="false"
                            aria-controls="collapseSms">
                        <span><strong>SMS</strong></span>
                        <i class="fas fa-chevron-down"></i>
                    </button>
                </h2>
            </div>

            <div id="collapseSms" class="collapse" aria-labelledby="headingSms" data-parent="#configAccordion">
                <div class="card-body">

                    <form method="POST" action="{{ route('settings.config.sms.update') }}">
                        @csrf
                        @method('PUT')

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label d-block">Appointment SMS Enabled</label>
                                <div class="custom-control custom-switch">
                                    <input type="checkbox"
                                           class="custom-control-input"
                                           id="sms_appointments_enabled"
                                           name="sms_appointments_enabled"
                                           value="1"
                                           {{ old('sms_appointments_enabled', ($d->sms_appointments_enabled ?? false) ? '1' : '') ? 'checked' : '' }}>
                                    <label class="custom-control-label" for="sms_appointments_enabled">Enabled</label>
                                </div>
                                @error('sms_appointments_enabled') <div class="text-danger small">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">Appointment SMS Message (max 165)</label>
                                <input class="form-control"
                                       name="sms_appointments_message"
                                       maxlength="165"
                                       value="{{ old('sms_appointments_message', $d->sms_appointments_message ?? '') }}"
                                       placeholder="Reminder: your appointment is on {date} at {time}">
                                @error('sms_appointments_message') <div class="text-danger small">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label d-block">Birthday SMS Enabled</label>
                                <div class="custom-control custom-switch">
                                    <input type="checkbox"
                                           class="custom-control-input"
                                           id="sms_birthdays_enabled"
                                           name="sms_birthdays_enabled"
                                           value="1"
                                           {{ old('sms_birthdays_enabled', ($d->sms_birthdays_enabled ?? false) ? '1' : '') ? 'checked' : '' }}>
                                    <label class="custom-control-label" for="sms_birthdays_enabled">Enabled</label>
                                </div>
                                @error('sms_birthdays_enabled') <div class="text-danger small">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">Birthday SMS Message (max 165)</label>
                                <input class="form-control"
                                       name="sms_birthdays_message"
                                       maxlength="165"
                                       value="{{ old('sms_birthdays_message', $d->sms_birthdays_message ?? '') }}"
                                       placeholder="Happy Birthday from {company}!">
                                @error('sms_birthdays_message') <div class="text-danger small">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">Sent Appointments SMS Count</label>
                                <input class="form-control" value="{{ (int)($d->sms_sent_appointments_count ?? 0) }}" readonly>
                                <div class="text-muted small mt-1">Read-only counter.</div>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">Sent Birthdays SMS Count</label>
                                <input class="form-control" value="{{ (int)($d->sms_sent_birthdays_count ?? 0) }}" readonly>
                                <div class="text-muted small mt-1">Read-only counter.</div>
                            </div>
                        </div>

                        <button class="btn btn-primary">
                            <i class="fas fa-save mr-2"></i> Save SMS
                        </button>
                    </form>

                </div>
            </div>
        </div>

    </div>

</div>
@endsection
