<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Service;
use Illuminate\Support\Carbon;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();
        $roleKey = (string) ($user->role ?? 'user');

        $canManageAppointments = $user && (
            in_array($user->role, ['admin', 'owner'], true) || $user->hasPermission('appointment.manage')
        );

        $canCalendarView = $user && (
            in_array($user->role, ['admin', 'owner'], true)
            || $user->hasPermission('calendar_view.view')
            || $user->hasPermission('appointment.manage')
        );

        $canPos = $user && (in_array($user->role, ['admin', 'owner'], true) || $user->hasPermission('cashier.manage'));
        $canClients = $user && (in_array($user->role, ['admin', 'owner'], true) || $user->hasPermission('client.manage'));
        $canReports = $user && (in_array($user->role, ['admin', 'owner'], true) || $user->hasPermission('reports.view'));
        $canAdminArea = $user && (in_array($user->role, ['admin', 'owner'], true) || $user->hasPermission('admin.access'));

        if ($roleKey === 'user' && $canCalendarView && !$canClients && !$canPos && !$canReports && !$canAdminArea) {
            return redirect()->route('calendar_view.index');
        }

        $today = Carbon::today();
        $start = $today->copy()->startOfDay();
        $end = $today->copy()->endOfDay();

        $todayAppointmentsCount = Appointment::query()
            ->whereBetween('start_at', [$start, $end])
            ->count();

        $todaySales = (float) Sale::notVoided()->forDate($today)->sum('grand_total');

        $stats = [
            'todayAppointments' => $todayAppointmentsCount,
            'totalClients' => Client::query()->count(),
            'totalServices' => Service::query()->count(),
            'totalProducts' => Product::query()->count(),
            'todaySales' => round($todaySales, 2),
        ];

        // Fetch rows for the list using the same logic as CalendarViewController
        $localDate = $today->toDateString();
        $appointments = Appointment::query()
            ->with([
                'client:id,first_name,last_name,email',
                'staff.user:id,name',
                'service:id,name',
            ])
            ->whereDate('start_at', $localDate)
            ->orderBy('start_at', 'asc')
            ->get();

        $rows = $appointments->map(function ($a) {
            $clientName = $a->client
                ? trim(($a->client->first_name ?? '') . ' ' . ($a->client->last_name ?? ''))
                : ($a->client_name ?? '');

            return [
                'id' => (int) $a->id,
                'start_at' => $a->start_at ? Carbon::parse($a->start_at)->format('H:i') : '',
                'end_at' => $a->end_at ? Carbon::parse($a->end_at)->format('H:i') : '',
                'client_name' => $clientName ?: '—',
                'staff_name' => $a->staff?->user?->name ?? '—',
                'service_name' => $a->service?->name ?? '—',
                'notes' => (string) ($a->notes ?? ''),
            ];
        })->values();

        $roleExperience = match ($roleKey) {
            'owner' => [
                'label' => 'Owner workspace',
                'summary' => 'Full business overview with operations, reports, communications, and settings access.',
            ],
            'admin' => [
                'label' => 'Admin workspace',
                'summary' => 'Full operational and administrative control across the CRM.',
            ],
            'reception' => [
                'label' => 'Reception workspace',
                'summary' => 'Front-desk workflow focused on calendar, appointments, clients, cashier, and limited reports.',
            ],
            default => [
                'label' => 'Staff workspace',
                'summary' => 'Operational access only. Staff users are routed toward the calendar when broader dashboard access is not needed.',
            ],
        };

        return view('dashboard', [
            'stats' => $stats,
            'today' => $localDate,
            'rows' => $rows,
            'canManage' => $canManageAppointments,
            'canPos' => $canPos,
            'canClients' => $canClients,
            'canReports' => $canReports,
            'roleExperience' => $roleExperience,
            'roleKey' => $roleKey,
        ]);
    }
}
