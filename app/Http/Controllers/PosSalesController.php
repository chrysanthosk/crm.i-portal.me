<?php

namespace App\Http\Controllers;

use App\Models\PaymentMethod;
use App\Models\Sale;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

class PosSalesController extends Controller
{
    private function driver(): string
    {
        return (string) DB::getDriverName();
    }

    private function personNameExpr(string $first, string $last): string
    {
        if ($this->driver() === 'sqlite') {
            return "TRIM(COALESCE({$first},'') || ' ' || COALESCE({$last},''))";
        }

        return "TRIM(CONCAT(COALESCE({$first},''), ' ', COALESCE({$last},'')))";
    }

    public function index(Request $request)
    {
        $search     = trim((string) $request->query('search', ''));
        $from       = trim((string) $request->query('from', ''));
        $to         = trim((string) $request->query('to', ''));
        $pmId       = $request->query('payment_method_id');
        $staffId    = (int) $request->query('staff_id', 0);
        $showVoided = (string) $request->query('show_voided', '0') === '1';

        $limitRaw      = (string) $request->query('limit', '50');
        $allowedLimits = ['50', '100', '200', '300', 'all'];
        if (!in_array($limitRaw, $allowedLimits, true)) {
            $limitRaw = '50';
        }
        $perPage = $limitRaw === 'all' ? 5000 : (int) $limitRaw;

        $paymentMethods = PaymentMethod::orderBy('name')->get(['id', 'name']);

        $staffOptions = DB::table('staff as st')
            ->leftJoin('users as u', 'u.id', '=', 'st.user_id')
            ->select('st.id', DB::raw("COALESCE(u.name, CONCAT('Staff #', st.id)) as name"))
            ->orderBy('name')
            ->get();

        // Base query — keep joins for client-name search/display, use model scopes for filtering
        $q = Sale::query()
            ->leftJoin('clients as mc', 'mc.id', '=', 'sales.client_id')
            ->leftJoin('appointments as a', 'a.id', '=', 'sales.appointment_id')
            ->leftJoin('clients as ac', 'ac.id', '=', 'a.client_id')
            ->select([
                'sales.id',
                'sales.grand_total',
                'sales.total_vat',
                'sales.appointment_id',
                'sales.client_id',
                'sales.created_at',
                'sales.voided_at',
                'sales.voided_by',
                'sales.void_reason',
                DB::raw('mc.first_name as manual_first'),
                DB::raw('mc.last_name  as manual_last'),
                DB::raw('ac.first_name as appt_first'),
                DB::raw('ac.last_name  as appt_last'),
                DB::raw('a.client_name as appt_client_fallback'),
            ]);

        if (!$showVoided) {
            $q->notVoided();
        }

        if ($search !== '') {
            $manualExpr = $this->personNameExpr('mc.first_name', 'mc.last_name');
            $apptExpr   = $this->personNameExpr('ac.first_name', 'ac.last_name');
            $like       = '%' . $search . '%';

            $q->where(function ($w) use ($search, $manualExpr, $apptExpr, $like) {
                if (ctype_digit($search)) {
                    $w->orWhere('sales.id', (int) $search);
                }
                $w->orWhereRaw("{$manualExpr} LIKE ?", [$like])
                  ->orWhereRaw("{$apptExpr} LIKE ?", [$like])
                  ->orWhere('a.client_name', 'like', $like);
            });
        }

        if ($from !== '') {
            try {
                $q->where('sales.created_at', '>=', Carbon::parse($from)->startOfDay());
            } catch (Throwable) {}
        }

        if ($to !== '') {
            try {
                $q->where('sales.created_at', '<=', Carbon::parse($to)->endOfDay());
            } catch (Throwable) {}
        }

        if ($pmId !== null && $pmId !== '') {
            $q->forPaymentMethod((int) $pmId);
        }

        if ($staffId > 0) {
            $q->forStaff($staffId);
        }

        $q->orderByDesc('sales.created_at');

        // Summary counts (before pagination)
        $summaryBase   = clone $q;
        $summaryCount  = (clone $summaryBase)->count('sales.id');
        $summaryGross  = (float) (clone $summaryBase)->sum('sales.grand_total');
        $summaryVoided = (clone $summaryBase)->voided()->count('sales.id');

        $sales = $q->paginate($perPage)->appends($request->query());

        // Eager-load line items and payments on the current page only
        $sales->getCollection()->loadMissing([
            'saleServices.service',
            'saleProducts.product',
            'salePayments.paymentMethod',
        ]);

        // Build lookup collections the view expects (keyed by sale_id)
        $serviceLinesBySale = $sales->getCollection()
            ->mapWithKeys(fn ($s) => [
                $s->id => $s->saleServices->map(fn ($ss) => (object) [
                    'sale_id'    => $s->id,
                    'name'       => $ss->service?->name ?? '',
                    'quantity'   => $ss->quantity,
                    'unit_price' => $ss->unit_price,
                    'line_total' => $ss->line_total,
                ]),
            ]);

        $productLinesBySale = $sales->getCollection()
            ->mapWithKeys(fn ($s) => [
                $s->id => $s->saleProducts->map(fn ($sp) => (object) [
                    'sale_id'    => $s->id,
                    'name'       => $sp->product?->name ?? '',
                    'quantity'   => $sp->quantity,
                    'unit_price' => $sp->unit_price,
                    'line_total' => $sp->line_total,
                ]),
            ]);

        $paymentsBySale = $sales->getCollection()
            ->mapWithKeys(fn ($s) => [
                $s->id => $s->salePayments->map(fn ($p) => (object) [
                    'sale_id'     => $s->id,
                    'amount'      => $p->amount,
                    'method_name' => $p->paymentMethod?->name ?? '',
                ]),
            ]);

        // Decorate each sale row for the view
        $sales->getCollection()->transform(function (Sale $s) {
            $manualName = trim(($s->manual_first ?? '') . ' ' . ($s->manual_last ?? ''));
            $apptName   = trim(($s->appt_first ?? '') . ' ' . ($s->appt_last ?? ''));
            $fallback   = (string) ($s->appt_client_fallback ?? '');

            $s->client_name   = $manualName ?: ($apptName ?: ($fallback ?: 'Walk-in'));
            $s->display_date  = $s->created_at ?? now();
            $s->is_voided     = $s->is_voided;
            $s->paid_amount   = $s->paid_amount;
            $s->balance_due   = $s->balance_due;
            $s->payment_status = $s->payment_status;

            return $s;
        });

        return view('pos.sales', [
            'sales'              => $sales,
            'paymentMethods'     => $paymentMethods,
            'staffOptions'       => $staffOptions,
            'serviceLinesBySale' => $serviceLinesBySale,
            'productLinesBySale' => $productLinesBySale,
            'paymentsBySale'     => $paymentsBySale,
            'summaryCount'       => $summaryCount,
            'summaryGross'       => $summaryGross,
            'summaryVoided'      => $summaryVoided,
            'search'             => $search,
            'from'               => $from,
            'to'                 => $to,
            'pmId'               => $pmId,
            'staffId'            => $staffId,
            'limit'              => $limitRaw,
            'showVoided'         => $showVoided ? '1' : '0',
        ]);
    }

    public function void(Request $request, $sale)
    {
        $saleId = (int) $sale;

        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            DB::transaction(function () use ($saleId, $data, $request) {
                $sale = Sale::lockForUpdate()->findOrFail($saleId);

                if ($sale->is_voided) {
                    return; // already voided — idempotent
                }

                $sale->update([
                    'voided_at'   => now(),
                    'voided_by'   => $request->user()?->id,
                    'void_reason' => trim((string) ($data['reason'] ?? '')) ?: null,
                ]);

                Audit::log('pos', 'sale.void', 'sale', $saleId, ['reason' => $sale->void_reason]);
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
