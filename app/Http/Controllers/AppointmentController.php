<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\Service;
use App\Models\Staff;
use App\Models\ServiceCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AppointmentController extends Controller
{
    public function index()
    {
        return view('appointments.index');
    }

    public function resources()
    {
        $staff = Staff::query()
            ->with('user:id,name')
            ->orderBy('id')
            ->get();

        $resources = $staff->map(function ($s) {
            $title = $s->user?->name ?? ('Staff #' . $s->id);
            return [
                'id'    => (string) $s->id,
                'title' => $title,
            ];
        })->values();

        return response()->json($resources);
    }

    public function events(Request $request)
    {
        $tz = config('app.timezone');

        $appointments = Appointment::query()
            ->with([
                'staff.user:id,name',
                'service:id,name',
                'client:id,first_name,last_name,email',
            ])
            ->orderBy('start_at')
            ->get();

        $events = $appointments->map(function ($a) use ($tz) {
            $start = $this->formatForCalendar($a->start_at, $tz);
            $end   = $this->formatForCalendar($a->end_at, $tz);

            $staffName = $a->staff?->user?->name ?? ('Staff #' . $a->staff_id);
            $serviceName = $a->service?->name ?? 'Service';

            $clientLabel = $a->client_id
                ? (trim(($a->client?->first_name ?? '') . ' ' . ($a->client?->last_name ?? '')) ?: ($a->client?->email ?? 'Client'))
                : 'Client';

            return [
                'id' => (string) $a->id,
                'resourceId' => (string) $a->staff_id,
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

    /**
     * JSON endpoint used by dependent dropdown (category -> services)
     * GET /appointments/services?category_id=1
     */
    public function servicesByCategory(Request $request)
    {
        $request->validate([
            'category_id' => ['nullable', 'integer'],
        ]);

        $categoryId = $request->input('category_id');

        $q = Service::query()
            ->select('id', 'name', 'category_id')
            ->orderBy('name');

        if (!empty($categoryId)) {
            $q->where('category_id', (int) $categoryId);
        }

        return response()->json([
            'data' => $q->get()->map(fn ($s) => [
                'id' => (int) $s->id,
                'name' => (string) $s->name,
                'category_id' => (int) ($s->category_id ?? 0),
            ])->values()
        ]);
    }

    public function create(Request $request)
    {
        $staff = Staff::query()->with('user:id,name')->orderBy('id')->get();
        $clients = Client::query()->orderBy('first_name')->orderBy('last_name')->get();

        $serviceCategories = ServiceCategory::query()->orderBy('name')->get();
        $services = Service::query()->orderBy('name')->get(); // fallback list (JS filters it)

        if ($request->boolean('modal')) {
            return view('appointments._form', [
                'mode' => 'create',
                'appointment' => new Appointment(),
                'staff' => $staff,
                'clients' => $clients,
                'serviceCategories' => $serviceCategories,
                'services' => $services,
            ]);
        }

        return view('appointments.create', [
            'staff' => $staff,
            'clients' => $clients,
            'serviceCategories' => $serviceCategories,
            'services' => $services,
        ]);
    }

    public function edit(Request $request, Appointment $appointment)
    {
        $staff = Staff::query()->with('user:id,name')->orderBy('id')->get();
        $clients = Client::query()->orderBy('first_name')->orderBy('last_name')->get();

        $serviceCategories = ServiceCategory::query()->orderBy('name')->get();
        $services = Service::query()->orderBy('name')->get();

        if ($request->boolean('modal')) {
            return view('appointments._form', [
                'mode' => 'edit',
                'appointment' => $appointment,
                'staff' => $staff,
                'clients' => $clients,
                'serviceCategories' => $serviceCategories,
                'services' => $services,
            ]);
        }

        return view('appointments.edit', [
            'appointment' => $appointment,
            'staff' => $staff,
            'clients' => $clients,
            'serviceCategories' => $serviceCategories,
            'services' => $services,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateAppointment($request);

        // If no existing client selected => create a new client
        if (empty($data['client_id'])) {
            $data['client_id'] = $this->createClientFromAppointment($data);
        }

        // Parse local datetime-local inputs as app timezone and store as plain datetime
        $data['start_at'] = $this->parseLocalDateTime($data['start_at'])->format('Y-m-d H:i:s');
        $data['end_at']   = $this->parseLocalDateTime($data['end_at'])->format('Y-m-d H:i:s');

        // Keep appointments table clean: do not store the temp client fields
        unset($data['client_first_name'], $data['client_last_name'], $data['client_phone'], $data['service_category_id']);

        $appointment = Appointment::create($data);

        return response()->json(['success' => true, 'id' => $appointment->id]);
    }

    public function update(Request $request, Appointment $appointment)
    {
        $data = $this->validateAppointment($request, $appointment->id);

        // On edit: if user cleared client_id and provided new client fields => create new client
        if (empty($data['client_id'])) {
            $data['client_id'] = $this->createClientFromAppointment($data);
        }

        $data['start_at'] = $this->parseLocalDateTime($data['start_at'])->format('Y-m-d H:i:s');
        $data['end_at']   = $this->parseLocalDateTime($data['end_at'])->format('Y-m-d H:i:s');

        unset($data['client_first_name'], $data['client_last_name'], $data['client_phone'], $data['service_category_id']);

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
            $appointment->staff_id = (int) $request->input('staff_id');
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
            }

            return [
                'id' => $a->id,
                'date' => optional($a->start_at)->format('Y-m-d'),
                'time' => optional($a->start_at)->format('H:i') . ' - ' . optional($a->end_at)->format('H:i'),
                'client_name' => $clientName !== '' ? $clientName : '—',
                'staff_name' => $a->staff?->user?->name ?? '—',
                'service_name' => $a->service?->name ?? '—',
                'status' => (string) ($a->status ?? ''),
                'notes' => (string) ($a->notes ?? ''),
            ];
        })->values();

        return response()->json(['data' => $rows]);
    }

    /**
     * CSV Export
     * GET /appointments/export?flag=today|all
     */
    public function export(Request $request): StreamedResponse
    {
        $flag = $request->get('flag', 'today');

        $q = Appointment::query()
            ->with(['client:id,first_name,last_name,email', 'staff.user:id,name', 'service:id,name'])
            ->orderBy('start_at', 'asc');

        if ($flag === 'today') {
            $q->whereDate('start_at', now()->toDateString());
        }

        $rows = $q->get();

        $dateTag = ($flag === 'today') ? now()->format('Y-m-d') : 'all';
        $filename = "appointments_{$dateTag}.csv";

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ];

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');

            // UTF-8 BOM for Excel compatibility
            fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));

            // Header
            fputcsv($out, [
                'ID',
                'Start',
                'End',
                'Client',
                'Client Email',
                'Staff',
                'Service',
                'Status',
                'Notes',
            ]);

            foreach ($rows as $a) {
                $clientName = $a->client
                    ? trim(($a->client->first_name ?? '').' '.($a->client->last_name ?? ''))
                    : '';

                $clientEmail = $a->client?->email ?? '';
                $staffName = $a->staff?->user?->name ?? '';
                $serviceName = $a->service?->name ?? '';

                // keep them as stored (local app timezone style)
                $start = $a->start_at ? Carbon::parse($a->start_at)->format('Y-m-d H:i') : '';
                $end   = $a->end_at ? Carbon::parse($a->end_at)->format('Y-m-d H:i') : '';

                fputcsv($out, [
                    $a->id,
                    $start,
                    $end,
                    $clientName,
                    $clientEmail,
                    $staffName,
                    $serviceName,
                    (string) ($a->status ?? ''),
                    (string) ($a->notes ?? ''),
                ]);
            }

            fclose($out);
        }, $filename, $headers);
    }

    private function validateAppointment(Request $request, ?int $ignoreId = null): array
    {
        // If no client_id => require first/last name for new client
        return $request->validate([
            'staff_id' => ['required', 'integer', 'exists:staff,id'],
            'start_at' => ['required', 'string'],
            'end_at'   => ['required', 'string'],

            'client_id' => ['nullable', 'integer', 'exists:clients,id'],

            'client_first_name' => ['nullable', 'string', 'max:100', 'required_without:client_id'],
            'client_last_name'  => ['nullable', 'string', 'max:100', 'required_without:client_id'],
            'client_phone'      => ['nullable', 'string', 'max:20'],

            // UI requires category first
            'service_category_id' => ['required', 'integer', 'exists:service_categories,id'],

            // service_id must exist; we also enforce it belongs to selected category in after-hook
            'service_id' => ['required', 'integer', 'exists:services,id'],

            'status' => ['required', 'string'],
            'send_sms' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string'],
            'internal_notes' => ['nullable', 'string'],
        ], [
            'client_first_name.required_without' => 'First name is required when no existing client is selected.',
            'client_last_name.required_without'  => 'Last name is required when no existing client is selected.',
        ])->tap(function ($validator) use ($request) {
            // Ensure service belongs to category
            $catId = (int) $request->input('service_category_id');
            $svcId = (int) $request->input('service_id');

            if ($catId && $svcId) {
                $ok = Service::query()
                    ->where('id', $svcId)
                    ->where('category_id', $catId)
                    ->exists();

                if (!$ok) {
                    $validator->errors()->add('service_id', 'Selected service does not belong to the chosen category.');
                }
            }
        });
    }

    private function createClientFromAppointment(array $data): int
    {
        $first = trim((string)($data['client_first_name'] ?? ''));
        $last  = trim((string)($data['client_last_name'] ?? ''));
        $phone = trim((string)($data['client_phone'] ?? ''));

        // Safety guard: validation should catch, but keep it safe
        if ($first === '' || $last === '') {
            // fallback: avoid creating garbage clients
            throw new \RuntimeException('Cannot create client: first/last name missing.');
        }

        $client = Client::create([
            'registration_date' => now(),
            'first_name' => $first,
            'last_name' => $last,
            'mobile' => $phone !== '' ? $phone : null,
        ]);

        return (int) $client->id;
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

        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $value)) {
            return Carbon::createFromFormat('Y-m-d\TH:i', $value, $tz);
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}$/', $value)) {
            return Carbon::createFromFormat('Y-m-d\TH:i:s', $value, $tz);
        }

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
