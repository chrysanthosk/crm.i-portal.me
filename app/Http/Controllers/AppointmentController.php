<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\Service;
use App\Models\Staff;
use App\Models\ServiceCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
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
                : ($a->client_name ?: 'Client');

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
        $services = Service::query()->orderBy('name')->get();

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

        if (empty($data['client_id'])) {
            $data['client_id'] = $this->createClientFromAppointment($data);
        }

        $start = $this->parseLocalDateTime($data['start_at']);
        $end   = $this->parseLocalDateTime($data['end_at']);

        $data['start_at'] = $start->format('Y-m-d H:i:s');
        $data['end_at']   = $end->format('Y-m-d H:i:s');

        $this->applyReminderAndResetSmsState($data, $start);

        unset($data['client_first_name'], $data['client_last_name'], $data['client_phone'], $data['service_category_id']);

        $appointment = Appointment::create($data);

        return response()->json(['success' => true, 'id' => $appointment->id]);
    }

    public function update(Request $request, Appointment $appointment)
    {
        $data = $this->validateAppointment($request, $appointment->id);

        if (empty($data['client_id'])) {
            $data['client_id'] = $this->createClientFromAppointment($data);
        }

        $start = $this->parseLocalDateTime($data['start_at']);
        $end   = $this->parseLocalDateTime($data['end_at']);

        $data['start_at'] = $start->format('Y-m-d H:i:s');
        $data['end_at']   = $end->format('Y-m-d H:i:s');

        $this->applyReminderAndResetSmsState($data, $start);

        unset($data['client_first_name'], $data['client_last_name'], $data['client_phone'], $data['service_category_id']);

        $appointment->update($data);

        return response()->json(['success' => true]);
    }

    public function destroy(Appointment $appointment)
    {
        $appointment->delete();
        return response()->json(['success' => true]);
    }

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

        // Recompute reminder_at and reset SMS state if SMS reminders are applicable
        $payload = [
            'send_sms' => (bool)$appointment->send_sms,
            'status' => (string)$appointment->status,
        ];
        $this->applyReminderAndResetSmsState($payload, $start);
        $appointment->reminder_at = $payload['reminder_at'] ?? null;

        // Reset SMS fields so the reminder can re-send for the new time
        $appointment->sms_attempts = 0;
        $appointment->sms_sent_success = false;
        $appointment->sms_send_failed = false;
        $appointment->sms_sent_at = null;
        $appointment->sms_failed_at = null;
        $appointment->sms_provider = null;
        $appointment->sms_provider_message_id = null;
        $appointment->sms_last_error = null;

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
            $clientName = $a->client ? trim(($a->client->first_name ?? '') . ' ' . ($a->client->last_name ?? '')) : '';
            if ($clientName === '') $clientName = $a->client_name ?: '';

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
            fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($out, [
                'ID','Start','End','Client','Client Email','Staff','Service','Status','Notes',
            ]);

            foreach ($rows as $a) {
                $clientName = $a->client
                    ? trim(($a->client->first_name ?? '').' '.($a->client->last_name ?? ''))
                    : ($a->client_name ?? '');

                $clientEmail = $a->client?->email ?? '';
                $staffName = $a->staff?->user?->name ?? '';
                $serviceName = $a->service?->name ?? '';

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

    /**
     * FIXED: no ->tap() on array.
     * Laravel $request->validate() returns an array; to add after-hook validation
     * we must use Validator::make().
     */
    private function validateAppointment(Request $request, ?int $ignoreId = null): array
    {
        $rules = [
            'staff_id' => ['required', 'integer', 'exists:staff,id'],
            'start_at' => ['required', 'string'],
            'end_at'   => ['required', 'string'],

            'client_id' => ['nullable', 'integer', 'exists:clients,id'],

            'client_first_name' => ['nullable', 'string', 'max:100', 'required_without:client_id'],
            'client_last_name'  => ['nullable', 'string', 'max:100', 'required_without:client_id'],
            'client_phone'      => ['nullable', 'string', 'max:20'],

            'service_category_id' => ['required', 'integer', 'exists:service_categories,id'],
            'service_id' => ['required', 'integer', 'exists:services,id'],

            'status' => ['required', 'string'],
            'send_sms' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string'],
            'internal_notes' => ['nullable', 'string'],
        ];

        $messages = [
            'client_first_name.required_without' => 'First name is required when no existing client is selected.',
            'client_last_name.required_without'  => 'Last name is required when no existing client is selected.',
        ];

        $validator = Validator::make($request->all(), $rules, $messages);

        // Ensure selected service belongs to selected category
        $validator->after(function ($v) use ($request) {
            $catId = (int) $request->input('service_category_id');
            $svcId = (int) $request->input('service_id');

            if ($catId && $svcId) {
                $ok = Service::query()
                    ->where('id', $svcId)
                    ->where('category_id', $catId)
                    ->exists();

                if (!$ok) {
                    $v->errors()->add('service_id', 'Selected service does not belong to the chosen category.');
                }
            }
        });

        return $validator->validate();
    }

    private function createClientFromAppointment(array $data): int
    {
        $first = trim((string)($data['client_first_name'] ?? ''));
        $last  = trim((string)($data['client_last_name'] ?? ''));
        $phone = trim((string)($data['client_phone'] ?? ''));

        if ($first === '' || $last === '') {
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

    private function formatForCalendar($dt, string $tz): string
    {
        if (!$dt) return '';

        try {
            return Carbon::parse($dt, $tz)->format('Y-m-d\TH:i:s');
        } catch (\Throwable $e) {
            return '';
        }
    }

    /**
     * Applies reminder_at = start_at - 24h for confirmed+send_sms.
     * Also resets SMS state fields so scheduler can send again correctly.
     */
    private function applyReminderAndResetSmsState(array &$data, Carbon $start): void
    {
        $sendSms = isset($data['send_sms']) ? (bool)$data['send_sms'] : false;
        $status  = strtolower(trim((string)($data['status'] ?? '')));

        if ($sendSms && $status === 'confirmed') {
            $data['reminder_at'] = $start->copy()->subHours(24)->format('Y-m-d H:i:s');
        } else {
            $data['reminder_at'] = null;
        }

        // Reset SMS state whenever appointment is created/edited
        $data['sms_attempts'] = 0;
        $data['sms_sent_success'] = false;
        $data['sms_send_failed'] = false;
        $data['sms_sent_at'] = null;
        $data['sms_failed_at'] = null;
        $data['sms_provider'] = null;
        $data['sms_provider_message_id'] = null;
        $data['sms_last_error'] = null;
    }
}
