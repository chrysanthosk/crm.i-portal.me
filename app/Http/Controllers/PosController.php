<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\ClientLoyalty;
use App\Models\DashboardSetting;
use App\Models\LoyaltyTier;
use App\Models\PaymentMethod;
use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
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

        // Today's appointments that have not yet been checked out
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
                DB::raw("COALESCE(TRIM(CONCAT(COALESCE(c.first_name,''), ' ', COALESCE(c.last_name,''))), a.client_name) as client_name"),
                DB::raw("sv.name as service_name"),
                DB::raw("sv.price as sell_price"),
                'vt.vat_percent',
            ])
            ->orderBy('a.start_at')
            ->get()
            ->map(function ($row) {
                $start = $row->start_at ? Carbon::parse($row->start_at) : null;
                $row->appointment_date = $start ? $start->format('Y-m-d') : '';
                $row->start_time       = $start ? $start->format('H:i') : '';
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

        $payments = PaymentMethod::orderBy('name')->get(['id', 'name']);

        $loyaltyRate = (int) (DB::table('loyalty_settings')
            ->where('key', 'points_per_euro')
            ->value('value') ?? 0);

        return view('pos.index', [
            'products'     => $products,
            'services'     => $services,
            'appointments' => $appointments,
            'clients'      => $clients,
            'staff'        => $staff,
            'payments'     => $payments,
            'loyaltyRate'  => $loyaltyRate,
        ]);
    }

    public function clientLoyalty(Client $client)
    {
        $loyalty      = ClientLoyalty::where('client_id', $client->id)->first();
        $points       = $loyalty?->points_balance ?? 0;
        $currentTier  = LoyaltyTier::forPoints($points);
        $nextTier     = LoyaltyTier::nextTierForPoints($points);
        $pointsToNext = $nextTier ? max(0, $nextTier->points_min - $points) : null;

        $progress = null;
        if ($nextTier && $currentTier) {
            $span = $nextTier->points_min - $currentTier->points_min;
            $progress = $span > 0 ? min(100, round(($points - $currentTier->points_min) / $span * 100)) : 100;
        } elseif ($nextTier) {
            $progress = $nextTier->points_min > 0 ? min(100, round($points / $nextTier->points_min * 100)) : 0;
        }

        return response()->json([
            'points'          => $points,
            'current_tier'    => $currentTier?->name,
            'next_tier'       => $nextTier?->name,
            'next_tier_min'   => $nextTier?->points_min,
            'points_to_next'  => $pointsToNext,
            'progress'        => $progress,
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
                $appointmentId   = null;

                // ---- Validate & normalise cart items ----
                foreach ($items as $idx => $it) {
                    $type = (string) ($it['type'] ?? '');
                    $qty  = max(1, (int) ($it['qty'] ?? 1));

                    if ($type === 'service') {
                        $service = DB::table('services as s')
                            ->join('vat_types as vt', 's.vat_type_id', '=', 'vt.id')
                            ->where('s.id', (int) ($it['id'] ?? 0))
                            ->select('s.id', DB::raw('s.price as sell_price'), 'vt.vat_percent')
                            ->first();

                        if (!$service) {
                            return response()->json(['error' => "Service item #{$idx} is invalid."], 422);
                        }

                        $normalizedItems[] = [
                            'type'        => 'service',
                            'service_id'  => (int) $service->id,
                            'quantity'    => $qty,
                            'unit_price'  => (float) $service->sell_price,
                            'vat_percent' => (float) $service->vat_percent,
                        ];
                        continue;
                    }

                    if ($type === 'appointment') {
                        $apptId = (int) ($it['appt_id'] ?? 0);
                        $appt   = DB::table('appointments as a')
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
                            'type'           => 'appointment',
                            'appointment_id' => $apptId,
                            'service_id'     => (int) $appt->service_id,
                            'quantity'       => 1,
                            'unit_price'     => (float) $appt->sell_price,
                            'vat_percent'    => (float) $appt->vat_percent,
                        ];
                        continue;
                    }

                    if ($type === 'product') {
                        $productId = (int) ($it['id'] ?? 0);
                        $product   = DB::table('products as p')
                            ->join('vat_types as vt', 'p.sell_vat_type_id', '=', 'vt.id')
                            ->where('p.id', $productId)
                            ->select('p.id', 'p.sell_price', 'p.quantity_stock', 'vt.vat_percent')
                            ->lockForUpdate()
                            ->first();

                        if (!$product) {
                            return response()->json(['error' => "Product item #{$idx} is invalid."], 422);
                        }

                        if (isset($product->quantity_stock) && (float) $product->quantity_stock < $qty) {
                            return response()->json(['error' => "Product #{$productId} does not have enough stock."], 422);
                        }

                        $normalizedItems[] = [
                            'type'        => 'product',
                            'product_id'  => $productId,
                            'quantity'    => $qty,
                            'unit_price'  => (float) $product->sell_price,
                            'vat_percent' => (float) $product->vat_percent,
                        ];
                        continue;
                    }

                    return response()->json(['error' => "Cart item #{$idx} has unsupported type."], 422);
                }

                if (empty($normalizedItems)) {
                    return response()->json(['error' => 'Cart is empty.'], 422);
                }

                // ---- Calculate totals ----
                $servicesSub = 0.0;
                $productsSub = 0.0;
                $servicesVat = 0.0;
                $productsVat = 0.0;

                foreach ($normalizedItems as $item) {
                    $unitInc    = (float) $item['unit_price'];
                    $vatPct     = (float) $item['vat_percent'] / 100.0;
                    $qty        = (int) $item['quantity'];
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

                // ---- Persist sale header ----
                $sale = Sale::create([
                    'appointment_id'    => $appointmentId,
                    'client_id'         => $clientId,
                    'services_subtotal' => round($servicesSub, 2),
                    'services_vat'      => round($servicesVat, 2),
                    'products_subtotal' => round($productsSub, 2),
                    'products_vat'      => round($productsVat, 2),
                    'total_vat'         => round($totalVat, 2),
                    'grand_total'       => $grand,
                ]);

                // ---- Persist line items ----
                foreach ($normalizedItems as $item) {
                    $qty       = (int) $item['quantity'];
                    $unitInc   = (float) $item['unit_price'];
                    $lineTotal = round($unitInc * $qty, 2);

                    if ($item['type'] === 'service' || $item['type'] === 'appointment') {
                        $sale->saleServices()->create([
                            'service_id' => $item['service_id'],
                            'staff_id'   => $staffId,
                            'quantity'   => $qty,
                            'unit_price' => $unitInc,
                            'line_total' => $lineTotal,
                        ]);
                        continue;
                    }

                    // Product line
                    $sale->saleProducts()->create([
                        'product_id' => $item['product_id'],
                        'staff_id'   => $staffId,
                        'quantity'   => $qty,
                        'unit_price' => $unitInc,
                        'line_total' => $lineTotal,
                    ]);

                    // Decrement stock
                    DB::table('products')
                        ->where('id', $item['product_id'])
                        ->update(['quantity_stock' => DB::raw('quantity_stock - ' . $qty)]);
                }

                // ---- Persist payment ----
                $sale->salePayments()->create([
                    'payment_method_id' => $methodId,
                    'amount'            => $paid,
                ]);

                // ---- Award loyalty points (if tables exist) ----
                $this->awardLoyaltyPoints($sale, $grand, $appointmentId, $clientId);

                return response()->json([
                    'sale_id'     => $sale->id,
                    'grand_total' => round($grand, 2),
                    'receipt_url' => route('pos.receipt', ['sale' => $sale->id]),
                ]);
            });
        } catch (Throwable $e) {
            report($e);
            return response()->json(['error' => 'Save failed: ' . $e->getMessage()], 500);
        }
    }

    public function receipt(int $saleId)
    {
        $sale = Sale::with([
            'client',
            'appointment.client',
            'appointment.service',
            'saleServices.service',
            'saleProducts.product',
            'salePayments.paymentMethod',
        ])->findOrFail($saleId);

        $saleDate = $sale->created_at ?? now();

        $clientName   = $sale->client_name;
        $clientMobile = $sale->client_mobile;
        $isAppt       = (bool) $sale->appointment_id;
        $apptTime     = $isAppt ? $sale->appointment?->start_at : null;
        $apptService  = $sale->appointment?->service?->name ?? '';

        $ds = DashboardSetting::query()->first();
        $company = [
            'company_name'         => $ds->company_name ?? ($ds->dashboard_name ?? config('app.name')),
            'company_vat_number'   => $ds->company_vat_number ?? '',
            'company_phone_number' => $ds->company_phone_number ?? '',
            'company_address'      => $ds->company_address ?? '',
        ];

        $grandTotal = (float) $sale->grand_total;
        $amountPaid = (float) $sale->salePayments->sum('amount');
        $changeDue  = $amountPaid - $grandTotal;

        return view('pos.receipt', [
            'sale_id'      => $saleId,
            'sale'         => $sale,
            'saleDate'     => $saleDate,
            'clientName'   => $clientName,
            'clientMobile' => $clientMobile,
            'isAppt'       => $isAppt,
            'apptTime'     => $apptTime,
            'apptService'  => $apptService,
            'serviceLines' => $sale->saleServices,
            'productLines' => $sale->saleProducts,
            'payments'     => $sale->salePayments,
            'company'      => $company,
            'amountPaid'   => $amountPaid,
            'changeDue'    => $changeDue,
        ]);
    }

    // ---------- Private helpers ----------

    private function awardLoyaltyPoints(Sale $sale, float $grand, ?int $appointmentId, ?int $clientId): void
    {
        if (
            !DB::getSchemaBuilder()->hasTable('loyalty_settings') ||
            !DB::getSchemaBuilder()->hasTable('loyalty_transactions') ||
            !DB::getSchemaBuilder()->hasTable('client_loyalty')
        ) {
            return;
        }

        $rate   = (int) (DB::table('loyalty_settings')->where('key', 'points_per_euro')->value('value') ?? 0);
        $points = ($rate > 0) ? (int) floor($grand * $rate) : 0;

        $awardClient = null;
        if ($appointmentId) {
            $awardClient = (int) (DB::table('appointments')->where('id', $appointmentId)->value('client_id') ?? 0);
            if ($awardClient <= 0) {
                $awardClient = null;
            }
        } elseif ($clientId) {
            $awardClient = $clientId;
        }

        if (!$awardClient || $points <= 0) {
            return;
        }

        DB::table('loyalty_transactions')->insert([
            'client_id'    => $awardClient,
            'change'       => $points,
            'reason'       => 'purchase',
            'reference_id' => $sale->id,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        $existing = DB::table('client_loyalty')->where('client_id', $awardClient)->first();
        if ($existing) {
            DB::table('client_loyalty')
                ->where('client_id', $awardClient)
                ->update([
                    'points_balance' => (int) $existing->points_balance + $points,
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
