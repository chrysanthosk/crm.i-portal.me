<?php

namespace App\Http\Controllers;

use App\Support\Audit;
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

        $showVoided = (string)$request->query('show_voided', '0') === '1';

        $limitRaw = (string)$request->query('limit', '50');
        $allowedLimits = ['50','100','200','300','all'];
        if (!in_array($limitRaw, $allowedLimits, true)) $limitRaw = '50';

        $perPage = $limitRaw === 'all' ? 5000 : (int)$limitRaw;

        $hasSaleDate   = Schema::hasColumn('sales', 'sale_date');
        $hasCreatedAt  = Schema::hasColumn('sales', 'created_at');

        $hasVoidedAt   = Schema::hasColumn('sales', 'voided_at');
        $hasVoidedBy   = Schema::hasColumn('sales', 'voided_by');
        $hasVoidReason = Schema::hasColumn('sales', 'void_reason');

        $paymentMethods = DB::table('payment_methods')->select('id', 'name')->orderBy('name')->get();

        $select = [
            's.id',
            's.grand_total',
            's.total_vat',
            's.appointment_id',
            's.client_id',
            DB::raw("mc.first_name as manual_first"),
            DB::raw("mc.last_name as manual_last"),
            DB::raw("ac.first_name as appt_first"),
            DB::raw("ac.last_name as appt_last"),
            DB::raw("a.client_name as appt_client_fallback"),
        ];

        if ($hasSaleDate)  $select[] = 's.sale_date';
        if ($hasCreatedAt) $select[] = 's.created_at';

        if ($hasVoidedAt)   $select[] = 's.voided_at';
        if ($hasVoidedBy)   $select[] = 's.voided_by';
        if ($hasVoidReason) $select[] = 's.void_reason';

        $q = DB::table('sales as s')
            ->leftJoin('clients as mc', 'mc.id', '=', 's.client_id')
            ->leftJoin('appointments as a', 'a.id', '=', 's.appointment_id')
            ->leftJoin('clients as ac', 'ac.id', '=', 'a.client_id')
            ->select($select);

        if ($hasVoidedAt && !$showVoided) {
            $q->whereNull('s.voided_at');
        }

        if ($search !== '') {
            $q->where(function ($w) use ($search) {
                $like = '%' . $search . '%';
                $w->whereRaw("TRIM(COALESCE(mc.first_name,'') || ' ' || COALESCE(mc.last_name,'')) LIKE ?", [$like])
                  ->orWhereRaw("TRIM(COALESCE(ac.first_name,'') || ' ' || COALESCE(ac.last_name,'')) LIKE ?", [$like])
                  ->orWhere('a.client_name', 'like', $like);
            });
        }

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

        if ($pmId !== null && $pmId !== '') {
            $q->whereExists(function ($sub) use ($pmId) {
                $sub->select(DB::raw(1))
                    ->from('sale_payments as sp')
                    ->whereRaw('sp.sale_id = s.id')
                    ->where('sp.payment_method_id', (int)$pmId);
            });
        }

        if ($hasSaleDate) {
            $q->orderByDesc('s.sale_date');
        } elseif ($hasCreatedAt) {
            $q->orderByDesc('s.created_at');
        } else {
            $q->orderByDesc('s.id');
        }

        $sales = $q->paginate($perPage)->appends($request->query());

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

        $sales->getCollection()->transform(function ($s) use ($hasSaleDate, $hasCreatedAt, $hasVoidedAt) {
            $manualName = trim(($s->manual_first ?? '') . ' ' . ($s->manual_last ?? ''));
            $apptName   = trim(($s->appt_first ?? '') . ' ' . ($s->appt_last ?? ''));
            $fallback   = (string)($s->appt_client_fallback ?? '');

            $s->client_name = $manualName ?: ($apptName ?: ($fallback ?: 'Walk-in'));

            $date = null;
            if ($hasSaleDate && !empty($s->sale_date)) {
                $date = $s->sale_date;
            } elseif ($hasCreatedAt && !empty($s->created_at)) {
                $date = $s->created_at;
            }
            $s->display_date = $date ? Carbon::parse($date) : now();

            $s->is_voided = ($hasVoidedAt && !empty($s->voided_at));

            return $s;
        });

        return view('pos.sales', [
            'sales'              => $sales,
            'paymentMethods'     => $paymentMethods,
            'serviceLinesBySale' => $serviceLinesBySale,
            'productLinesBySale' => $productLinesBySale,
            'paymentsBySale'     => $paymentsBySale,
            'search'      => $search,
            'from'        => $from,
            'to'          => $to,
            'pmId'        => $pmId,
            'limit'       => $limitRaw,
            'showVoided'  => $showVoided ? '1' : '0',
        ]);
    }

    public function void(Request $request, $sale)
    {
        $saleId = (int)$sale;

        if (!Schema::hasColumn('sales', 'voided_at')) {
            return redirect()
                ->route('pos.sales.index')
                ->with('error', 'VOID is not available (missing sales.voided_at). Run migrations.');
        }

        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            DB::transaction(function () use ($saleId, $data, $request) {
                $sale = DB::table('sales')->lockForUpdate()->where('id', $saleId)->first();
                if (!$sale) {
                    abort(404, 'Sale not found.');
                }

                if (!empty($sale->voided_at)) {
                    return;
                }

                $update = [
                    'voided_at' => now(),
                ];

                if (Schema::hasColumn('sales', 'voided_by')) {
                    $update['voided_by'] = $request->user()?->id;
                }
                if (Schema::hasColumn('sales', 'void_reason')) {
                    $update['void_reason'] = trim((string)($data['reason'] ?? '')) ?: null;
                }
                if (Schema::hasColumn('sales', 'updated_at')) {
                    $update['updated_at'] = now();
                }

                DB::table('sales')->where('id', $saleId)->update($update);
                Audit::log('pos', 'sale.void', 'sale', $saleId, ['reason' => $update['void_reason'] ?? null]);
            });

            return redirect()
                ->route('pos.sales.index')
                ->with('success', "Sale #{$saleId} voided successfully.");
        } catch (Throwable $e) {
            report($e);
            return redirect()
                ->route('pos.sales.index')
                ->with('error', 'Could not void sale: ' . $e->getMessage());
        }
    }

    public function destroy($sale)
    {
        $saleId = (int) $sale;

        Audit::log('pos', 'sale.delete.blocked', 'sale', $saleId);

        return redirect()
            ->route('pos.sales.index')
            ->with('error', 'Hard deleting sales is disabled. Please use VOID to preserve accounting history.');
    }
}
