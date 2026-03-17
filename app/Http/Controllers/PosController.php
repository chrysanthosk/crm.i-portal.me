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
        $products = DB::table('products as p')
            ->join('vat_types as vt', 'p.sell_vat_type_id', '=', 'vt.id')
            ->select('p.id', 'p.name', 'p.sell_price', 'vt.vat_percent')
            ->orderBy('p.name')
            ->get();

        $services = DB::table('services as s')
            ->join('vat_types as vt', 's.vat_type_id', '=', 'vt.id')
            ->select('s.id', 's.name', DB::raw('s.price as sell_price'), 'vt.vat_percent')
            ->orderBy('s.name')
            ->get();

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

        $clients = DB::table('clients')
            ->select('id', DB::raw("TRIM(CONCAT(COALESCE(first_name,''), ' ', COALESCE(last_name,''))) as name"))
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();

        $staff = DB::table('staff as st')
            ->leftJoin('users as u', 'u.id', '=', 'st.user_id')
            ->select('st.id', DB::raw("COALESCE(u.name, CONCAT('Staff #', st.id)) as name"))
            ->orderBy('st.id')
            ->get();

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
                $normalizedItems = [];
                $appointmentId = null;

                foreach ($items as $idx => $it) {
                    $type = (string)($it['type'] ?? '');
                    $qty  = max(1, (int)($it['qty'] ?? 1));

                    if ($type === 'service') {
                        $serviceId = (int)($it['id'] ?? 0);
                        $service = DB::table('services as s')
                            ->join('vat_types as vt', 's.vat_type_id', '=', 'vt.id')
                            ->where('s.id', $serviceId)
                            ->select('s.id', DB::raw('s.price as sell_price'), 'vt.vat_percent')
                            ->first();

                        if (!$service) {
                            return response()->json(['error' => "Service item #{$idx} is invalid."], 422);
                        }

                        $normalizedItems[] = [
                            'type' => 'service',
                            'service_id' => $serviceId,
                            'quantity' => $qty,
                            'unit_price' => (float)$service->sell_price,
                            'vat_percent' => (float)$service->vat_percent,
                        ];
                        continue;
                    }

                    if ($type === 'appointment') {
                        $apptId = (int)($it['appt_id'] ?? 0);
                        $appt = DB::table('appointments as a')
                            ->join('services as s', 'a.service_id', '=', 's.id')
                            ->join('vat_types as vt', 's.vat_type_id', '=', 'vt.id')
                            ->leftJoin('sales as sale', 'sale.appointment_id', '=', 'a.id')
                            ->where('a.id', $apptId)
                            ->whereNull('sale.id')
                            ->select('a.id', 'a.service_id', DB::raw('s.price as sell_price'), 'vt.vat_percent')
                            ->first();

                        if (!$appt) {
                            return response()->json(['error' => "Appointment item #{$idx} is invalid or already sold."], 422);
                        }

                        $appointmentId ??= $apptId;
                        if ($appointmentId !== $apptId) {
                            return response()->json(['error' => 'Only one appointment can be checked out in a single sale.'], 422);
                        }

                        $normalizedItems[] = [
                            'type' => 'appointment',
                            'appointment_id' => $apptId,
                            'service_id' => (int)$appt->service_id,
                            'quantity' => 1,
                            'unit_price' => (float)$appt->sell_price,
                            'vat_percent' => (float)$appt->vat_percent,
                        ];
                        continue;
                    }

                    if ($type === 'product') {
                        $productId = (int)($it['id'] ?? 0);
                        $product = DB::table('products as p')
                            ->join('vat_types as vt', 'p.sell_vat_type_id', '=', 'vt.id')
                            ->where('p.id', $productId)
                            ->select('p.id', 'p.sell_price', 'p.quantity_stock', 'vt.vat_percent')
                            ->lockForUpdate()
                            ->first();

                        if (!$product) {
                            return response()->json(['error' => "Product item #{$idx} is invalid."], 422);
                        }

                        if (isset($product->quantity_stock) && (float)$product->quantity_stock < $qty) {
                            return response()->json(['error' => "Product #{$productId} does not have enough stock."], 422);
                        }

                        $normalizedItems[] = [
                            'type' => 'product',
                            'product_id' => $productId,
                            'quantity' => $qty,
                            'unit_price' => (float)$product->sell_price,
                            'vat_percent' => (float)$product->vat_percent,
                        ];
                        continue;
                    }

                    return response()->json(['error' => "Cart item #{$idx} has unsupported type."], 422);
                }

                if (empty($normalizedItems)) {
                    return response()->json(['error' => 'Cart is empty.'], 422);
                }

                $servicesSub = 0.0; $productsSub = 0.0;
                $servicesVat = 0.0; $productsVat = 0.0;

                foreach ($normalizedItems as $item) {
                    $unitInc = (float)$item['unit_price'];
                    $vatPct  = ((float)$item['vat_percent']) / 100.0;
                    $qty     = (int)$item['quantity'];

                    $netPerUnit = $unitInc / (1.0 + $vatPct);
                    $taxPerUnit = $unitInc - $netPerUnit;

                    if ($item['type'] === 'service' || $item['type'] === 'appointment') {
                        $servicesSub += $netPerUnit * $qty;
                        $servicesVat += $taxPerUnit * $qty;
                    } else {
                        $productsSub += $netPerUnit * $qty;
                        $productsVat += $taxPerUnit * $qty;
                    }
                }

                $totalVat = $servicesVat + $productsVat;
                $grand    = round($servicesSub + $productsSub + $totalVat, 2);

                if ($paid + 0.00001 < $grand) {
                    return response()->json(['error' => 'Amount paid is less than grand total.'], 422);
                }

                $saleRow = [
                    'appointment_id'    => $appointmentId,
                    'client_id'         => $clientId,
                    'services_subtotal' => round($servicesSub, 2),
                    'services_vat'      => round($servicesVat, 2),
                    'products_subtotal' => round($productsSub, 2),
                    'products_vat'      => round($productsVat, 2),
                    'total_vat'         => round($totalVat, 2),
                    'grand_total'       => $grand,
                ];

                if (Schema::hasColumn('sales', 'sale_date')) {
                    $saleRow['sale_date'] = now();
                }
                if (Schema::hasColumn('sales', 'created_at')) $saleRow['created_at'] = now();
                if (Schema::hasColumn('sales', 'updated_at')) $saleRow['updated_at'] = now();

                $saleId = DB::table('sales')->insertGetId($saleRow);

                $ssHasCreated = Schema::hasColumn('sale_services', 'created_at');
                $ssHasUpdated = Schema::hasColumn('sale_services', 'updated_at');
                $spHasCreated = Schema::hasColumn('sale_products', 'created_at');
                $spHasUpdated = Schema::hasColumn('sale_products', 'updated_at');

                foreach ($normalizedItems as $item) {
                    $qty       = (int)$item['quantity'];
                    $unitInc   = (float)$item['unit_price'];
                    $lineTotal = round($unitInc * $qty, 2);

                    if ($item['type'] === 'service') {
                        $row = [
                            'sale_id'    => $saleId,
                            'service_id' => $item['service_id'],
                            'staff_id'   => $staffId,
                            'quantity'   => $qty,
                            'unit_price' => $unitInc,
                            'line_total' => $lineTotal,
                        ];
                        if ($ssHasCreated) $row['created_at'] = now();
                        if ($ssHasUpdated) $row['updated_at'] = now();
                        DB::table('sale_services')->insert($row);
                        continue;
                    }

                    if ($item['type'] === 'appointment') {
                        $row = [
                            'sale_id'    => $saleId,
                            'service_id' => $item['service_id'],
                            'staff_id'   => $staffId,
                            'quantity'   => 1,
                            'unit_price' => $unitInc,
                            'line_total' => round($unitInc, 2),
                        ];
                        if ($ssHasCreated) $row['created_at'] = now();
                        if ($ssHasUpdated) $row['updated_at'] = now();
                        DB::table('sale_services')->insert($row);
                        continue;
                    }

                    $row = [
                        'sale_id'    => $saleId,
                        'product_id' => $item['product_id'],
                        'staff_id'   => $staffId,
                        'quantity'   => $qty,
                        'unit_price' => $unitInc,
                        'line_total' => $lineTotal,
                    ];
                    if ($spHasCreated) $row['created_at'] = now();
                    if ($spHasUpdated) $row['updated_at'] = now();
                    DB::table('sale_products')->insert($row);

                    if (Schema::hasColumn('products', 'quantity_stock')) {
                        $upd = [
                            'quantity_stock' => DB::raw('quantity_stock - ' . $qty),
                        ];
                        if (Schema::hasColumn('products', 'updated_at')) {
                            $upd['updated_at'] = now();
                        }
                        DB::table('products')->where('id', $item['product_id'])->update($upd);
                    }
                }

                DB::table('sale_payments')->insert([
                    'sale_id'           => $saleId,
                    'payment_method_id' => $methodId,
                    'amount'            => $paid,
                    'created_at'        => now(),
                    'updated_at'        => now(),
                ]);

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

    public function receipt($saleId)
    {
        $saleId = (int) $saleId;

        $sale = DB::table('sales as s')
            ->leftJoin('clients as mc', 'mc.id', '=', 's.client_id')
            ->leftJoin('appointments as a', 'a.id', '=', 's.appointment_id')
            ->leftJoin('clients as ac', 'ac.id', '=', 'a.client_id')
            ->leftJoin('services as sv', 'sv.id', '=', 'a.service_id')
            ->select([
                's.*',
                's.client_id as manual_client_id',
                'mc.first_name as manual_first',
                'mc.last_name as manual_last',
                'mc.mobile as manual_mobile',
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

        $payments = DB::table('sale_payments as sp')
            ->leftJoin('payment_methods as pm', 'pm.id', '=', 'sp.payment_method_id')
            ->where('sp.sale_id', $saleId)
            ->select([
                'sp.*',
                DB::raw("COALESCE(pm.name,'') as method_name"),
            ])
            ->get();

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
