<?php

namespace App\Http\Controllers;

use App\Models\DashboardSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class PosController extends Controller
{
    public function index()
    {
        // 1) Products with VAT
        $products = DB::table('products as p')
            ->join('vat_types as vt', 'p.sell_vat_type_id', '=', 'vt.id')
            ->select('p.id', 'p.name', 'p.sell_price', 'vt.vat_percent')
            ->orderBy('p.name')
            ->get();

        // 2) Services with VAT
        $services = DB::table('services as s')
            ->join('vat_types as vt', 's.vat_type_id', '=', 'vt.id')
            ->select('s.id', 's.name', DB::raw('s.price as sell_price'), 'vt.vat_percent')
            ->orderBy('s.name')
            ->get();

        // 3) Pending appointments (today, no sale yet)
        $appointments = DB::table('appointments as a')
            ->join('services as sv', 'a.service_id', '=', 'sv.id')
            ->join('vat_types as vt', 'sv.vat_type_id', '=', 'vt.id')
            ->leftJoin('clients as c', 'a.client_id', '=', 'c.id')
            ->leftJoin('sales as s', 's.appointment_id', '=', 'a.id')
            ->whereNull('s.id')
            ->whereDate('a.start_at', Carbon::today()->toDateString())
            ->select([
                'a.id',
                'a.service_id',
                'a.start_at',
                'a.end_at',
                DB::raw("COALESCE(TRIM(COALESCE(c.first_name,'') || ' ' || COALESCE(c.last_name,'')), a.client_name) as client_name"),
                DB::raw("sv.name as service_name"),
                DB::raw("sv.price as sell_price"),
                'vt.vat_percent',
            ])
            ->orderBy('a.start_at')
            ->get()
            ->map(function ($row) {
                $start = $row->start_at ? Carbon::parse($row->start_at) : null;
                $row->appointment_date = $start ? $start->format('Y-m-d') : '';
                $row->start_time = $start ? $start->format('H:i') : '';
                return $row;
            })
            ->values();

        // 4) Clients
        $clients = DB::table('clients')
            ->select('id', DB::raw("TRIM(COALESCE(first_name,'') || ' ' || COALESCE(last_name,'')) as name"))
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();

        // 5) Staff (staff -> users.name)
        $staff = DB::table('staff as st')
            ->leftJoin('users as u', 'u.id', '=', 'st.user_id')
            ->select('st.id', DB::raw("COALESCE(u.name, 'Staff #' || st.id) as name"))
            ->orderBy('st.id')
            ->get();

        // 6) Payment methods
        $payments = DB::table('payment_methods')
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        return view('pos.index', [
            'products'     => $products,
            'services'     => $services,
            'appointments' => $appointments,
            'clients'      => $clients,
            'staff'        => $staff,
            'payments'     => $payments,
        ]);
    }

    public function checkout(Request $request)
    {
        $data = $request->validate([
            'items_json'        => ['required', 'string'],
            'staff_id'          => ['required', 'integer'],
            'payment_method_id' => ['required', 'integer'],
            'amount_paid'       => ['required', 'numeric'],
            'client_id'         => ['nullable', 'integer'],
        ]);

        $items = json_decode($data['items_json'], true);
        if (!is_array($items) || count($items) === 0) {
            return response()->json(['error' => 'Cart is empty.'], 422);
        }

        $staffId  = (int) $data['staff_id'];
        $methodId = (int) $data['payment_method_id'];
        $paid     = (float) $data['amount_paid'];
        $clientId = !empty($data['client_id']) ? (int) $data['client_id'] : null;

        try {
            return DB::transaction(function () use ($items, $staffId, $methodId, $paid, $clientId) {

                // (A) detect appointment sale if any
                $appointmentId = null;
                foreach ($items as $it) {
                    if (($it['type'] ?? '') === 'appointment') {
                        $appointmentId = (int)($it['appt_id'] ?? 0);
                        if ($appointmentId > 0) break;
                    }
                }

                // (B) compute VAT-inclusive → net & tax totals
                $servicesSub = 0.0; $productsSub = 0.0;
                $servicesVat = 0.0; $productsVat = 0.0;

                foreach ($items as $it) {
                    $type    = (string)($it['type'] ?? '');
                    $unitInc = (float)($it['price'] ?? 0);
                    $vatPct  = ((float)($it['vat'] ?? 0)) / 100.0;
                    $qty     = max(1, (int)($it['qty'] ?? 1));

                    if ($unitInc <= 0) continue;

                    $netPerUnit = $unitInc / (1.0 + $vatPct);
                    $taxPerUnit = $unitInc - $netPerUnit;

                    if ($type === 'service' || $type === 'appointment') {
                        $servicesSub += $netPerUnit * $qty;
                        $servicesVat += $taxPerUnit * $qty;
                    } else {
                        $productsSub += $netPerUnit * $qty;
                        $productsVat += $taxPerUnit * $qty;
                    }
                }

                $totalVat = $servicesVat + $productsVat;
                $grand    = $servicesSub + $productsSub + $totalVat;

                if ($paid + 0.00001 < $grand) {
                    return response()->json(['error' => 'Amount paid is less than grand total.'], 422);
                }

                // (C) insert sale
                $saleRow = [
                    'appointment_id'    => $appointmentId,
                    'client_id'         => $clientId,
                    'services_subtotal' => $servicesSub,
                    'services_vat'      => $servicesVat,
                    'products_subtotal' => $productsSub,
                    'products_vat'      => $productsVat,
                    'total_vat'         => $totalVat,
                    'grand_total'       => $grand,
                ];

                if (Schema::hasColumn('sales', 'sale_date')) {
                    $saleRow['sale_date'] = now();
                }
                if (Schema::hasColumn('sales', 'created_at')) $saleRow['created_at'] = now();
                if (Schema::hasColumn('sales', 'updated_at')) $saleRow['updated_at'] = now();

                $saleId = DB::table('sales')->insertGetId($saleRow);

                // helpers for timestamps
                $ssHasCreated = Schema::hasColumn('sale_services', 'created_at');
                $ssHasUpdated = Schema::hasColumn('sale_services', 'updated_at');
                $spHasCreated = Schema::hasColumn('sale_products', 'created_at');
                $spHasUpdated = Schema::hasColumn('sale_products', 'updated_at');

                // (D) line-items
                foreach ($items as $it) {
                    $type      = (string)($it['type'] ?? '');
                    $qty       = max(1, (int)($it['qty'] ?? 1));
                    $unitInc   = (float)($it['price'] ?? 0);
                    $lineTotal = $unitInc * $qty;

                    if ($unitInc <= 0) continue;

                    if ($type === 'service') {
                        $row = [
                            'sale_id'    => $saleId,
                            'service_id' => (int)($it['id'] ?? 0),
                            'staff_id'   => $staffId,
                            'quantity'   => $qty,
                            'unit_price' => $unitInc,
                            'line_total' => $lineTotal,
                        ];
                        if ($ssHasCreated) $row['created_at'] = now();
                        if ($ssHasUpdated) $row['updated_at'] = now();
                        DB::table('sale_services')->insert($row);

                    } elseif ($type === 'appointment') {
                        $row = [
                            'sale_id'    => $saleId,
                            'service_id' => (int)($it['service_id'] ?? 0),
                            'staff_id'   => $staffId,
                            'quantity'   => 1,
                            'unit_price' => $unitInc,
                            'line_total' => $unitInc,
                        ];
                        if ($ssHasCreated) $row['created_at'] = now();
                        if ($ssHasUpdated) $row['updated_at'] = now();
                        DB::table('sale_services')->insert($row);

                    } else { // product
                        $row = [
                            'sale_id'    => $saleId,
                            'product_id' => (int)($it['id'] ?? 0),
                            'staff_id'   => $staffId,
                            'quantity'   => $qty,
                            'unit_price' => $unitInc,
                            'line_total' => $lineTotal,
                        ];
                        if ($spHasCreated) $row['created_at'] = now();
                        if ($spHasUpdated) $row['updated_at'] = now();
                        DB::table('sale_products')->insert($row);

                        // Optional: decrease stock if column exists
                        if (Schema::hasColumn('products', 'quantity_stock')) {
                            $pid = (int)($it['id'] ?? 0);
                            if ($pid > 0) {
                                $upd = [
                                    'quantity_stock' => DB::raw('quantity_stock - ' . (int)$qty),
                                ];
                                if (Schema::hasColumn('products', 'updated_at')) $upd['updated_at'] = now();
                                DB::table('products')->where('id', $pid)->update($upd);
                            }
                        }
                    }
                }

                // (E) payment — your migration uses payment_method_id, so store that.
                DB::table('sale_payments')->insert([
                    'sale_id'           => $saleId,
                    'payment_method_id' => $methodId,
                    'amount'            => $paid,
                    'created_at'        => now(),
                    'updated_at'        => now(),
                ]);

                // (F) loyalty (optional)
                if (
                    Schema::hasTable('loyalty_settings') &&
                    Schema::hasTable('loyalty_transactions') &&
                    Schema::hasTable('client_loyalty')
                ) {
                    $rate = (int)(DB::table('loyalty_settings')->where('key', 'points_per_euro')->value('value') ?? 0);
                    $points = ($rate > 0) ? (int) floor($grand * $rate) : 0;

                    $awardClient = null;
                    if ($appointmentId) {
                        $awardClient = (int)(DB::table('appointments')->where('id', $appointmentId)->value('client_id') ?? 0);
                        if ($awardClient <= 0) $awardClient = null;
                    } elseif ($clientId) {
                        $awardClient = $clientId;
                    }

                    if ($awardClient && $points > 0) {
                        DB::table('loyalty_transactions')->insert([
                            'client_id'    => $awardClient,
                            'change'       => $points,
                            'reason'       => 'purchase',
                            'reference_id' => $saleId,
                            'created_at'   => now(),
                            'updated_at'   => now(),
                        ]);

                        $existing = DB::table('client_loyalty')->where('client_id', $awardClient)->first();
                        if ($existing) {
                            DB::table('client_loyalty')->where('client_id', $awardClient)->update([
                                'points_balance' => (int)$existing->points_balance + $points,
                                'updated_at'     => now(),
                            ]);
                        } else {
                            DB::table('client_loyalty')->insert([
                                'client_id'      => $awardClient,
                                'points_balance' => $points,
                                'created_at'     => now(),
                                'updated_at'     => now(),
                            ]);
                        }
                    }
                }

                return response()->json([
                    'sale_id'     => $saleId,
                    'grand_total' => round($grand, 2),
                    'receipt_url' => route('pos.receipt', ['sale' => $saleId]),
                ]);
            });
        } catch (Throwable $e) {
            report($e);
            return response()->json(['error' => 'Save failed: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Printable receipt (80mm thermal)
     */
    public function receipt($saleId)
    {
        $saleId = (int) $saleId;

        // Sale header + manual client + appointment client + appointment service
        $sale = DB::table('sales as s')
            ->leftJoin('clients as mc', 'mc.id', '=', 's.client_id') // manual client
            ->leftJoin('appointments as a', 'a.id', '=', 's.appointment_id')
            ->leftJoin('clients as ac', 'ac.id', '=', 'a.client_id') // appointment client
            ->leftJoin('services as sv', 'sv.id', '=', 'a.service_id')
            ->select([
                's.*',

                // manual client
                's.client_id as manual_client_id',
                'mc.first_name as manual_first',
                'mc.last_name as manual_last',
                'mc.mobile as manual_mobile',

                // appointment info
                'a.id as appt_id',
                'a.start_at as appt_start_at',
                'a.client_name as appt_client_name_fallback',
                'ac.first_name as appt_first',
                'ac.last_name as appt_last',
                'ac.mobile as appt_mobile',
                'sv.name as appt_service_name',
            ])
            ->where('s.id', $saleId)
            ->first();

        abort_if(!$sale, 404);

        $saleDate = null;
        if (!empty($sale->sale_date)) $saleDate = Carbon::parse($sale->sale_date);
        elseif (!empty($sale->created_at)) $saleDate = Carbon::parse($sale->created_at);
        else $saleDate = now();

        // Decide which client to print
        $isAppt = false;
        if (!empty($sale->manual_client_id)) {
            $clientName   = trim(($sale->manual_first ?? '') . ' ' . ($sale->manual_last ?? '')) ?: 'Walk-in';
            $clientMobile = (string)($sale->manual_mobile ?? '');
            $isAppt = false;
        } elseif (!empty($sale->appt_id)) {
            $apptName = trim(($sale->appt_first ?? '') . ' ' . ($sale->appt_last ?? ''));
            if (!$apptName) $apptName = (string)($sale->appt_client_name_fallback ?? '');
            $clientName   = $apptName ?: 'Walk-in';
            $clientMobile = (string)($sale->appt_mobile ?? '');
            $isAppt = true;
        } else {
            $clientName = 'Walk-in';
            $clientMobile = '';
            $isAppt = false;
        }

        $apptTime = null;
        if ($isAppt && !empty($sale->appt_start_at)) {
            $apptTime = Carbon::parse($sale->appt_start_at);
        }

        // Lines
        $serviceLines = DB::table('sale_services as ss')
            ->join('services as sv', 'sv.id', '=', 'ss.service_id')
            ->select('sv.name as name', 'ss.quantity', 'ss.unit_price', 'ss.line_total')
            ->where('ss.sale_id', $saleId)
            ->orderBy('sv.name')
            ->get();

        $productLines = DB::table('sale_products as sp')
            ->join('products as p', 'p.id', '=', 'sp.product_id')
            ->select('p.name as name', 'sp.quantity', 'sp.unit_price', 'sp.line_total')
            ->where('sp.sale_id', $saleId)
            ->orderBy('p.name')
            ->get();

        // Payments with method name
        $payments = DB::table('sale_payments as sp')
            ->leftJoin('payment_methods as pm', 'pm.id', '=', 'sp.payment_method_id')
            ->where('sp.sale_id', $saleId)
            ->select([
                'sp.*',
                DB::raw("COALESCE(pm.name,'') as method_name"),
            ])
            ->get();

        // Company info from dashboard_settings (id=1 equivalent)
        $ds = DashboardSetting::query()->first();
        $company = [
            'company_name'         => $ds->company_name ?? ($ds->dashboard_name ?? config('app.name')),
            'company_vat_number'   => $ds->company_vat_number ?? '',
            'company_phone_number' => $ds->company_phone_number ?? '',
            'company_address'      => $ds->company_address ?? '',
        ];

        $grandTotal = (float)($sale->grand_total ?? 0);
        $amountPaid = (float)$payments->sum('amount');
        $changeDue  = $amountPaid - $grandTotal;

        return view('pos.receipt', [
            'sale_id'      => $saleId,
            'sale'         => $sale,
            'saleDate'     => $saleDate,
            'clientName'   => $clientName,
            'clientMobile' => $clientMobile,
            'isAppt'       => $isAppt,
            'apptTime'     => $apptTime,
            'apptService'  => (string)($sale->appt_service_name ?? ''),
            'serviceLines' => $serviceLines,
            'productLines' => $productLines,
            'payments'     => $payments,
            'company'      => $company,
            'amountPaid'   => $amountPaid,
            'changeDue'    => $changeDue,
        ]);
    }
}
