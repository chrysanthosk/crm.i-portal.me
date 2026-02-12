<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\Service;
use App\Models\Staff;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AppointmentController extends Controller
{
    public function index()
    {
        return view('appointments.index');
    }

    public function resources()
    {
        // FullCalendar Scheduler resources (staff)
        $staff = Staff::query()
            ->with('user:id,name')
            ->orderBy('id')
            ->get();

        $resources = $staff->map(function ($s) {
            $title = $s->user?->name ?? ('Staff #'.$s->id);
            return [
                'id'    => (string)$s->id,
                'title' => $title,
            ];
        })->values();

        return response()->json($resources);
    }

    public function events(Request $request)
    {
        // FullCalendar sends start/end; we can ignore or filter if you want
        $tz = config('app.timezone');

        $q = Appointment::query()
            ->with([
                'staff.user:id,name',
                'service:id,name',
            ])
            ->orderBy('start_at');

        $appointments = $q->get();

        $events = $appointments->map(function ($a) use ($tz) {
            $start = $this->formatForCalendar($a->start_at, $tz);
            $end   = $this->formatForCalendar($a->end_at, $tz);

            $staffName = $a->staff?->user?->name ?? ('Staff #'.$a->staff_id);
            $serviceName = $a->service?->name ?? 'Service';
            $clientLabel = $a->client_id
                ? trim(($a->client?->first_name ?? '').' '.($a->client?->last_name ?? '')) ?: ($a->client?->email ?? 'Client')
                : ($a->client_name ?: 'Client');

            return [
                'id' => (string)$a->id,
                'resourceId' => (string)$a->staff_id,
                'title' => $clientLabel . ' • ' . $serviceName,
                'start' => $start,
                'end' => $end,
                'editable' => true,
                'startEditable' => true,
                'durationEditable' => true,
                'resourceEditable' => true,
                'extendedProps' => [
                    'staff' => $staffName,
                    'status' => $a->status,
                ],
            ];
        })->values();

        return response()->json($events);
    }

    public function create(Request $request)
    {
        $staff = Staff::query()->with('user:id,name')->orderBy('id')->get();
        $clients = Client::query()->orderBy('first_name')->orderBy('last_name')->get();
        $services = Service::query()->orderBy('name')->get();

        // modal partial
        if ($request->boolean('modal')) {
            return view('appointments._form', [
                'mode' => 'create',
                'appointment' => new Appointment(),
                'staff' => $staff,
                'clients' => $clients,
                'services' => $services,
            ]);
        }

        return view('appointments.create', [
            'staff' => $staff,
            'clients' => $clients,
            'services' => $services,
        ]);
    }

    public function edit(Request $request, Appointment $appointment)
    {
        $staff = Staff::query()->with('user:id,name')->orderBy('id')->get();
        $clients = Client::query()->orderBy('first_name')->orderBy('last_name')->get();
        $services = Service::query()->orderBy('name')->get();

        if ($request->boolean('modal')) {
            return view('appointments._form', [
                'mode' => 'edit',
                'appointment' => $appointment,
                'staff' => $staff,
                'clients' => $clients,
                'services' => $services,
            ]);
        }

        return view('appointments.edit', [
            'appointment' => $appointment,
            'staff' => $staff,
            'clients' => $clients,
            'services' => $services,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateAppointment($request);

        // Parse local datetime-local inputs as app timezone and store as plain datetime
        $data['start_at'] = $this->parseLocalDateTime($data['start_at'])->format('Y-m-d H:i:s');
        $data['end_at']   = $this->parseLocalDateTime($data['end_at'])->format('Y-m-d H:i:s');

        $appointment = Appointment::create($data);

        // Your modal submit expects JSON { success: true }
        return response()->json(['success' => true, 'id' => $appointment->id]);
    }

    public function update(Request $request, Appointment $appointment)
    {
        $data = $this->validateAppointment($request, $appointment->id);

        $data['start_at'] = $this->parseLocalDateTime($data['start_at'])->format('Y-m-d H:i:s');
        $data['end_at']   = $this->parseLocalDateTime($data['end_at'])->format('Y-m-d H:i:s');

        $appointment->update($data);

        return response()->json(['success' => true]);
    }

    public function destroy(Appointment $appointment)
    {
        $appointment->delete();
        return response()->json(['success' => true]);
    }

    /**
     * PATCH /appointments/{id}/move
     * Payload from calendar: start_at/end_at as "YYYY-MM-DDTHH:mm:ss"
     */
    public function move(Request $request, Appointment $appointment)
    {
        $request->validate([
            'start_at' => ['required', 'string'],
            'end_at'   => ['required', 'string'],
            'staff_id' => ['nullable', 'integer'],
        ]);

        $start = $this->parseLocalDateTime($request->input('start_at'));
        $end   = $this->parseLocalDateTime($request->input('end_at'));

        $appointment->start_at = $start->format('Y-m-d H:i:s');
        $appointment->end_at   = $end->format('Y-m-d H:i:s');

        if ($request->filled('staff_id')) {
            $appointment->staff_id = (int)$request->input('staff_id');
        }

        $appointment->save();

        return response()->json(['success' => true]);
    }

    public function list(Request $request)
    {
        $flag = $request->get('flag', 'today');

        $q = Appointment::query()
            ->with([
                'client',
                'staff.user',
                'service',
            ])
            ->orderBy('start_at', 'desc');

        if ($flag === 'today') {
            $q->whereDate('start_at', now()->toDateString());
        }

        $rows = $q->get()->map(function ($a) {
            $clientName = '';

            if ($a->client) {
                $clientName = trim(($a->client->first_name ?? '') . ' ' . ($a->client->last_name ?? ''));
            } else {
                // fallback to appointment fields (if you store ad-hoc client info)
                $clientName = (string)($a->client_name ?? '');
            }

            return [
                'id' => $a->id,
                'date' => optional($a->start_at)->format('Y-m-d'),
                'time' => optional($a->start_at)->format('H:i') . ' - ' . optional($a->end_at)->format('H:i'),
                'client_name' => $clientName !== '' ? $clientName : '—',
                'staff_name' => $a->staff?->user?->name ?? '—',
                'service_name' => $a->service?->name ?? '—',
                'status' => (string)($a->status ?? ''),
                'notes' => (string)($a->notes ?? ''),
            ];
        })->values();

        // DataTables expects { data: [...] }
        return response()->json(['data' => $rows]);
    }

    private function validateAppointment(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'staff_id' => ['required', 'integer', 'exists:staff,id'],
            'start_at' => ['required', 'string'],
            'end_at'   => ['required', 'string'],

            'client_id' => ['nullable', 'integer', 'exists:clients,id'],
            'client_name' => ['nullable', 'string', 'max:200'],
            'client_phone' => ['nullable', 'string', 'max:20'],

            'service_id' => ['required', 'integer', 'exists:services,id'],
            'status' => ['required', 'string'],
            'send_sms' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string'],
            'internal_notes' => ['nullable', 'string'],
        ]);
    }

    /**
     * Accepts:
     * - "YYYY-MM-DDTHH:mm" (datetime-local)
     * - "YYYY-MM-DDTHH:mm:ss" (calendar drag)
     */
    private function parseLocalDateTime(string $value): Carbon
    {
        $tz = config('app.timezone');

        $value = trim($value);

        // normalize: if seconds missing, add :00
        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $value)) {
            return Carbon::createFromFormat('Y-m-d\TH:i', $value, $tz);
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}$/', $value)) {
            return Carbon::createFromFormat('Y-m-d\TH:i:s', $value, $tz);
        }

        // fallback (still treat as local)
        return Carbon::parse($value, $tz);
    }

    /**
     * Return calendar times WITHOUT timezone suffix (so FullCalendar treats as local)
     */
    private function formatForCalendar($dt, string $tz): string
    {
        if (!$dt) return '';

        try {
            return Carbon::parse($dt, $tz)->format('Y-m-d\TH:i:s');
        } catch (\Throwable $e) {
            return '';
        }
    }
}
