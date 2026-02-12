<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\Service;
use App\Models\Staff;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AppointmentController extends Controller
{
    public function index()
    {
        return view('appointments.index');
    }

    public function create(Request $request)
    {
        $clients  = Client::query()->orderBy('first_name')->orderBy('last_name')->get();

        $staff = Staff::query()
            ->with(['user', 'role'])     // your Staff model has role()
            ->orderBy('position')
            ->orderBy('id')
            ->get();

        $services = Service::query()->orderBy('id')->get();

        if ($request->boolean('modal')) {
            return view('appointments._form', [
                'mode' => 'create',
                'appointment' => new Appointment(),
                'clients' => $clients,
                'staff' => $staff,
                'services' => $services,
            ]);
        }

        return view('appointments.create', compact('clients', 'staff', 'services'));
    }

    public function store(Request $request)
    {
        $data = $this->validateAppointment($request);

        $data['created_by'] = auth()->id();
        $data['updated_by'] = auth()->id();

        $appt = Appointment::create($data);

        return response()->json([
            'success' => true,
            'id' => $appt->id,
            'message' => 'Appointment created.',
        ]);
    }

    public function edit(Request $request, Appointment $appointment)
    {
        $appointment->load(['client', 'staff.user', 'service']);

        $clients = Client::query()->orderBy('first_name')->orderBy('last_name')->get();

        $staff = Staff::query()
            ->with(['user', 'role'])
            ->orderBy('position')
            ->orderBy('id')
            ->get();

        $services = Service::query()->orderBy('id')->get();

        if ($request->boolean('modal')) {
            return view('appointments._form', [
                'mode' => 'edit',
                'appointment' => $appointment,
                'clients' => $clients,
                'staff' => $staff,
                'services' => $services,
            ]);
        }

        return view('appointments.edit', compact('appointment', 'clients', 'staff', 'services'));
    }

    public function update(Request $request, Appointment $appointment)
    {
        $data = $this->validateAppointment($request, $appointment->id);
        $data['updated_by'] = auth()->id();

        $appointment->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Appointment updated.',
        ]);
    }

    public function destroy(Appointment $appointment)
    {
        $appointment->delete();

        return response()->json([
            'success' => true,
            'message' => 'Appointment deleted.',
        ]);
    }

    /**
     * FullCalendar "resources" endpoint (Scheduler)
     */
    public function resources()
    {
        $staff = Staff::query()
            ->with('user')
            // ->where('show_in_calendar', true)  // enable if you want to hide staff from calendar
            ->orderBy('position')
            ->orderBy('id')
            ->get();

        $resources = $staff->map(function ($s) {
            $title = $s->user ? $s->user->name : ('Staff #' . $s->id);
            return [
                'id' => (string)$s->id,
                'title' => $title,
                'extendedProps' => [
                    'color' => $s->color,
                ],
            ];
        });

        return response()->json($resources);
    }

    /**
     * FullCalendar "events" endpoint
     * Accepts: start, end (ISO)
     */
    public function events(Request $request)
    {
        $startQ = $request->query('start');
        $endQ   = $request->query('end');

        if (!$startQ || !$endQ) {
            return response()->json([]); // FullCalendar expects an array
        }

        try {
            $start = Carbon::parse($startQ);
            $end   = Carbon::parse($endQ);
        } catch (\Throwable $e) {
            return response()->json([]);
        }

        $rows = Appointment::query()
            ->with(['client', 'staff.user', 'service'])
            ->where('start_at', '<', $end)
            ->where('end_at', '>', $start)
            ->get();

        $events = $rows->map(function (Appointment $a) {
            $staffColor  = $a->staff?->color ?: '#0d6efd';
            $clientName  = $a->client_display_name;
            $serviceName = $a->service?->name ?? ('Service #' . $a->service_id);

            $title = $clientName . ' — ' . $serviceName;

            return [
                'id' => (string)$a->id,
                'resourceId' => (string)$a->staff_id,
                'start' => $a->start_at?->toIso8601String(),
                'end' => $a->end_at?->toIso8601String(),
                'title' => $title,
                'backgroundColor' => $staffColor,
                'borderColor' => $staffColor,
                'textColor' => '#ffffff',
                'extendedProps' => [
                    'status' => $a->status,
                    'client_id' => $a->client_id,
                    'client_name' => $a->client_name,
                    'client_phone' => $a->client_phone,
                    'service_id' => $a->service_id,
                    'notes' => $a->notes,
                    'send_sms' => (bool)$a->send_sms,
                ],
            ];
        });

        return response()->json($events);
    }

    /**
     * Drag/drop + resize handler from calendar
     * payload: start_at, end_at, staff_id
     */
    public function move(Request $request, Appointment $appointment)
    {
        $data = $request->validate([
            'start_at' => ['required', 'date'],
            'end_at'   => ['required', 'date', 'after:start_at'],
            'staff_id' => ['required', 'integer', 'exists:staff,id'],
        ]);

        $this->ensureNoOverlap(
            (int)$data['staff_id'],
            Carbon::parse($data['start_at']),
            Carbon::parse($data['end_at']),
            $appointment->id
        );

        $appointment->update([
            'start_at' => Carbon::parse($data['start_at']),
            'end_at'   => Carbon::parse($data['end_at']),
            'staff_id' => (int)$data['staff_id'],
            'updated_by' => auth()->id(),
        ]);

        return response()->json(['success' => true]);
    }

    /**
     * Table JSON endpoint (DataTables)
     * Query: flag=today|all
     */
    public function list(Request $request)
    {
        $flag = $request->query('flag', 'today');

        $q = Appointment::query()
            ->with(['client', 'staff.user', 'service'])
            ->orderByDesc('start_at');

        if ($flag === 'today') {
            $todayStart = now()->startOfDay();
            $todayEnd   = now()->endOfDay();
            $q->whereBetween('start_at', [$todayStart, $todayEnd]);
        }

        $rows = $q->limit(2000)->get();

        $data = $rows->map(function (Appointment $a) {
            $clientName  = $a->client_display_name;
            $staffName   = $a->staff?->user?->name ?: ('Staff #' . $a->staff_id);
            $serviceName = $a->service?->name ?: ('Service #' . $a->service_id);

            return [
                'id' => $a->id,
                'date' => $a->start_at?->format('Y-m-d'),
                'time' => ($a->start_at?->format('H:i') ?? '') . ' - ' . ($a->end_at?->format('H:i') ?? ''),
                'client_name' => $clientName,
                'staff_name' => $staffName,
                'service_name' => $serviceName,
                'status' => $a->status,
                'notes' => (string)($a->notes ?? ''),
            ];
        });

        return response()->json(['data' => $data]);
    }

    public function export(Request $request): StreamedResponse
    {
        $flag = $request->query('flag', 'today');

        $q = Appointment::query()->with(['client', 'staff.user', 'service'])->orderByDesc('start_at');

        if ($flag === 'today') {
            $todayStart = now()->startOfDay();
            $todayEnd   = now()->endOfDay();
            $q->whereBetween('start_at', [$todayStart, $todayEnd]);
        }

        $rows = $q->get();

        $filename = 'appointments_' . $flag . '_' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');

            fputcsv($out, [
                'ID',
                'Start',
                'End',
                'Staff',
                'Client',
                'Service',
                'Status',
                'Notes',
                'Send SMS',
            ]);

            foreach ($rows as $a) {
                $staffName   = $a->staff?->user?->name ?: ('Staff #' . $a->staff_id);
                $clientName  = $a->client_display_name;
                $serviceName = $a->service?->name ?: ('Service #' . $a->service_id);

                fputcsv($out, [
                    $a->id,
                    $a->start_at?->format('Y-m-d H:i'),
                    $a->end_at?->format('Y-m-d H:i'),
                    $staffName,
                    $clientName,
                    $serviceName,
                    $a->status,
                    (string)($a->notes ?? ''),
                    $a->send_sms ? '1' : '0',
                ]);
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function validateAppointment(Request $request, ?int $ignoreId = null): array
    {
        $data = $request->validate([
            'start_at' => ['required', 'date'],
            'end_at'   => ['required', 'date', 'after:start_at'],
            'staff_id' => ['required', 'integer', 'exists:staff,id'],

            'client_id' => ['nullable', 'integer', 'exists:clients,id'],
            'client_name' => ['nullable', 'string', 'max:200'],
            'client_phone' => ['nullable', 'string', 'max:20'],

            'service_id' => ['required', 'integer', 'exists:services,id'],

            'status' => ['required', Rule::in(['scheduled', 'confirmed', 'completed', 'cancelled', 'no_show'])],
            'notes' => ['nullable', 'string'],
            'internal_notes' => ['nullable', 'string'],

            'send_sms' => ['nullable', 'boolean'],
            'reminder_at' => ['nullable', 'date'],
        ]);

        $data['send_sms'] = (bool)$request->boolean('send_sms');

        $start = Carbon::parse($data['start_at']);
        $end   = Carbon::parse($data['end_at']);

        // Require either client_id OR (client_name + client_phone)
        $clientId = $data['client_id'] ?? null;
        $name = trim((string)($data['client_name'] ?? ''));
        $phone = trim((string)($data['client_phone'] ?? ''));

        if ($clientId) {
            $data['client_name'] = null;
            $data['client_phone'] = null;
        } else {
            if ($name === '' || $phone === '') {
                abort(422, 'Select an existing client or provide new client name & phone.');
            }
            $data['client_id'] = null;
        }

        // Overlap validation (no double booking per staff)
        $this->ensureNoOverlap((int)$data['staff_id'], $start, $end, $ignoreId);

        return $data;
    }

    private function ensureNoOverlap(int $staffId, Carbon $start, Carbon $end, ?int $ignoreId = null): void
    {
        $q = Appointment::query()
            ->where('staff_id', $staffId)
            ->where('start_at', '<', $end)
            ->where('end_at', '>', $start);

        if ($ignoreId) {
            $q->where('id', '!=', $ignoreId);
        }

        if ($q->exists()) {
            abort(422, 'This staff member already has an overlapping appointment.');
        }
    }
}
