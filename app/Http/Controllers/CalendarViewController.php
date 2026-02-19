<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class CalendarViewController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $canView = $user && (
            $user->role === 'admin'
            || $user->hasPermission('calendar_view.view')
            || $user->hasPermission('appointment.manage')
        );
        abort_unless($canView, 403);

        $canManage = $user && (
            $user->role === 'admin'
            || $user->hasPermission('appointment.manage')
        );

        $today = now()->format('Y-m-d');

        $rows = $this->fetchAppointmentsForDate($today);

        return view('calendar_view.index', [
            'today' => $today,
            'rows' => $rows,
            'canManage' => $canManage,
        ]);
    }

    /**
     * GET /calendar-view/today-rows?date=YYYY-MM-DD
     * Returns only the <tr> list rows as HTML partial
     */
    public function todayRows(Request $request)
    {
        $user = $request->user();

        $canView = $user && (
            $user->role === 'admin'
            || $user->hasPermission('calendar_view.view')
            || $user->hasPermission('appointment.manage')
        );
        abort_unless($canView, 403);

        $canManage = $user && (
            $user->role === 'admin'
            || $user->hasPermission('appointment.manage')
        );

        $date = (string) $request->query('date', now()->format('Y-m-d'));

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            abort(422, 'Invalid date format. Expected YYYY-MM-DD');
        }

        $rows = $this->fetchAppointmentsForDate($date);

        return view('calendar_view._today_rows', [
            'rows' => $rows,
            'canManage' => $canManage,
        ]);
    }

    private function fetchAppointmentsForDate(string $date)
    {
        // Use app timezone consistently
        $tz = config('app.timezone');

        // WhereDate uses DB date; we make sure $date is in the same expected local date format
        $localDate = Carbon::createFromFormat('Y-m-d', $date, $tz)->toDateString();

        $appointments = Appointment::query()
            ->with([
                'client:id,first_name,last_name,email',
                'staff.user:id,name',
                'service:id,name',
            ])
            ->whereDate('start_at', $localDate)
            ->orderBy('start_at', 'asc')
            ->get();

        return $appointments->map(function ($a) {
            $clientName = $a->client
                ? trim(($a->client->first_name ?? '') . ' ' . ($a->client->last_name ?? ''))
                : ($a->client_name ?? '');

            $staffName = $a->staff?->user?->name ?? '';
            $serviceName = $a->service?->name ?? '';

            return [
                'id' => (int) $a->id,
                'start_at' => $a->start_at ? Carbon::parse($a->start_at)->format('H:i') : '',
                'end_at' => $a->end_at ? Carbon::parse($a->end_at)->format('H:i') : '',
                'client_name' => $clientName ?: '—',
                'staff_name' => $staffName ?: '—',
                'service_name' => $serviceName ?: '—',
                'notes' => (string) ($a->notes ?? ''),
            ];
        })->values();
    }
}
