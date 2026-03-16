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
        $this->middleware('permission:reporting.view');
    }

    private function parseMonth(?string $m): string
    {
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

    private function validateExpenseRows(array $rows, Carbon $start, Carbon $end, bool $requireId = false): array
    {
        $errors = [];

        foreach ($rows as $i => $row) {
            $rules = [
                'id' => [$requireId ? 'required' : 'nullable', 'integer'],
                'date' => ['required', 'date'],
                'name' => ['required', 'string', 'max:255'],
                'amount_paid' => ['required', 'numeric', 'min:0'],
                'payment_type' => ['nullable', 'string', 'max:100'],
                'cheque_no' => ['nullable', 'string', 'max:100'],
                'invoice_reason' => ['nullable', 'string'],
            ];

            $v = Validator::make($row, $rules);

            if ($v->fails()) {
                $errors[$i] = $v->errors()->toArray();
                continue;
            }

            $date = Carbon::parse($row['date'])->toDateString();
            if ($date < $start->toDateString() || $date > $end->toDateString()) {
                $errors[$i]['date'] = ['Date must be within selected month.'];
            }
        }

        return $errors;
    }

    private function validateIncomeRows(array $rows, Carbon $start, Carbon $end): array
    {
        $errors = [];

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
            }
        }

        return $errors;
    }

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

        $errors = $this->validateExpenseRows($rows, $start, $end, false);
        if (!empty($errors)) {
            return response()->json(['message' => 'Validation failed', 'errors' => $errors], 422);
        }

        DB::transaction(function () use ($rows) {
            foreach ($rows as $row) {
                $date = Carbon::parse($row['date'])->toDateString();
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
                    if ($data['name'] !== '' || $data['amount_paid'] > 0) {
                        Expense::create($data);
                    }
                }
            }
        });

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

        $errors = $this->validateExpenseRows($rows, $start, $end, false);
        if (!empty($errors)) {
            return response()->json(['message' => 'Import validation failed', 'errors' => $errors], 422);
        }

        DB::transaction(function () use ($rows) {
            foreach ($rows as $row) {
                Expense::create([
                    'date' => Carbon::parse($row['date'])->toDateString(),
                    'name' => trim((string)($row['name'] ?? '')),
                    'amount_paid' => round((float)$row['amount_paid'], 2),
                    'payment_type' => isset($row['payment_type']) ? trim((string)$row['payment_type']) : null,
                    'cheque_no' => isset($row['cheque_no']) ? trim((string)$row['cheque_no']) : null,
                    'invoice_reason' => isset($row['invoice_reason']) ? trim((string)$row['invoice_reason']) : null,
                ]);
            }
        });

        return response()->json(['ok' => true]);
    }

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

        $errors = $this->validateIncomeRows($rows, $start, $end);
        if (!empty($errors)) {
            return response()->json(['message' => 'Validation failed', 'errors' => $errors], 422);
        }

        DB::transaction(function () use ($rows) {
            foreach ($rows as $row) {
                $date = Carbon::parse($row['date'])->toDateString();
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

        $errors = $this->validateIncomeRows($rows, $start, $end);
        if (!empty($errors)) {
            return response()->json(['message' => 'Import validation failed', 'errors' => $errors], 422);
        }

        DB::transaction(function () use ($rows) {
            foreach ($rows as $row) {
                $date = Carbon::parse($row['date'])->toDateString();
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

        return response()->json(['ok' => true]);
    }
}
