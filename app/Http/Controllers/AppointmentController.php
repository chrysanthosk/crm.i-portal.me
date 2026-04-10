<?php

namespace App\Http\Controllers;

use App\Http\Requests\AppointmentRequest;
use App\Jobs\SendAppointmentConfirmationEmailJob;
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
            ->with(['user:id,name', 'availabilities'])
            ->orderBy('id')
            ->get();

        // FullCalendar day-of-week uses 0=Sun,1=Mon…6=Sat
        // Our StaffAvailability uses 0=Mon…6=Sun — convert accordingly
        $fcDow = [
            0 => 1, // Mon
            1 => 2, // Tue
            2 => 3, // Wed
            3 => 4, // Thu
            4 => 5, // Fri
            5 => 6, // Sat
            6 => 0, // Sun
        ];

        $resources = $staff->map(function ($s) use ($fcDow) {
            $title = $s->user?->name ?? ('Staff #' . $s->id);

            $businessHours = [];
            foreach ($s->availabilities as $avail) {
                if ($avail->is_day_off) continue;

                $businessHours[] = [
                    'daysOfWeek'  => [$fcDow[$avail->day_of_week]],
                    'startTime'   => substr($avail->start_time, 0, 5), // HH:mm
                    'endTime'     => substr($avail->end_time, 0, 5),
                ];
            }

            return [
                'id'            => (string) $s->id,
                'title'         => $title,
                'businessHours' => $businessHours ?: false, // false = no restriction
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

    public function staffForService(Request $request)
    {
        $request->validate([
            'service_id' => ['nullable', 'integer'],
        ]);

        $serviceId = (int) $request->input('service_id');

        $q = Staff::query()->with('user:id,name')->orderBy('id');

        // If a service is specified, filter to staff who have that skill.
        // If no skills are configured for any staff, fall back to all staff.
        if ($serviceId > 0) {
            $skilled = Staff::query()
                ->whereHas('services', fn ($s) => $s->where('services.id', $serviceId))
                ->pluck('id');

            if ($skilled->isNotEmpty()) {
                $q->whereIn('id', $skilled);
            }
        }

        return response()->json([
            'data' => $q->get()->map(fn ($s) => [
                'id'   => (int) $s->id,
                'name' => $s->user?->name ?? ('Staff #' . $s->id),
            ])->values()
        ]);
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
        $clients = Client::query()->select('id', 'first_name', 'last_name', 'mobile', 'email')->orderBy('first_name')->orderBy('last_name')->get();

        $serviceCategories = ServiceCategory::query()->orderBy('name')->get();
        $services = Service::query()->orderBy('name')->get();

        $appointment = new Appointment();
        if ($request->has('client_id')) {
            $appointment->client_id = (int) $request->input('client_id');
        }

        if ($request->boolean('modal')) {
            return view('appointments._form', [
                'mode' => 'create',
                'appointment' => $appointment,
                'staff' => $staff,
                'clients' => $clients,
                'serviceCategories' => $serviceCategories,
                'services' => $services,
            ]);
        }

        return view('appointments.create', [
            'appointment' => $appointment,
            'staff' => $staff,
            'clients' => $clients,
            'serviceCategories' => $serviceCategories,
            'services' => $services,
        ]);
    }

    public function edit(Request $request, Appointment $appointment)
    {
        $staff = Staff::query()->with('user:id,name')->orderBy('id')->get();
        $clients = Client::query()->select('id', 'first_name', 'last_name', 'mobile', 'email')->orderBy('first_name')->orderBy('last_name')->get();

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

    public function store(AppointmentRequest $request)
    {
        $data = $request->validated();

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

        // Dispatch confirmation email if client has email and status is confirmed
        if (($data['status'] ?? '') === 'confirmed' && !empty($data['client_id'])) {
            SendAppointmentConfirmationEmailJob::dispatch($appointment->id);
        }

        if ($request->ajax() || $request->wantsJson() || $request->boolean('modal')) {
            return response()->json(['success' => true, 'id' => $appointment->id]);
        }

        return redirect()
            ->route('dashboard')
            ->with('success', 'Appointment created successfully.');
    }

    public function update(AppointmentRequest $request, Appointment $appointment)
    {
        $data = $request->validated();

        if (empty($data['client_id'])) {
            $data['client_id'] = $this->createClientFromAppointment($data);
        }

        $start = $this->parseLocalDateTime($data['start_at']);
        $end   = $this->parseLocalDateTime($data['end_at']);

        $data['start_at'] = $start->format('Y-m-d H:i:s');
        $data['end_at']   = $end->format('Y-m-d H:i:s');

        $this->applyReminderAndResetSmsState($data, $start);

        unset($data['client_first_name'], $data['client_last_name'], $data['client_phone'], $data['service_category_id']);

        $wasConfirmed = $appointment->status === 'confirmed';
        $appointment->update($data);

        // Dispatch confirmation email if status just became confirmed and not already sent
        if (
            ($data['status'] ?? '') === 'confirmed' &&
            !$wasConfirmed &&
            !$appointment->email_confirmation_sent_at &&
            !empty($appointment->client_id)
        ) {
            SendAppointmentConfirmationEmailJob::dispatch($appointment->id);
        }

        if ($request->ajax() || $request->wantsJson() || $request->boolean('modal')) {
            return response()->json(['success' => true]);
        }

        return redirect()
            ->route('appointments.index')
            ->with('success', 'Appointment updated successfully.');
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
            'mobile' => $phone !== '' ? $phone : '',
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
