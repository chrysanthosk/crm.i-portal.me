<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Barryvdh\DomPDF\Facade\Pdf;

class ReportsController extends Controller
{
    /**
     * Analytics Dashboard
     * Defaults to last 30 days.
     */
    public function analytics(Request $request)
    {
        $end   = $request->query('to', Carbon::now()->toDateString());
        $start = $request->query('from', Carbon::now()->subDays(29)->toDateString());

        $startDt = Carbon::parse($start)->startOfDay();
        $endDt   = Carbon::parse($end)->endOfDay();

        $salesBase = DB::table('sales')
            ->whereNull('voided_at')
            ->whereBetween('created_at', [$startDt, $endDt]);

        $totalRevenue = (float) (clone $salesBase)->sum('grand_total');

        $totalAppointments = (int) DB::table('appointments')
            ->whereBetween('start_at', [$startDt, $endDt])
            ->count();

        $byDay = (clone $salesBase)
            ->selectRaw($this->exprDate('created_at') . " AS day, COALESCE(SUM(grand_total),0) AS revenue")
            ->groupBy('day')
            ->orderBy('day')
            ->get();

        $days     = $byDay->pluck('day')->toArray();
        $revenues = $byDay->pluck('revenue')->map(fn ($v) => (float) $v)->toArray();

        // staff display logic (staff may not have name columns; use users via staff.user_id)
        [$staffSelectSql, $staffGroupBy, $needsUserJoin] = $this->staffDisplaySql('t', 'u');

        $topStaffQ = DB::table('sale_services as ss')
            ->join('staff as t', 'ss.staff_id', '=', 't.id')
            ->join('sales as s', 'ss.sale_id', '=', 's.id')
            ->whereNull('s.voided_at')
            ->whereBetween('s.created_at', [$startDt, $endDt]);

        if ($needsUserJoin) {
            $topStaffQ->leftJoin('users as u', 't.user_id', '=', 'u.id');
        }

        $topStaff = $topStaffQ
            ->selectRaw("
                t.id as staff_id,
                {$staffSelectSql},
                COALESCE(SUM(ss.line_total),0) as revenue
            ")
            ->groupBy(array_merge(['t.id'], $staffGroupBy))
            ->orderByDesc('revenue')
            ->limit(5)
            ->get();

        return view('reports.analytics', compact(
            'start',
            'end',
            'totalRevenue',
            'totalAppointments',
            'days',
            'revenues',
            'topStaff'
        ));
    }

    /**
     * BI Reports page
     */
    public function bi(Request $request)
    {
        return view('reports.bi');
    }

    /**
     * ✅ Staff Performance page
     * URL: /reports/staff-performance?from_date=YYYY-MM-DD&to_date=YYYY-MM-DD&date_basis=sale|appointment&staff_id=#
     */
    public function staffPerformance(Request $request)
    {
        $to   = (string)$request->query('to_date', Carbon::now()->toDateString());
        $from = (string)$request->query('from_date', Carbon::now()->subDays(29)->toDateString());
        $dateBasis = (string)$request->query('date_basis', 'sale');

        $data = $this->loadReportData('staff_performance', $request);

        return view('reports.staff_performance', [
            'from' => $from,
            'to' => $to,
            'dateBasis' => $dateBasis,
            'rows' => $data['rows'] ?? [],
        ]);
    }

    /**
     * ✅ JSON endpoint for BI reports
     * URL: /reports/data?report=yoy (etc)
     */
    public function data(Request $request)
    {
        $report = (string) $request->query('report', '');
        $report = trim($report);

        try {
            switch ($report) {
                case 'yoy':
                    return response()->json($this->biYoY());

                case 'ytd':
                    return response()->json($this->biYtd());

                case 'expense_cat':
                    return response()->json($this->biExpenseCategory());

                case 'top_vendors':
                    return response()->json($this->biTopVendors());

                case 'pl':
                    return response()->json($this->biPL());

                case 'drill':
                    $start = (string) $request->query('start', '');
                    $end   = (string) $request->query('end', '');
                    return response()->json($this->biDrill($start, $end));

                case 'yoy_table':
                    return response()->json($this->biYoYTable());

                // Support both names (some JS uses sales_cat)
                case 'sales_category':
                case 'sales_cat':
                    return response()->json($this->biSalesByCategory());

                case 'top_products':
                    return response()->json($this->biTopProducts());

                case 'customer_ltv':
                    return response()->json($this->biCustomerLtv());

                default:
                    return response()->json(['ok' => false, 'error' => 'Unknown report'], 400);
            }
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'error' => 'Report query failed',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Reports selector page
     */
    public function index(Request $request)
    {
        $report = (string) $request->query('report', '');
        $data   = $this->loadReportData($report, $request);

        // filters for dropdowns on reports.index
        $filters = [
            'staff'    => $this->filterStaffOptions(),
            'services' => $this->filterServicesOptions(),
            'products' => $this->filterProductsOptions(),
        ];

        return view('reports.index', [
            'selectedReport' => $report,
            'data' => $data,
            'filters' => $filters,
        ]);
    }

    /**
     * Universal PDF export:
     * /reports/pdf/{report}?from_date=...&to_date=...&...
     */
    public function pdf(string $report, Request $request)
    {
        $data = $this->loadReportData($report, $request);

        $titleMap = [
            'analytics'            => 'Analytics Dashboard',
            'bi'                   => 'BI Reports',
            'top_clients_appts'    => 'Top 10 Clients (Appointments)',
            'top_clients_payments' => 'Top 10 Clients (Payments)',
            'top_staff_appts'      => 'Top 10 Staff (Appointments)',
            'top_staff_payments'   => 'Top 10 Staff (Payments)',
            'first_appointments'   => 'First Appointments (New Clients)',
            'gender_distribution'  => 'Gender Distribution',

            // Added
            'sales_appointments'   => 'Sales (Appointments)',
            'sales_products'       => 'Sales (Products)',
            'cashier_all'          => 'Cashier (All)',
            'cashier_staff'        => 'Cashier (Staff)',
            'cashier_service'      => 'Cashier (Service)',
            'cashier_products'     => 'Cashier (Products)',

            'generated_zreports'   => 'Generated Z Reports',
            'z_reports'            => 'Z Reports',

            // ✅ NEW
            'staff_performance'    => 'Staff Performance',
        ];

        $title = $titleMap[$report] ?? ('Report: ' . $report);

        $pdf = Pdf::loadView('reports.pdf', [
            'title' => $title,
            'report' => $report,
            'filters' => $request->query(),
            'data' => $data,
        ])->setPaper('a4', 'portrait');

        $filename = 'report_' . $report . '_' . now()->format('Ymd_His') . '.pdf';
        return $pdf->download($filename);
    }

    /**
     * Z-Report print page (receipt style)
     * Route: /reports/zreport/{id}
     */
    public function zReportPrint(int $id)
    {
        $zr = DB::table('z_reports')->where('id', $id)->first();
        abort_if(!$zr, 404, "Z Report #{$id} not found.");

        $fromDt = Carbon::parse($zr->date_from)->startOfDay();
        $toDt   = Carbon::parse($zr->date_to)->endOfDay();

        $salesBase = DB::table('sales')
            ->whereNull('voided_at')
            ->whereBetween('created_at', [$fromDt, $toDt]);

        $totals = (object) [
            'transactions_count' => (int) (clone $salesBase)->count(),
            'total_transactions' => (float) (clone $salesBase)->sum('grand_total'),
        ];

        $subtotals = (object) [
            'services_net'  => (float) (clone $salesBase)->sum('services_subtotal'),
            'services_vat'  => (float) (clone $salesBase)->sum('services_vat'),
            'products_net'  => (float) (clone $salesBase)->sum('products_subtotal'),
            'products_vat'  => (float) (clone $salesBase)->sum('products_vat'),
        ];

        $servicesVatPct = $subtotals->services_net > 0 ? ($subtotals->services_vat / $subtotals->services_net) * 100 : 0;
        $productsVatPct = $subtotals->products_net > 0 ? ($subtotals->products_vat / $subtotals->products_net) * 100 : 0;

        $payments = [];
        try {
            $payments = DB::table('sale_payments as sp')
                ->join('sales as s', 'sp.sale_id', '=', 's.id')
                ->leftJoin('payment_methods as pm', 'sp.payment_method_id', '=', 'pm.id')
                ->whereNull('s.voided_at')
                ->whereBetween('s.created_at', [$fromDt, $toDt])
                ->selectRaw("COALESCE(pm.name, CAST(sp.payment_method_id AS TEXT)) as payment_method, COALESCE(SUM(sp.amount),0) as amount")
                ->groupBy('payment_method')
                ->orderBy('payment_method')
                ->get()
                ->toArray();
        } catch (\Throwable $e) {
            $payments = DB::table('sale_payments as sp')
                ->join('sales as s', 'sp.sale_id', '=', 's.id')
                ->whereNull('s.voided_at')
                ->whereBetween('s.created_at', [$fromDt, $toDt])
                ->selectRaw("CAST(sp.payment_method_id AS TEXT) as payment_method, COALESCE(SUM(sp.amount),0) as amount")
                ->groupBy('payment_method')
                ->orderBy('payment_method')
                ->get()
                ->toArray();
        }

        $company = [
            'company_name' => config('app.name', 'Company'),
            'company_vat_number' => '',
            'company_phone_number' => '',
            'company_address' => '',
        ];

        try {
            $cfg = DB::table('dashboard_settings')->first();
            if ($cfg) {
                $company = [
                    'company_name' => $cfg->company_name ?? $company['company_name'],
                    'company_vat_number' => $cfg->company_vat_number ?? '',
                    'company_phone_number' => $cfg->company_phone_number ?? '',
                    'company_address' => $cfg->company_address ?? '',
                ];
            }
        } catch (\Throwable $e) {
            // ignore if table doesn't exist
        }

        return view('reports.zreport_print', [
            'zr' => $zr,
            'company' => $company,
            'totals' => $totals,
            'subtotals' => $subtotals,
            'servicesVatPct' => $servicesVatPct,
            'productsVatPct' => $productsVatPct,
            'payments' => $payments,
        ]);
    }

    /**
     * Create Z report (AJAX)
     * POST /reports/zreport/generate
     * body: {from_date, to_date}
     */
    public function zReportGenerate(Request $request)
    {
        $from = (string)$request->input('from_date', '');
        $to   = (string)$request->input('to_date', '');

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
            return response()->json(['success' => false, 'error' => 'Invalid date range'], 422);
        }

        // If already exists, return it
        $existing = DB::table('z_reports')
            ->where('date_from', $from)
            ->where('date_to', $to)
            ->first();

        if ($existing) {
            return response()->json([
                'success' => true,
                'report_id' => $existing->id,
                'report_number' => $existing->report_number ?? $existing->id,
                'print_url' => route('reports.zreport.print', ['id' => $existing->id]),
            ]);
        }

        DB::beginTransaction();
        try {
            $id = DB::table('z_reports')->insertGetId([
                'report_number' => 0,
                'date_from' => $from,
                'date_to' => $to,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('z_reports')->where('id', $id)->update([
                'report_number' => $id,
                'updated_at' => now(),
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'report_id' => $id,
                'report_number' => $id,
                'print_url' => route('reports.zreport.print', ['id' => $id]),
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Delete Z report
     * DELETE /reports/zreport/{id}
     */
    public function zReportDelete(int $id)
    {
        DB::table('z_reports')->where('id', $id)->delete();

        return redirect()->route('reports.index', ['report' => 'generated_zreports'])
            ->with('success', 'Z report deleted.');
    }

    /**
     * Central report data loader (server-side) used for UI + PDF export
     */
    private function loadReportData(string $report, Request $request): array
    {
        $report = trim($report);

        $from = $request->query('from_date', Carbon::now()->toDateString());
        $to   = $request->query('to_date', Carbon::now()->toDateString());

        $fromDt = Carbon::parse($from)->startOfDay();
        $toDt   = Carbon::parse($to)->endOfDay();

        if ($report === 'analytics') {
            $toA   = $request->query('to', Carbon::now()->toDateString());
            $fromA = $request->query('from', Carbon::now()->subDays(29)->toDateString());

            $startDt = Carbon::parse($fromA)->startOfDay();
            $endDt   = Carbon::parse($toA)->endOfDay();

            $salesBase = DB::table('sales')
                ->whereNull('voided_at')
                ->whereBetween('created_at', [$startDt, $endDt]);

            $totalRevenue = (float) (clone $salesBase)->sum('grand_total');

            $totalAppointments = (int) DB::table('appointments')
                ->whereBetween('start_at', [$startDt, $endDt])
                ->count();

            $byDay = (clone $salesBase)
                ->selectRaw($this->exprDate('created_at') . " AS day, COALESCE(SUM(grand_total),0) AS revenue")
                ->groupBy('day')
                ->orderBy('day')
                ->get()
                ->toArray();

            [$staffSelectSql, $staffGroupBy, $needsUserJoin] = $this->staffDisplaySql('t', 'u');

            $q = DB::table('sale_services as ss')
                ->join('staff as t', 'ss.staff_id', '=', 't.id')
                ->join('sales as s', 'ss.sale_id', '=', 's.id')
                ->whereNull('s.voided_at')
                ->whereBetween('s.created_at', [$startDt, $endDt]);

            if ($needsUserJoin) {
                $q->leftJoin('users as u', 't.user_id', '=', 'u.id');
            }

            $topStaff = $q
                ->selectRaw("
                    t.id as staff_id,
                    {$staffSelectSql},
                    COALESCE(SUM(ss.line_total),0) as revenue
                ")
                ->groupBy(array_merge(['t.id'], $staffGroupBy))
                ->orderByDesc('revenue')
                ->limit(5)
                ->get()
                ->toArray();

            return [
                'type' => 'analytics',
                'from' => $fromA,
                'to'   => $toA,
                'totalRevenue' => $totalRevenue,
                'totalAppointments' => $totalAppointments,
                'byDay' => $byDay,
                'topStaff' => $topStaff,
            ];
        }

        if ($report === 'bi') {
            return [
                'type' => 'bi',
                'message' => 'BI page uses /reports/data JSON endpoint.',
            ];
        }

        switch ($report) {

            /**
             * ✅ NEW: Staff Performance
             * date_basis=sale -> filter sales by sales.created_at
             * date_basis=appointment -> filter sales by related appointment.start_at via sales.appointment_id
             */
            case 'staff_performance': {
                $dateBasis = (string)$request->query('date_basis', 'sale'); // sale|appointment
                $staffId = (int)$request->query('staff_id', 0);

                [$staffSelectSql, $staffGroupBy, $needsUserJoin] = $this->staffDisplaySql('t', 'u');

                // appointments per staff (always based on appointments.start_at within range)
                $apptSub = DB::table('appointments as a')
                    ->whereBetween('a.start_at', [$fromDt, $toDt])
                    ->selectRaw("a.staff_id, COUNT(*) as appointments_count")
                    ->groupBy('a.staff_id');

                if ($staffId > 0) {
                    $apptSub->where('a.staff_id', $staffId);
                }

                // service revenue per staff
                $serviceSub = DB::table('sale_services as ss')
                    ->join('sales as s', 'ss.sale_id', '=', 's.id')
                    ->whereNull('s.voided_at')
                    ->selectRaw("ss.staff_id, COALESCE(SUM(ss.line_total),0) as service_revenue")
                    ->groupBy('ss.staff_id');

                // product revenue per staff
                $productSub = DB::table('sale_products as sp')
                    ->join('sales as s', 'sp.sale_id', '=', 's.id')
                    ->whereNull('s.voided_at')
                    ->selectRaw("sp.staff_id, COALESCE(SUM(sp.line_total),0) as product_revenue")
                    ->groupBy('sp.staff_id');

                // date basis filtering
                if ($dateBasis === 'appointment') {
                    // only sales linked to appointments will count here
                    $serviceSub->leftJoin('appointments as a2', 's.appointment_id', '=', 'a2.id')
                        ->whereBetween('a2.start_at', [$fromDt, $toDt]);

                    $productSub->leftJoin('appointments as a2', 's.appointment_id', '=', 'a2.id')
                        ->whereBetween('a2.start_at', [$fromDt, $toDt]);
                } else {
                    $serviceSub->whereBetween('s.created_at', [$fromDt, $toDt]);
                    $productSub->whereBetween('s.created_at', [$fromDt, $toDt]);
                }

                if ($staffId > 0) {
                    $serviceSub->where('ss.staff_id', $staffId);
                    $productSub->where('sp.staff_id', $staffId);
                }

                // base staff
                $base = DB::table('staff as t');
                if ($needsUserJoin) {
                    $base->leftJoin('users as u', 't.user_id', '=', 'u.id');
                }

                $base->leftJoinSub($apptSub, 'ap', function ($join) {
                    $join->on('ap.staff_id', '=', 't.id');
                });

                $base->leftJoinSub($serviceSub, 'sr', function ($join) {
                    $join->on('sr.staff_id', '=', 't.id');
                });

                $base->leftJoinSub($productSub, 'pr', function ($join) {
                    $join->on('pr.staff_id', '=', 't.id');
                });

                if ($staffId > 0) {
                    $base->where('t.id', $staffId);
                }

                $rows = $base->selectRaw("
                        t.id as staff_id,
                        {$staffSelectSql},
                        COALESCE(ap.appointments_count,0) as appointments_count,
                        COALESCE(sr.service_revenue,0) as service_revenue,
                        COALESCE(pr.product_revenue,0) as product_revenue,
                        (COALESCE(sr.service_revenue,0) + COALESCE(pr.product_revenue,0)) as total_revenue
                    ")
                    ->orderByDesc('total_revenue')
                    ->orderByDesc('appointments_count')
                    ->get()
                    ->toArray();

                return [
                    'type' => 'table',
                    'from' => $from,
                    'to' => $to,
                    'date_basis' => $dateBasis,
                    'rows' => $rows,
                ];
            }

            case 'top_clients_appts':
                $rows = DB::table('appointments as a')
                    ->join('clients as c', 'a.client_id', '=', 'c.id')
                    ->whereBetween('a.start_at', [$fromDt, $toDt])
                    ->selectRaw("TRIM(COALESCE(c.first_name,'') || ' ' || COALESCE(c.last_name,'')) as client_name, COUNT(a.id) as appt_count")
                    ->groupBy('a.client_id', 'c.first_name', 'c.last_name')
                    ->orderByDesc('appt_count')
                    ->limit(10)
                    ->get()
                    ->toArray();

                return ['type' => 'table', 'from' => $from, 'to' => $to, 'rows' => $rows];

            case 'top_clients_payments':
                $rows = DB::table('sale_payments as sp')
                    ->join('sales as s', 'sp.sale_id', '=', 's.id')
                    ->leftJoin('clients as c', 's.client_id', '=', 'c.id')
                    ->whereNull('s.voided_at')
                    ->whereBetween('s.created_at', [$fromDt, $toDt])
                    ->selectRaw("COALESCE(TRIM(COALESCE(c.first_name,'') || ' ' || COALESCE(c.last_name,'')),'Walk-in') as client_name, SUM(sp.amount) as total_paid")
                    ->groupBy('s.client_id', 'c.first_name', 'c.last_name')
                    ->orderByDesc('total_paid')
                    ->limit(10)
                    ->get()
                    ->toArray();

                return ['type' => 'table', 'from' => $from, 'to' => $to, 'rows' => $rows];

            case 'top_staff_appts': {
                [$staffSelectSql, $staffGroupBy, $needsUserJoin] = $this->staffDisplaySql('t', 'u');

                $q = DB::table('appointments as a')
                    ->join('staff as t', 'a.staff_id', '=', 't.id')
                    ->whereBetween('a.start_at', [$fromDt, $toDt]);

                if ($needsUserJoin) {
                    $q->leftJoin('users as u', 't.user_id', '=', 'u.id');
                }

                $rows = $q
                    ->selectRaw("t.id as staff_id, {$staffSelectSql}, COUNT(a.id) as appt_count")
                    ->groupBy(array_merge(['t.id'], $staffGroupBy))
                    ->orderByDesc('appt_count')
                    ->limit(10)
                    ->get()
                    ->toArray();

                return ['type' => 'table', 'from' => $from, 'to' => $to, 'rows' => $rows];
            }

            case 'top_staff_payments': {
                [$staffSelectSql, $staffGroupBy, $needsUserJoin] = $this->staffDisplaySql('t', 'u');

                $q = DB::table('sale_services as ss')
                    ->join('staff as t', 'ss.staff_id', '=', 't.id')
                    ->join('sales as s', 'ss.sale_id', '=', 's.id')
                    ->whereNull('s.voided_at')
                    ->whereBetween('s.created_at', [$fromDt, $toDt]);

                if ($needsUserJoin) {
                    $q->leftJoin('users as u', 't.user_id', '=', 'u.id');
                }

                $rows = $q
                    ->selectRaw("t.id as staff_id, {$staffSelectSql}, SUM(ss.line_total) as total_revenue")
                    ->groupBy(array_merge(['t.id'], $staffGroupBy))
                    ->orderByDesc('total_revenue')
                    ->limit(10)
                    ->get()
                    ->toArray();

                return ['type' => 'table', 'from' => $from, 'to' => $to, 'rows' => $rows];
            }

            case 'first_appointments':
                $rows = DB::table('appointments as a')
                    ->join('clients as c', 'a.client_id', '=', 'c.id')
                    ->selectRaw("TRIM(COALESCE(c.first_name,'') || ' ' || COALESCE(c.last_name,'')) as client_name, MIN(a.start_at) as first_appt_date")
                    ->groupBy('a.client_id', 'c.first_name', 'c.last_name')
                    ->havingRaw("DATE(first_appt_date) BETWEEN ? AND ?", [Carbon::parse($from)->toDateString(), Carbon::parse($to)->toDateString()])
                    ->orderByDesc('first_appt_date')
                    ->limit(20)
                    ->get()
                    ->toArray();

                return ['type' => 'table', 'from' => $from, 'to' => $to, 'rows' => $rows];

            case 'gender_distribution':
                $rows = DB::table('clients')
                    ->whereBetween('created_at', [$fromDt, $toDt])
                    ->selectRaw("COALESCE(gender,'') as gender, COUNT(*) as count")
                    ->groupBy('gender')
                    ->get()
                    ->toArray();

                return ['type' => 'table', 'from' => $from, 'to' => $to, 'rows' => $rows];

            /**
             * Sales (Appointments)
             * Shows sales that have at least one service line.
             */
            case 'sales_appointments': {
                [$staffSelectSql, $staffGroupBy, $needsUserJoin] = $this->staffDisplaySql('t', 'u');

                $q = DB::table('sales as s')
                    ->leftJoin('clients as c', 's.client_id', '=', 'c.id')
                    ->join('sale_services as ss', 'ss.sale_id', '=', 's.id') // must have service
                    ->whereNull('s.voided_at')
                    ->whereBetween('s.created_at', [$fromDt, $toDt]);

                $q->leftJoin('staff as t', 'ss.staff_id', '=', 't.id');
                if ($needsUserJoin) {
                    $q->leftJoin('users as u', 't.user_id', '=', 'u.id');
                }

                $rows = $q
                    ->selectRaw("
                        s.id as sale_id,
                        s.created_at as sale_date,
                        COALESCE(TRIM(COALESCE(c.first_name,'') || ' ' || COALESCE(c.last_name,'')),'Walk-in') as client_name,
                        {$staffSelectSql},
                        (SELECT GROUP_CONCAT(sv.name, ', ')
                           FROM sale_services ss2
                           JOIN services sv ON sv.id = ss2.service_id
                          WHERE ss2.sale_id = s.id
                        ) as services_list,
                        (SELECT GROUP_CONCAT(p.name, ', ')
                           FROM sale_products sp2
                           JOIN products p ON p.id = sp2.product_id
                          WHERE sp2.sale_id = s.id
                        ) as products_list,
                        (COALESCE(s.services_subtotal,0) + COALESCE(s.products_subtotal,0)) as price,
                        (SELECT COALESCE(SUM(amount),0) FROM sale_payments spay WHERE spay.sale_id = s.id) as paid_amount
                    ")
                    ->groupBy(array_merge(['s.id', 's.created_at', 'c.first_name', 'c.last_name'], $staffGroupBy))
                    ->orderByDesc('s.created_at')
                    ->get()
                    ->toArray();

                return ['type' => 'table', 'from' => $from, 'to' => $to, 'rows' => $rows];
            }

            /**
             * Sales (Products)
             * Shows sales that have at least one product line.
             */
            case 'sales_products': {
                [$staffSelectSql, $staffGroupBy, $needsUserJoin] = $this->staffDisplaySql('t', 'u');

                $q = DB::table('sales as s')
                    ->leftJoin('clients as c', 's.client_id', '=', 'c.id')
                    ->join('sale_products as sp', 'sp.sale_id', '=', 's.id') // must have product
                    ->whereNull('s.voided_at')
                    ->whereBetween('s.created_at', [$fromDt, $toDt]);

                $q->leftJoin('staff as t', 'sp.staff_id', '=', 't.id');
                if ($needsUserJoin) {
                    $q->leftJoin('users as u', 't.user_id', '=', 'u.id');
                }

                $rows = $q
                    ->selectRaw("
                        s.id as sale_id,
                        s.created_at as sale_date,
                        COALESCE(TRIM(COALESCE(c.first_name,'') || ' ' || COALESCE(c.last_name,'')),'Walk-in') as client_name,
                        {$staffSelectSql},
                        (SELECT GROUP_CONCAT(p.name, ', ')
                           FROM sale_products sp2
                           JOIN products p ON p.id = sp2.product_id
                          WHERE sp2.sale_id = s.id
                        ) as products_list,
                        COALESCE(s.products_subtotal,0) as price,
                        (SELECT COALESCE(SUM(amount),0) FROM sale_payments spay WHERE spay.sale_id = s.id) as paid_amount
                    ")
                    ->groupBy(array_merge(['s.id', 's.created_at', 'c.first_name', 'c.last_name'], $staffGroupBy))
                    ->orderByDesc('s.created_at')
                    ->get()
                    ->toArray();

                return ['type' => 'table', 'from' => $from, 'to' => $to, 'rows' => $rows];
            }

            /**
             * Cashier (All) - one line per sale
             */
            case 'cashier_all': {
                $q = DB::table('sales as s')
                    ->leftJoin('clients as c', 's.client_id', '=', 'c.id')
                    ->whereNull('s.voided_at')
                    ->whereBetween('s.created_at', [$fromDt, $toDt]);

                $rows = $q->selectRaw("
                    s.id as sale_id,
                    s.created_at as sale_date,
                    COALESCE(TRIM(COALESCE(c.first_name,'') || ' ' || COALESCE(c.last_name,'')),'Walk-in') as client_name,
                    (SELECT GROUP_CONCAT(sv.name, ', ')
                       FROM sale_services ss2
                       JOIN services sv ON sv.id = ss2.service_id
                      WHERE ss2.sale_id = s.id
                    ) as services_list,
                    (SELECT GROUP_CONCAT(p.name, ', ')
                       FROM sale_products sp2
                       JOIN products p ON p.id = sp2.product_id
                      WHERE sp2.sale_id = s.id
                    ) as products_list,
                    (COALESCE(s.services_subtotal,0) + COALESCE(s.products_subtotal,0)) as total_price,
                    (SELECT COALESCE(SUM(amount),0) FROM sale_payments spay WHERE spay.sale_id = s.id) as paid_amount
                ")
                ->orderByDesc('s.created_at')
                ->get()
                ->toArray();

                return ['type' => 'table', 'from' => $from, 'to' => $to, 'rows' => $rows];
            }

            /**
             * Cashier (Staff) - optional staff_id filter
             */
            case 'cashier_staff': {
                $staffId = (int)$request->query('staff_id', 0);

                $sales = DB::table('sales as s')
                    ->leftJoin('clients as c', 's.client_id', '=', 'c.id')
                    ->whereNull('s.voided_at')
                    ->whereBetween('s.created_at', [$fromDt, $toDt]);

                if ($staffId > 0) {
                    $sales->where(function ($w) use ($staffId) {
                        $w->whereExists(function ($q) use ($staffId) {
                            $q->select(DB::raw(1))
                                ->from('sale_services as ssx')
                                ->whereColumn('ssx.sale_id', 's.id')
                                ->where('ssx.staff_id', $staffId);
                        })->orWhereExists(function ($q) use ($staffId) {
                            $q->select(DB::raw(1))
                                ->from('sale_products as spx')
                                ->whereColumn('spx.sale_id', 's.id')
                                ->where('spx.staff_id', $staffId);
                        });
                    });
                }

                $rows = $sales->selectRaw("
                    s.id as sale_id,
                    s.created_at as sale_date,
                    COALESCE(TRIM(COALESCE(c.first_name,'') || ' ' || COALESCE(c.last_name,'')),'Walk-in') as client_name,
                    (SELECT GROUP_CONCAT(sv.name, ', ')
                       FROM sale_services ss2
                       JOIN services sv ON sv.id = ss2.service_id
                      WHERE ss2.sale_id = s.id
                    ) as services_list,
                    (SELECT GROUP_CONCAT(p.name, ', ')
                       FROM sale_products sp2
                       JOIN products p ON p.id = sp2.product_id
                      WHERE sp2.sale_id = s.id
                    ) as products_list,
                    (COALESCE(s.services_subtotal,0) + COALESCE(s.products_subtotal,0)) as total_price,
                    (SELECT COALESCE(SUM(amount),0) FROM sale_payments spay WHERE spay.sale_id = s.id) as paid_amount
                ")
                ->orderByDesc('s.created_at')
                ->get()
                ->toArray();

                return ['type' => 'table', 'from' => $from, 'to' => $to, 'rows' => $rows];
            }

            /**
             * Cashier (Service) - optional service_id filter, must have at least 1 service line
             */
            case 'cashier_service': {
                $serviceId = (int)$request->query('service_id', 0);

                $sales = DB::table('sales as s')
                    ->leftJoin('clients as c', 's.client_id', '=', 'c.id')
                    ->whereNull('s.voided_at')
                    ->whereBetween('s.created_at', [$fromDt, $toDt]);

                if ($serviceId > 0) {
                    $sales->whereExists(function ($q) use ($serviceId) {
                        $q->select(DB::raw(1))
                            ->from('sale_services as ssx')
                            ->whereColumn('ssx.sale_id', 's.id')
                            ->where('ssx.service_id', $serviceId);
                    });
                } else {
                    $sales->whereExists(function ($q) {
                        $q->select(DB::raw(1))
                            ->from('sale_services as ssx')
                            ->whereColumn('ssx.sale_id', 's.id');
                    });
                }

                $rows = $sales->selectRaw("
                    s.id as sale_id,
                    s.created_at as sale_date,
                    COALESCE(TRIM(COALESCE(c.first_name,'') || ' ' || COALESCE(c.last_name,'')),'Walk-in') as client_name,
                    (SELECT sv.name
                       FROM sale_services ss2
                       JOIN services sv ON sv.id = ss2.service_id
                      WHERE ss2.sale_id = s.id
                      LIMIT 1
                    ) as service_name,
                    (SELECT GROUP_CONCAT(p.name, ', ')
                       FROM sale_products sp2
                       JOIN products p ON p.id = sp2.product_id
                      WHERE sp2.sale_id = s.id
                    ) as products_list,
                    (COALESCE(s.services_subtotal,0) + COALESCE(s.products_subtotal,0)) as total_price,
                    (SELECT COALESCE(SUM(amount),0) FROM sale_payments spay WHERE spay.sale_id = s.id) as paid_amount
                ")
                ->orderByDesc('s.created_at')
                ->get()
                ->toArray();

                return ['type' => 'table', 'from' => $from, 'to' => $to, 'rows' => $rows];
            }

            /**
             * Cashier (Products) - optional product_id filter, must have at least 1 product line
             */
            case 'cashier_products': {
                $productId = (int)$request->query('product_id', 0);

                $sales = DB::table('sales as s')
                    ->leftJoin('clients as c', 's.client_id', '=', 'c.id')
                    ->whereNull('s.voided_at')
                    ->whereBetween('s.created_at', [$fromDt, $toDt]);

                if ($productId > 0) {
                    $sales->whereExists(function ($q) use ($productId) {
                        $q->select(DB::raw(1))
                            ->from('sale_products as spx')
                            ->whereColumn('spx.sale_id', 's.id')
                            ->where('spx.product_id', $productId);
                    });
                } else {
                    $sales->whereExists(function ($q) {
                        $q->select(DB::raw(1))
                            ->from('sale_products as spx')
                            ->whereColumn('spx.sale_id', 's.id');
                    });
                }

                $rows = $sales->selectRaw("
                    s.id as sale_id,
                    s.created_at as sale_date,
                    COALESCE(TRIM(COALESCE(c.first_name,'') || ' ' || COALESCE(c.last_name,'')),'Walk-in') as client_name,
                    (SELECT GROUP_CONCAT(sv.name, ', ')
                       FROM sale_services ss2
                       JOIN services sv ON sv.id = ss2.service_id
                      WHERE ss2.sale_id = s.id
                    ) as services_list,
                    (SELECT p.name
                       FROM sale_products sp2
                       JOIN products p ON p.id = sp2.product_id
                      WHERE sp2.sale_id = s.id
                      LIMIT 1
                    ) as product_name,
                    (COALESCE(s.services_subtotal,0) + COALESCE(s.products_subtotal,0)) as total_price,
                    (SELECT COALESCE(SUM(amount),0) FROM sale_payments spay WHERE spay.sale_id = s.id) as paid_amount
                ")
                ->orderByDesc('s.created_at')
                ->get()
                ->toArray();

                return ['type' => 'table', 'from' => $from, 'to' => $to, 'rows' => $rows];
            }

            case 'z_reports':
                return [
                    'type' => 'empty',
                    'message' => 'Use the Generate button above to create a Z Report.',
                    'from' => $from,
                    'to' => $to,
                ];

            case 'generated_zreports':
                $rows = DB::table('z_reports')
                    ->select('id', 'report_number', 'date_from', 'date_to', 'created_at')
                    ->orderByDesc('created_at')
                    ->get()
                    ->toArray();

                return ['type' => 'table', 'rows' => $rows, 'actions' => true];

            default:
                return [
                    'type' => 'empty',
                    'message' => 'No report selected or report not implemented yet for PDF.',
                    'report' => $report,
                    'from' => $from,
                    'to' => $to,
                ];
        }
    }

    /*
     |--------------------------------------------------------------------------
     | Dropdown filter options (for reports.index)
     |--------------------------------------------------------------------------
     */

    private function filterStaffOptions(): array
    {
        $q = DB::table('staff as t')->leftJoin('users as u', 't.user_id', '=', 'u.id');

        $rows = $q->selectRaw("
            t.id as id,
            COALESCE(
                TRIM(COALESCE(u.first_name,'') || ' ' || COALESCE(u.last_name,'')),
                COALESCE(u.name,'')
            ) as name
        ")->orderBy('name')->get()->toArray();

        return array_map(function ($r) {
            $name = trim((string)($r->name ?? ''));
            if ($name === '') $name = 'Staff #' . ($r->id ?? '');
            return ['id' => (int)$r->id, 'name' => $name];
        }, $rows);
    }

    private function filterServicesOptions(): array
    {
        if (!$this->tableExists('services')) return [];
        return DB::table('services')
            ->select('id', 'name')
            ->orderBy('name')
            ->get()
            ->map(fn ($r) => ['id' => (int)$r->id, 'name' => (string)$r->name])
            ->toArray();
    }

    private function filterProductsOptions(): array
    {
        if (!$this->tableExists('products')) return [];
        return DB::table('products')
            ->select('id', 'name')
            ->orderBy('name')
            ->get()
            ->map(fn ($r) => ['id' => (int)$r->id, 'name' => (string)$r->name])
            ->toArray();
    }

    /*
     |--------------------------------------------------------------------------
     | BI REPORT IMPLEMENTATIONS
     |--------------------------------------------------------------------------
     */

    private function biYoY(): array
    {
        $yearExpr = $this->exprYear('created_at');

        $years = DB::table('sales')
            ->whereNull('voided_at')
            ->selectRaw("DISTINCT {$yearExpr} AS y")
            ->orderByDesc('y')
            ->limit(2)
            ->pluck('y')
            ->map(fn($v) => (int)$v)
            ->toArray();

        sort($years);

        $labels = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
        $datasets = [];

        foreach ($years as $y) {
            $monthExpr = $this->exprMonth('created_at');

            $rows = DB::table('sales')
                ->whereNull('voided_at')
                ->whereRaw($this->whereYear('created_at'), [$y])
                ->selectRaw("{$monthExpr} AS m, COALESCE(SUM(grand_total),0) AS amt")
                ->groupBy('m')
                ->get();

            $m2 = array_fill(1, 12, 0.0);
            foreach ($rows as $r) {
                $m = (int)($r->m ?? 0);
                if ($m >= 1 && $m <= 12) $m2[$m] = (float)$r->amt;
            }

            $datasets[] = [
                'label' => (string)$y,
                'data'  => array_values($m2),
                'fill'  => false,
            ];
        }

        return ['ok' => true, 'labels' => $labels, 'datasets' => $datasets];
    }

    private function biYtd(): array
    {
        $year = (int)date('Y');
        $monthExpr = $this->exprMonth('created_at');

        $incRows = DB::table('sales')
            ->whereNull('voided_at')
            ->whereRaw($this->whereYear('created_at'), [$year])
            ->selectRaw("{$monthExpr} AS m, COALESCE(SUM(grand_total),0) AS amt")
            ->groupBy('m')
            ->get();

        $income = array_fill(1, 12, 0.0);
        foreach ($incRows as $r) {
            $income[(int)$r->m] = (float)$r->amt;
        }

        $expenses = array_fill(1, 12, 0.0);
        $warning = null;

        if ($this->tableExists('expenses')) {
            $expMonthExpr = $this->exprMonth('date');

            $expRows = DB::table('expenses')
                ->whereRaw($this->whereYear('date'), [$year])
                ->selectRaw("{$expMonthExpr} AS m, COALESCE(SUM(amount_paid),0) AS amt")
                ->groupBy('m')
                ->get();

            foreach ($expRows as $r) {
                $m = (int)$r->m;
                if ($m >= 1 && $m <= 12) $expenses[$m] = (float)$r->amt;
            }
        } else {
            $warning = "Table 'expenses' not found; returning expenses as zeros.";
        }

        return [
            'ok' => true,
            'labels' => array_keys($income),
            'income' => array_values($income),
            'expenses' => array_values($expenses),
            'warning' => $warning,
        ];
    }

    private function biExpenseCategory(): array
    {
        if (!$this->tableExists('expenses')) {
            return ['ok' => false, 'error' => "Table 'expenses' not found."];
        }

        $rows = DB::table('expenses')
            ->selectRaw("name, COALESCE(SUM(amount_paid),0) tot")
            ->groupBy('name')
            ->get();

        $labels = $rows->pluck('name')->toArray();
        $data = $rows->pluck('tot')->map(fn($v) => (float)$v)->toArray();

        return ['ok' => true, 'labels' => $labels, 'data' => $data];
    }

    private function biTopVendors(): array
    {
        if (!$this->tableExists('expenses')) {
            return ['ok' => false, 'error' => "Table 'expenses' not found."];
        }

        $rows = DB::table('expenses')
            ->selectRaw("name, COALESCE(SUM(amount_paid),0) tot")
            ->groupBy('name')
            ->orderByDesc('tot')
            ->limit(10)
            ->get();

        return [
            'ok' => true,
            'labels' => $rows->pluck('name')->toArray(),
            'data' => $rows->pluck('tot')->map(fn($v) => (float)$v)->toArray(),
        ];
    }

    private function biPL(): array
    {
        $year = (int)date('Y');

        $income = array_fill(1, 12, 0.0);
        $expenses = array_fill(1, 12, 0.0);

        $salesRows = DB::table('sales')
            ->whereNull('voided_at')
            ->whereRaw($this->whereYear('created_at'), [$year])
            ->selectRaw($this->exprMonth('created_at') . " AS m, COALESCE(SUM(grand_total),0) AS amt")
            ->groupBy('m')
            ->get();

        foreach ($salesRows as $r) {
            $m = (int)$r->m;
            if ($m >= 1 && $m <= 12) $income[$m] = (float)$r->amt;
        }

        $warning = null;
        if ($this->tableExists('expenses')) {
            $expRows = DB::table('expenses')
                ->whereRaw($this->whereYear('date'), [$year])
                ->selectRaw($this->exprMonth('date') . " AS m, COALESCE(SUM(amount_paid),0) AS amt")
                ->groupBy('m')
                ->get();

            foreach ($expRows as $r) {
                $m = (int)$r->m;
                if ($m >= 1 && $m <= 12) $expenses[$m] = (float)$r->amt;
            }
        } else {
            $warning = "Table 'expenses' not found; expenses are zeros.";
        }

        $profit = [];
        foreach ($income as $m => $amt) {
            $profit[$m] = $amt - ($expenses[$m] ?? 0);
        }

        $labels = [];
        for ($i = 1; $i <= 12; $i++) {
            $labels[] = date('M', mktime(0, 0, 0, $i, 1));
        }

        return [
            'ok' => true,
            'labels' => $labels,
            'income' => array_values($income),
            'expenses' => array_values($expenses),
            'profit' => array_values($profit),
            'warning' => $warning,
        ];
    }

    private function biDrill(string $start, string $end): array
    {
        if (!$start || !$end) {
            return ['ok' => false, 'error' => 'Missing start/end'];
        }

        $startDt = Carbon::parse($start)->startOfDay();
        $endDt   = Carbon::parse($end)->endOfDay();

        $sales = DB::table('sales')
            ->whereNull('voided_at')
            ->whereBetween('created_at', [$startDt, $endDt])
            ->selectRaw($this->exprDate('created_at') . " AS date, COALESCE(SUM(grand_total),0) AS amount")
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->toArray();

        $expenses = [];
        $warning = null;

        if ($this->tableExists('expenses')) {
            $expenses = DB::table('expenses')
                ->whereBetween('date', [$startDt->toDateString(), $endDt->toDateString()])
                ->select('date', 'name', DB::raw('amount_paid as amount'))
                ->orderBy('date')
                ->get()
                ->toArray();
        } else {
            $warning = "Table 'expenses' not found; expenses list is empty.";
        }

        return ['ok' => true, 'sales' => $sales, 'expenses' => $expenses, 'warning' => $warning];
    }

    private function biYoYTable(): array
    {
        if (!$this->tableExists('income')) {
            return ['ok' => false, 'error' => "Table 'income' not found (needed for yoy_table)."];
        }

        $yearCurrent = (int)date('Y');
        $yearLast = $yearCurrent - 1;

        $sumExpr = "COALESCE(cash,0)+COALESCE(revolut,0)+COALESCE(visa,0)+COALESCE(other,0)";

        $stmt = DB::table('income')
            ->selectRaw($this->exprMonth('date') . " AS m, COALESCE(SUM({$sumExpr}),0) AS total")
            ->whereRaw($this->whereYear('date'), [$yearCurrent])
            ->groupBy('m')
            ->get();

        $curData = array_fill(1, 12, 0.0);
        foreach ($stmt as $r) $curData[(int)$r->m] = (float)$r->total;

        $stmt2 = DB::table('income')
            ->selectRaw($this->exprMonth('date') . " AS m, COALESCE(SUM({$sumExpr}),0) AS total")
            ->whereRaw($this->whereYear('date'), [$yearLast])
            ->groupBy('m')
            ->get();

        $lastData = array_fill(1, 12, 0.0);
        foreach ($stmt2 as $r) $lastData[(int)$r->m] = (float)$r->total;

        $months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
        $rows = [];
        foreach ($months as $i => $name) {
            $m = $i + 1;
            $rows[] = [
                'month' => $name,
                'cur' => $curData[$m],
                'last' => $lastData[$m],
            ];
        }

        return [
            'ok' => true,
            'yearCurrent' => $yearCurrent,
            'yearLast' => $yearLast,
            'rows' => $rows,
        ];
    }

    private function biSalesByCategory(): array
    {
        try {
            $rows = DB::table('sales as s')
                ->join('sale_services as ss', 'ss.sale_id', '=', 's.id')
                ->join('services as sv', 'ss.service_id', '=', 'sv.id')
                ->join('categories as c', 'sv.category_id', '=', 'c.id')
                ->whereNull('s.voided_at')
                ->selectRaw("c.name AS category, COALESCE(SUM(s.grand_total),0) AS total")
                ->groupBy('c.name')
                ->orderByDesc('total')
                ->get();

            return [
                'ok' => true,
                'categories' => $rows->pluck('category')->toArray(),
                'totals' => $rows->pluck('total')->map(fn($v) => (float)$v)->toArray(),
            ];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => 'sales_category failed', 'details' => $e->getMessage()];
        }
    }

    private function biTopProducts(): array
    {
        try {
            $rows = DB::table('sale_products as sp')
                ->join('products as p', 'sp.product_id', '=', 'p.id')
                ->selectRaw("p.name, COALESCE(SUM(sp.line_total),0) AS revenue")
                ->groupBy('p.name')
                ->orderByDesc('revenue')
                ->limit(10)
                ->get();

            return [
                'ok' => true,
                'products' => $rows->pluck('name')->toArray(),
                'revenue' => $rows->pluck('revenue')->map(fn($v) => (float)$v)->toArray(),
            ];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => 'top_products failed', 'details' => $e->getMessage()];
        }
    }

    private function biCustomerLtv(): array
    {
        try {
            $rows = DB::table('clients as c')
                ->join('sales as s', 's.client_id', '=', 'c.id')
                ->whereNull('s.voided_at')
                ->selectRaw("c.id, TRIM(COALESCE(c.first_name,'') || ' ' || COALESCE(c.last_name,'')) AS client, COALESCE(SUM(s.grand_total),0) AS lifetime_value")
                ->groupBy('c.id', 'c.first_name', 'c.last_name')
                ->get();

            $segments = ['Low (<500)', 'Mid (500–2k)', 'High (>2k)'];
            $counts = [0, 0, 0];
            $ltv_sum = [0.0, 0.0, 0.0];

            foreach ($rows as $r) {
                $v = (float)$r->lifetime_value;
                if ($v < 500) $i = 0;
                elseif ($v < 2000) $i = 1;
                else $i = 2;

                $counts[$i]++;
                $ltv_sum[$i] += $v;
            }

            $avg_ltv = [];
            for ($i = 0; $i < 3; $i++) {
                $avg_ltv[$i] = $counts[$i] ? ($ltv_sum[$i] / $counts[$i]) : 0.0;
            }

            return [
                'ok' => true,
                'segments' => $segments,
                'counts' => $counts,
                'avg_ltv' => $avg_ltv,
            ];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => 'customer_ltv failed', 'details' => $e->getMessage()];
        }
    }

    /*
     |--------------------------------------------------------------------------
     | SQL Helpers
     |--------------------------------------------------------------------------
     */

    private function driver(): string
    {
        return (string) DB::getDriverName(); // sqlite / mysql / pgsql etc
    }

    private function exprYear(string $col): string
    {
        if ($this->driver() === 'sqlite') {
            return "CAST(strftime('%Y', {$col}) AS INTEGER)";
        }
        return "YEAR({$col})";
    }

    private function exprMonth(string $col): string
    {
        if ($this->driver() === 'sqlite') {
            return "CAST(strftime('%m', {$col}) AS INTEGER)";
        }
        return "MONTH({$col})";
    }

    private function exprDate(string $col): string
    {
        return "DATE({$col})";
    }

    private function whereYear(string $col): string
    {
        if ($this->driver() === 'sqlite') {
            return "strftime('%Y', {$col}) = ?";
        }
        return "YEAR({$col}) = ?";
    }

    private function tableExists(string $table): bool
    {
        try {
            return Schema::hasTable($table);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Build staff display name select SQL + groupBy cols.
     * Your staff table has no "name" fields, so we default to users table via staff.user_id.
     *
     * Returns: [selectSql, groupByCols, needsUserJoin]
     */
    private function staffDisplaySql(string $staffAlias = 't', string $userAlias = 'u'): array
    {
        $staffCols = [];
        try { $staffCols = Schema::getColumnListing('staff'); } catch (\Throwable $e) { $staffCols = []; }
        $hasStaff = fn(string $c) => in_array($c, $staffCols, true);

        if ($hasStaff('first_name') && $hasStaff('last_name')) {
            $expr = $this->driver() === 'sqlite'
                ? "TRIM(COALESCE({$staffAlias}.first_name,'') || ' ' || COALESCE({$staffAlias}.last_name,''))"
                : "TRIM(CONCAT(COALESCE({$staffAlias}.first_name,''),' ',COALESCE({$staffAlias}.last_name,'')))";

            return [
                "COALESCE({$staffAlias}.first_name,'') as first_name, COALESCE({$staffAlias}.last_name,'') as last_name, {$expr} as name",
                ["{$staffAlias}.first_name", "{$staffAlias}.last_name"],
                false
            ];
        }

        if ($hasStaff('name')) {
            return [
                "COALESCE({$staffAlias}.name,'') as name",
                ["{$staffAlias}.name"],
                false
            ];
        }

        $userCols = [];
        try { $userCols = Schema::getColumnListing('users'); } catch (\Throwable $e) { $userCols = []; }
        $hasUser = fn(string $c) => in_array($c, $userCols, true);

        if ($hasUser('first_name') && $hasUser('last_name')) {
            $expr = $this->driver() === 'sqlite'
                ? "TRIM(COALESCE({$userAlias}.first_name,'') || ' ' || COALESCE({$userAlias}.last_name,''))"
                : "TRIM(CONCAT(COALESCE({$userAlias}.first_name,''),' ',COALESCE({$userAlias}.last_name,'')))";

            return [
                "COALESCE({$userAlias}.first_name,'') as first_name, COALESCE({$userAlias}.last_name,'') as last_name, {$expr} as name",
                ["{$userAlias}.first_name", "{$userAlias}.last_name"],
                true
            ];
        }

        if ($hasUser('name')) {
            return [
                "COALESCE({$userAlias}.name,'') as name",
                ["{$userAlias}.name"],
                true
            ];
        }

        if ($hasUser('email')) {
            return [
                "COALESCE({$userAlias}.email,'') as name",
                ["{$userAlias}.email"],
                true
            ];
        }

        $fallback = $this->driver() === 'sqlite'
            ? "'Staff #' || CAST({$staffAlias}.id AS TEXT) as name"
            : "CONCAT('Staff #', {$staffAlias}.id) as name";

        return [
            $fallback,
            [],
            false
        ];
    }
}
