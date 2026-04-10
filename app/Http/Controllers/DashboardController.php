<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Service;
use Illuminate\Support\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $user    = $request->user();
        $roleKey = (string) ($user->role ?? 'user');

        $canManageAppointments = $user && (
            in_array($user->role, ['admin', 'owner'], true) || $user->hasPermission('appointment.manage')
        );
        $canCalendarView = $user && (
            in_array($user->role, ['admin', 'owner'], true)
            || $user->hasPermission('calendar_view.view')
            || $user->hasPermission('appointment.manage')
        );
        $canPos       = $user && (in_array($user->role, ['admin', 'owner'], true) || $user->hasPermission('cashier.manage'));
        $canClients   = $user && (in_array($user->role, ['admin', 'owner'], true) || $user->hasPermission('client.manage'));
        $canReports   = $user && (in_array($user->role, ['admin', 'owner'], true) || $user->hasPermission('reports.view'));
        $canAdminArea = $user && (in_array($user->role, ['admin', 'owner'], true) || $user->hasPermission('admin.access'));

        if ($roleKey === 'user' && $canCalendarView && !$canClients && !$canPos && !$canReports && !$canAdminArea) {
            return redirect()->route('calendar_view.index');
        }

        $today      = Carbon::today();
        $monthStart = $today->copy()->startOfMonth();
        $monthEnd   = $today->copy()->endOfMonth();
        $lastStart  = $today->copy()->subMonth()->startOfMonth();
        $lastEnd    = $today->copy()->subMonth()->endOfMonth();

        // ── Today stats ──────────────────────────────────────────
        $todayAppointmentsCount = Appointment::query()
            ->whereDate('start_at', $today->toDateString())
            ->count();

        $todaySales = (float) Sale::notVoided()->forDate($today)->sum('grand_total');

        // Upcoming appointments today (scheduled/confirmed, start_at >= now)
        $upcomingToday = Appointment::query()
            ->whereDate('start_at', $today->toDateString())
            ->whereIn('status', ['scheduled', 'confirmed'])
            ->where('start_at', '>=', now())
            ->count();

        // ── Month stats ───────────────────────────────────────────
        $thisMonthRevenue = (float) Sale::notVoided()
            ->whereBetween('created_at', [$monthStart, $monthEnd])
            ->sum('grand_total');

        $lastMonthRevenue = (float) Sale::notVoided()
            ->whereBetween('created_at', [$lastStart, $lastEnd])
            ->sum('grand_total');

        $revenueChange = $lastMonthRevenue > 0
            ? round((($thisMonthRevenue - $lastMonthRevenue) / $lastMonthRevenue) * 100, 1)
            : null;

        $newClientsThisMonth = Client::query()
            ->whereBetween('created_at', [$monthStart, $monthEnd])
            ->count();

        // ── Top services this month (by sale line count) ──────────
        $topServices = DB::table('sale_services as ss')
            ->join('services as s', 's.id', '=', 'ss.service_id')
            ->join('sales', 'sales.id', '=', 'ss.sale_id')
            ->whereNull('sales.voided_at')
            ->whereBetween('sales.created_at', [$monthStart, $monthEnd])
            ->select('s.name', DB::raw('COUNT(*) as bookings'), DB::raw('SUM(ss.line_total) as revenue'))
            ->groupBy('s.id', 's.name')
            ->orderByDesc('bookings')
            ->limit(5)
            ->get();

        // ── Top staff this month (by appointment count) ───────────
        $topStaff = DB::table('appointments as a')
            ->join('staff as st', 'st.id', '=', 'a.staff_id')
            ->leftJoin('users as u', 'u.id', '=', 'st.user_id')
            ->whereBetween('a.start_at', [$monthStart, $monthEnd])
            ->whereIn('a.status', ['completed', 'confirmed', 'scheduled'])
            ->select(
                DB::raw('COALESCE(u.name, CONCAT("Staff #", st.id)) as name'),
                DB::raw('COUNT(*) as appointments'),
                DB::raw('SUM(CASE WHEN a.status = "completed" THEN 1 ELSE 0 END) as completed')
            )
            ->groupBy('st.id', 'u.name')
            ->orderByDesc('appointments')
            ->limit(5)
            ->get();

        $stats = [
            'todayAppointments'   => $todayAppointmentsCount,
            'totalClients'        => Client::query()->count(),
            'totalServices'       => Service::query()->count(),
            'totalProducts'       => Product::query()->count(),
            'todaySales'          => round($todaySales, 2),
            'upcomingToday'       => $upcomingToday,
            'thisMonthRevenue'    => round($thisMonthRevenue, 2),
            'lastMonthRevenue'    => round($lastMonthRevenue, 2),
            'revenueChange'       => $revenueChange,
            'newClientsThisMonth' => $newClientsThisMonth,
        ];

        // ── Today's appointments list ─────────────────────────────
        $localDate    = $today->toDateString();
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
                'id'           => (int) $a->id,
                'start_at'     => $a->start_at ? Carbon::parse($a->start_at)->format('H:i') : '',
                'end_at'       => $a->end_at ? Carbon::parse($a->end_at)->format('H:i') : '',
                'client_name'  => $clientName ?: '—',
                'staff_name'   => $a->staff?->user?->name ?? '—',
                'service_name' => $a->service?->name ?? '—',
                'notes'        => (string) ($a->notes ?? ''),
            ];
        })->values();

        $roleExperience = match ($roleKey) {
            'owner' => [
                'label'   => 'Owner workspace',
                'summary' => 'Full business overview with operations, reports, communications, and settings access.',
            ],
            'admin' => [
                'label'   => 'Admin workspace',
                'summary' => 'Full operational and administrative control across the CRM.',
            ],
            'reception' => [
                'label'   => 'Reception workspace',
                'summary' => 'Front-desk workflow focused on calendar, appointments, clients, cashier, and limited reports.',
            ],
            default => [
                'label'   => 'Staff workspace',
                'summary' => 'Operational access only. Staff users are routed toward the calendar when broader dashboard access is not needed.',
            ],
        };

        return view('dashboard', [
            'stats'          => $stats,
            'today'          => $localDate,
            'rows'           => $rows,
            'topServices'    => $topServices,
            'topStaff'       => $topStaff,
            'canManage'      => $canManageAppointments,
            'canPos'         => $canPos,
            'canClients'     => $canClients,
            'canReports'     => $canReports,
            'roleExperience' => $roleExperience,
            'roleKey'        => $roleKey,
        ]);
    }

    /**
     * AJAX endpoint: last 30 days daily revenue for the chart.
     */
    public function charts()
    {
        $days = 30;
        $rows = Sale::notVoided()
            ->where('created_at', '>=', Carbon::today()->subDays($days - 1)->startOfDay())
            ->select(
                DB::raw('DATE(created_at) as day'),
                DB::raw('SUM(grand_total) as revenue'),
                DB::raw('COUNT(*) as sales_count')
            )
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->keyBy('day');

        $labels  = [];
        $revenue = [];
        $counts  = [];

        for ($i = $days - 1; $i >= 0; $i--) {
            $date      = Carbon::today()->subDays($i)->toDateString();
            $labels[]  = Carbon::parse($date)->format('d M');
            $revenue[] = round((float) ($rows[$date]->revenue ?? 0), 2);
            $counts[]  = (int) ($rows[$date]->sales_count ?? 0);
        }

        return response()->json([
            'labels'  => $labels,
            'revenue' => $revenue,
            'counts'  => $counts,
        ]);
    }
}
