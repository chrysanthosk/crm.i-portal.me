<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\Product;
use App\Models\Service;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();
        
        $canManageAppointments = $user && (
            $user->role === 'admin' || $user->hasPermission('appointment.manage')
        );

        $today = Carbon::today();
        $start = $today->copy()->startOfDay();
        $end = $today->copy()->endOfDay();

        $todayAppointmentsCount = Appointment::query()
            ->whereBetween('start_at', [$start, $end])
            ->count();

        $todaySales = (float) DB::table('sales')
            ->whereBetween(\Illuminate\Support\Facades\Schema::hasColumn('sales', 'sale_date') ? 'sale_date' : 'created_at', [$start, $end])
            ->sum('grand_total');

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

        return view('dashboard', [
            'stats' => $stats,
            'today' => $localDate,
            'rows' => $rows,
            'canManage' => $canManageAppointments,
            'canPos' => $user && ($user->role === 'admin' || $user->hasPermission('cashier.manage')),
            'canClients' => $user && ($user->role === 'admin' || $user->hasPermission('client.manage')),
        ]);
    }
}
