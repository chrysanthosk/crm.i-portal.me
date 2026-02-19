<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class PosSalesController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string)$request->query('search', ''));
        $from   = trim((string)$request->query('from', ''));
        $to     = trim((string)$request->query('to', ''));
        $pmId   = $request->query('payment_method_id');

        // NEW: show voided toggle
        $showVoided = (string)$request->query('show_voided', '0') === '1';

        // Limit options (like your old page)
        $limitRaw = (string)$request->query('limit', '50');
        $allowedLimits = ['50','100','200','300','all'];
        if (!in_array($limitRaw, $allowedLimits, true)) $limitRaw = '50';

        $perPage = $limitRaw === 'all' ? 5000 : (int)$limitRaw; // "all" but still safe

        // Schema-safe date columns
        $hasSaleDate   = Schema::hasColumn('sales', 'sale_date');
        $hasCreatedAt  = Schema::hasColumn('sales', 'created_at');

        // VOID columns (schema-safe)
        $hasVoidedAt   = Schema::hasColumn('sales', 'voided_at');
        $hasVoidedBy   = Schema::hasColumn('sales', 'voided_by');
        $hasVoidReason = Schema::hasColumn('sales', 'void_reason');

        // Payment methods for filter dropdown
        $paymentMethods = DB::table('payment_methods')->select('id', 'name')->orderBy('name')->get();

        // Build select list safely (do NOT select non-existent columns)
        $select = [
            's.id',
            's.grand_total',
            's.total_vat',
            's.appointment_id',
            's.client_id',

            // manual client
            DB::raw("mc.first_name as manual_first"),
            DB::raw("mc.last_name as manual_last"),

            // appointment client + fallback
            DB::raw("ac.first_name as appt_first"),
            DB::raw("ac.last_name as appt_last"),
            DB::raw("a.client_name as appt_client_fallback"),
        ];

        if ($hasSaleDate)  $select[] = 's.sale_date';
        if ($hasCreatedAt) $select[] = 's.created_at';

        if ($hasVoidedAt)   $select[] = 's.voided_at';
        if ($hasVoidedBy)   $select[] = 's.voided_by';
        if ($hasVoidReason) $select[] = 's.void_reason';

        // Base query: sales + both client sources
        $q = DB::table('sales as s')
            ->leftJoin('clients as mc', 'mc.id', '=', 's.client_id')
            ->leftJoin('appointments as a', 'a.id', '=', 's.appointment_id')
            ->leftJoin('clients as ac', 'ac.id', '=', 'a.client_id')
            ->select($select);

        // Default: hide voided (if column exists)
        if ($hasVoidedAt && !$showVoided) {
            $q->whereNull('s.voided_at');
        }

        // Search by client name (manual or appointment)
        if ($search !== '') {
            $q->where(function ($w) use ($search) {
                $like = '%' . $search . '%';
                $w->whereRaw("TRIM(COALESCE(mc.first_name,'') || ' ' || COALESCE(mc.last_name,'')) LIKE ?", [$like])
                  ->orWhereRaw("TRIM(COALESCE(ac.first_name,'') || ' ' || COALESCE(ac.last_name,'')) LIKE ?", [$like])
                  ->orWhere('a.client_name', 'like', $like);
            });
        }

        // Date filters (sale_date preferred, else created_at)
        if ($from !== '') {
            try {
                $fromDt = Carbon::parse($from)->startOfDay();
                if ($hasSaleDate) {
                    $q->where('s.sale_date', '>=', $fromDt);
                } elseif ($hasCreatedAt) {
                    $q->where('s.created_at', '>=', $fromDt);
                }
            } catch (Throwable $e) {}
        }

        if ($to !== '') {
            try {
                $toDt = Carbon::parse($to)->endOfDay();
                if ($hasSaleDate) {
                    $q->where('s.sale_date', '<=', $toDt);
                } elseif ($hasCreatedAt) {
                    $q->where('s.created_at', '<=', $toDt);
                }
            } catch (Throwable $e) {}
        }

        // Filter by payment method (optional)
        if ($pmId !== null && $pmId !== '') {
            $q->whereExists(function ($sub) use ($pmId) {
                $sub->select(DB::raw(1))
                    ->from('sale_payments as sp')
                    ->whereRaw('sp.sale_id = s.id')
                    ->where('sp.payment_method_id', (int)$pmId);
            });
        }

        // Sort newest first
        if ($hasSaleDate) {
            $q->orderByDesc('s.sale_date');
        } elseif ($hasCreatedAt) {
            $q->orderByDesc('s.created_at');
        } else {
            $q->orderByDesc('s.id');
        }

        $sales = $q->paginate($perPage)->appends($request->query());

        // Load lines/payments in bulk
        $saleIds = $sales->getCollection()->pluck('id')->values()->all();

        $serviceLinesBySale = collect();
        $productLinesBySale = collect();
        $paymentsBySale     = collect();

        if (!empty($saleIds)) {
            $serviceLinesBySale = DB::table('sale_services as ss')
                ->join('services as sv', 'sv.id', '=', 'ss.service_id')
                ->whereIn('ss.sale_id', $saleIds)
                ->select('ss.sale_id', 'sv.name', 'ss.quantity', 'ss.unit_price', 'ss.line_total')
                ->orderBy('sv.name')
                ->get()
                ->groupBy('sale_id');

            $productLinesBySale = DB::table('sale_products as sp')
                ->join('products as p', 'p.id', '=', 'sp.product_id')
                ->whereIn('sp.sale_id', $saleIds)
                ->select('sp.sale_id', 'p.name', 'sp.quantity', 'sp.unit_price', 'sp.line_total')
                ->orderBy('p.name')
                ->get()
                ->groupBy('sale_id');

            $paymentsBySale = DB::table('sale_payments as pay')
                ->leftJoin('payment_methods as pm', 'pm.id', '=', 'pay.payment_method_id')
                ->whereIn('pay.sale_id', $saleIds)
                ->select('pay.sale_id', 'pay.amount', DB::raw("COALESCE(pm.name,'') as method_name"))
                ->orderBy('pay.sale_id')
                ->get()
                ->groupBy('sale_id');
        }

        // Prepare computed fields for the view
        $sales->getCollection()->transform(function ($s) use ($hasSaleDate, $hasCreatedAt, $hasVoidedAt) {
            $manualName = trim(($s->manual_first ?? '') . ' ' . ($s->manual_last ?? ''));
            $apptName   = trim(($s->appt_first ?? '') . ' ' . ($s->appt_last ?? ''));
            $fallback   = (string)($s->appt_client_fallback ?? '');

            $s->client_name = $manualName ?: ($apptName ?: ($fallback ?: 'Walk-in'));

            // Date to display
            $date = null;
            if ($hasSaleDate && !empty($s->sale_date)) {
                $date = $s->sale_date;
            } elseif ($hasCreatedAt && !empty($s->created_at)) {
                $date = $s->created_at;
            }
            $s->display_date = $date ? Carbon::parse($date) : now();

            // Void status
            $s->is_voided = ($hasVoidedAt && !empty($s->voided_at));

            return $s;
        });

        return view('pos.sales', [
            'sales'              => $sales,
            'paymentMethods'     => $paymentMethods,
            'serviceLinesBySale' => $serviceLinesBySale,
            'productLinesBySale' => $productLinesBySale,
            'paymentsBySale'     => $paymentsBySale,

            // filters
            'search'      => $search,
            'from'        => $from,
            'to'          => $to,
            'pmId'        => $pmId,
            'limit'       => $limitRaw,
            'showVoided'  => $showVoided ? '1' : '0',
        ]);
    }

    /**
     * VOID a sale (no delete, keeps numbering & history)
     */
    public function void(Request $request, $sale)
    {
        $saleId = (int)$sale;

        // If columns not present, fail clearly (prevents silent break)
        if (!Schema::hasColumn('sales', 'voided_at')) {
            return redirect()
                ->route('pos.sales.index')
                ->with('error', 'VOID is not available (missing sales.voided_at). Run migrations.');
        }

        $data = $request->validate([
            'void_reason' => ['nullable', 'string', 'max:255'],
        ]);

        $reason = trim((string)($data['void_reason'] ?? ''));
        if ($reason === '') $reason = 'Voided by user';

        try {
            DB::transaction(function () use ($saleId, $reason) {
                // Don’t void twice
                $saleRow = DB::table('sales')->where('id', $saleId)->first();
                if (!$saleRow) {
                    throw new \RuntimeException("Sale not found.");
                }
                if (!empty($saleRow->voided_at)) {
                    return; // already voided
                }

                $update = [
                    'voided_at'   => now(),
                    'void_reason' => Schema::hasColumn('sales', 'void_reason') ? $reason : null,
                ];

                if (Schema::hasColumn('sales', 'voided_by')) {
                    $update['voided_by'] = auth()->id();
                }
                if (Schema::hasColumn('sales', 'updated_at')) {
                    $update['updated_at'] = now();
                }

                // Remove null keys if columns don’t exist
                $update = array_filter($update, fn($v) => $v !== null);

                DB::table('sales')->where('id', $saleId)->update($update);
            });

            return redirect()
                ->route('pos.sales.index')
                ->with('success', "Sale #{$saleId} voided successfully.");
        } catch (Throwable $e) {
            report($e);

            return redirect()
                ->route('pos.sales.index')
                ->with('error', 'VOID failed: ' . $e->getMessage());
        }
    }

    /**
     * Optional: keep hard delete (not recommended). Remove UI button.
     */
    public function destroy($sale)
    {
        $saleId = (int)$sale;

        try {
            DB::transaction(function () use ($saleId) {
                DB::table('sale_payments')->where('sale_id', $saleId)->delete();
                DB::table('sale_products')->where('sale_id', $saleId)->delete();
                DB::table('sale_services')->where('sale_id', $saleId)->delete();
                DB::table('sales')->where('id', $saleId)->delete();
            });

            return redirect()
                ->route('pos.sales.index')
                ->with('success', 'Sale deleted successfully.');
        } catch (Throwable $e) {
            report($e);

            return redirect()
                ->route('pos.sales.index')
                ->with('error', 'Delete failed: ' . $e->getMessage());
        }
    }
}
