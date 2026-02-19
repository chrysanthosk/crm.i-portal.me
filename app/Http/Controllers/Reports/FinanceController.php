<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\Income;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class FinanceController extends Controller
{
    public function __construct()
    {
        // ✅ Apply permission once for all endpoints in this controller
        $this->middleware('permission:reporting.view');
    }

    private function parseMonth(?string $m): string
    {
        // expects YYYY-MM, fallback current month
        if ($m && preg_match('/^\d{4}-\d{2}$/', $m)) {
            return $m;
        }
        return now()->format('Y-m');
    }

    private function monthRange(string $month): array
    {
        $start = Carbon::createFromFormat('Y-m-d', $month . '-01')->startOfDay();
        $end = $start->copy()->endOfMonth()->endOfDay();
        return [$start, $end];
    }

    /*
     * EXPENSES
     */
    public function expenses(Request $request)
    {
        $month = $this->parseMonth($request->query('m'));
        [$start, $end] = $this->monthRange($month);

        $expenses = Expense::query()
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->orderBy('date')
            ->orderBy('id')
            ->get();

        $allNames = Expense::query()
            ->where('name', '<>', '')
            ->distinct()
            ->orderBy('name')
            ->pluck('name');

        return view('reports.expenses.index', [
            'month' => $month,
            'expenses' => $expenses,
            'allNames' => $allNames,
        ]);
    }

    public function expensesSave(Request $request)
    {
        $month = $this->parseMonth($request->query('m'));
        [$start, $end] = $this->monthRange($month);

        $rows = $request->json()->all();
        if (!is_array($rows)) {
            return response()->json(['message' => 'Invalid payload'], 422);
        }

        $errors = [];

        DB::transaction(function () use ($rows, $start, $end, &$errors) {
            foreach ($rows as $i => $row) {
                $v = Validator::make($row, [
                    'id' => ['nullable', 'integer'],
                    'date' => ['required', 'date'],
                    'name' => ['required', 'string', 'max:255'],
                    'amount_paid' => ['required', 'numeric', 'min:0'],
                    'payment_type' => ['nullable', 'string', 'max:100'],
                    'cheque_no' => ['nullable', 'string', 'max:100'],
                    'invoice_reason' => ['nullable', 'string'],
                ]);

                if ($v->fails()) {
                    $errors[$i] = $v->errors()->toArray();
                    continue;
                }

                $date = Carbon::parse($row['date'])->toDateString();
                if ($date < $start->toDateString() || $date > $end->toDateString()) {
                    $errors[$i]['date'] = ['Date must be within selected month.'];
                    continue;
                }

                $data = [
                    'date' => $date,
                    'name' => trim((string)($row['name'] ?? '')),
                    'amount_paid' => round((float)$row['amount_paid'], 2),
                    'payment_type' => isset($row['payment_type']) ? trim((string)$row['payment_type']) : null,
                    'cheque_no' => isset($row['cheque_no']) ? trim((string)$row['cheque_no']) : null,
                    'invoice_reason' => isset($row['invoice_reason']) ? trim((string)$row['invoice_reason']) : null,
                ];

                $id = $row['id'] ?? null;
                if ($id) {
                    Expense::query()->whereKey($id)->update($data);
                } else {
                    // only create if meaningful
                    if ($data['name'] !== '' || $data['amount_paid'] > 0) {
                        Expense::create($data);
                    }
                }
            }
        });

        if (!empty($errors)) {
            return response()->json(['message' => 'Validation failed', 'errors' => $errors], 422);
        }

        return response()->json(['ok' => true]);
    }

    public function expensesImport(Request $request)
    {
        $month = $this->parseMonth($request->query('m'));
        [$start, $end] = $this->monthRange($month);

        $rows = $request->json()->all();
        if (!is_array($rows)) {
            return response()->json(['message' => 'Invalid payload'], 422);
        }

        $errors = [];

        DB::transaction(function () use ($rows, $start, $end, &$errors) {
            foreach ($rows as $i => $row) {
                $v = Validator::make($row, [
                    'date' => ['required', 'date'],
                    'name' => ['required', 'string', 'max:255'],
                    'amount_paid' => ['required', 'numeric'],
                    'payment_type' => ['nullable', 'string', 'max:100'],
                    'cheque_no' => ['nullable', 'string', 'max:100'],
                    'invoice_reason' => ['nullable', 'string'],
                ]);

                if ($v->fails()) {
                    $errors[$i] = $v->errors()->toArray();
                    continue;
                }

                $date = Carbon::parse($row['date'])->toDateString();
                if ($date < $start->toDateString() || $date > $end->toDateString()) {
                    $errors[$i]['date'] = ['Date must be within selected month.'];
                    continue;
                }

                Expense::create([
                    'date' => $date,
                    'name' => trim((string)($row['name'] ?? '')),
                    'amount_paid' => round((float)$row['amount_paid'], 2),
                    'payment_type' => isset($row['payment_type']) ? trim((string)$row['payment_type']) : null,
                    'cheque_no' => isset($row['cheque_no']) ? trim((string)$row['cheque_no']) : null,
                    'invoice_reason' => isset($row['invoice_reason']) ? trim((string)$row['invoice_reason']) : null,
                ]);
            }
        });

        if (!empty($errors)) {
            return response()->json(['message' => 'Import validation failed', 'errors' => $errors], 422);
        }

        return response()->json(['ok' => true]);
    }

    /*
     * INCOME
     */
    public function income(Request $request)
    {
        $month = $this->parseMonth($request->query('m'));
        [$start, $end] = $this->monthRange($month);

        $existing = Income::query()
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->keyBy(fn($r) => Carbon::parse($r->date)->toDateString());

        $dates = [];
        $cursor = $start->copy()->startOfDay();
        while ($cursor->lte($end)) {
            $dates[] = $cursor->toDateString();
            $cursor->addDay();
        }

        return view('reports.income.index', [
            'month' => $month,
            'dates' => $dates,
            'rows' => $existing,
        ]);
    }

    public function incomeSave(Request $request)
    {
        $month = $this->parseMonth($request->query('m'));
        [$start, $end] = $this->monthRange($month);

        $rows = $request->json()->all();
        if (!is_array($rows)) {
            return response()->json(['message' => 'Invalid payload'], 422);
        }

        $errors = [];

        DB::transaction(function () use ($rows, $start, $end, &$errors) {
            foreach ($rows as $i => $row) {
                $v = Validator::make($row, [
                    'date' => ['required', 'date'],
                    'cash' => ['nullable', 'numeric'],
                    'revolut' => ['nullable', 'numeric'],
                    'visa' => ['nullable', 'numeric'],
                    'other' => ['nullable', 'numeric'],
                ]);

                if ($v->fails()) {
                    $errors[$i] = $v->errors()->toArray();
                    continue;
                }

                $date = Carbon::parse($row['date'])->toDateString();
                if ($date < $start->toDateString() || $date > $end->toDateString()) {
                    $errors[$i]['date'] = ['Date must be within selected month.'];
                    continue;
                }

                $data = [
                    'date' => $date,
                    'cash' => round((float)($row['cash'] ?? 0), 2),
                    'revolut' => round((float)($row['revolut'] ?? 0), 2),
                    'visa' => round((float)($row['visa'] ?? 0), 2),
                    'other' => round((float)($row['other'] ?? 0), 2),
                ];

                Income::query()->updateOrCreate(['date' => $date], $data);
            }
        });

        if (!empty($errors)) {
            return response()->json(['message' => 'Validation failed', 'errors' => $errors], 422);
        }

        return response()->json(['ok' => true]);
    }

    public function incomeImport(Request $request)
    {
        $month = $this->parseMonth($request->query('m'));
        [$start, $end] = $this->monthRange($month);

        $rows = $request->json()->all();
        if (!is_array($rows)) {
            return response()->json(['message' => 'Invalid payload'], 422);
        }

        $errors = [];

        DB::transaction(function () use ($rows, $start, $end, &$errors) {
            foreach ($rows as $i => $row) {
                $v = Validator::make($row, [
                    'date' => ['required', 'date'],
                    'cash' => ['nullable', 'numeric'],
                    'revolut' => ['nullable', 'numeric'],
                    'visa' => ['nullable', 'numeric'],
                    'other' => ['nullable', 'numeric'],
                ]);

                if ($v->fails()) {
                    $errors[$i] = $v->errors()->toArray();
                    continue;
                }

                $date = Carbon::parse($row['date'])->toDateString();
                if ($date < $start->toDateString() || $date > $end->toDateString()) {
                    $errors[$i]['date'] = ['Date must be within selected month.'];
                    continue;
                }

                $data = [
                    'date' => $date,
                    'cash' => round((float)($row['cash'] ?? 0), 2),
                    'revolut' => round((float)($row['revolut'] ?? 0), 2),
                    'visa' => round((float)($row['visa'] ?? 0), 2),
                    'other' => round((float)($row['other'] ?? 0), 2),
                ];

                Income::query()->updateOrCreate(['date' => $date], $data);
            }
        });

        if (!empty($errors)) {
            return response()->json(['message' => 'Import validation failed', 'errors' => $errors], 422);
        }

        return response()->json(['ok' => true]);
    }
}
