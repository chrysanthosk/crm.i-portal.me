<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\Product;
use App\Models\Service;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __invoke()
    {
        $today = Carbon::today();
        $start = $today->copy()->startOfDay();
        $end = $today->copy()->endOfDay();

        $appointments = Appointment::query()
            ->with(['client', 'staff.user', 'service'])
            ->whereBetween('start_at', [$start, $end])
            ->orderBy('start_at')
            ->limit(20)
            ->get();

        $todayAppointments = Appointment::query()
            ->whereBetween('start_at', [$start, $end])
            ->count();

        $todaySales = (float) DB::table('sales')
            ->whereBetween(DB::raw('COALESCE(sale_date, created_at)'), [$start, $end])
            ->sum('grand_total');

        $stats = [
            'todayAppointments' => $todayAppointments,
            'totalClients' => Client::query()->count(),
            'totalServices' => Service::query()->count(),
            'totalProducts' => Product::query()->count(),
            'todaySales' => round($todaySales, 2),
        ];

        return view('dashboard', [
            'appointments' => $appointments,
            'stats' => $stats,
            'today' => $today,
        ]);
    }
}
