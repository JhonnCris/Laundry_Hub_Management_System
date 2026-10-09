<?php

namespace App\Http\Controllers;

use App\Models\BillStatement;
use App\Models\FinanceTransaction;
use App\Models\RecurringBill;
use App\Models\Staff;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Admin: recurring bills (electricity, water, rent, tax...), their monthly statements and payments. */
class BillsApiController extends Controller
{
    /** Categories an admin can pick for a bill or a one-off expense ("stock" is set by Stock Receiving). */
    public const CATEGORIES = ['utilities', 'rent', 'tax', 'supplies', 'repairs', 'other'];

    protected function staffId(): ?int
    {
        return Staff::query()->where('email', Auth::user()->email)->value('id');
    }

    /** The month's checklist, the bill templates and every expense paid in that month. */
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate(['month' => ['nullable', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/']]);
        $month = $data['month'] ?? today()->format('Y-m');
        BillStatement::ensureForMonth($month);

        $statements = BillStatement::query()->with(['bill', 'payments'])
            ->where('period_month', $month)->get()->sortBy('bill.name')->values();
        $rows = $statements->map->toRow();

        $start = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        $expenses = FinanceTransaction::query()->with('staff:id,name')->where('type', 'expense')
            ->whereBetween('transaction_date', [$start->toDateString(), $start->copy()->endOfMonth()->toDateString()])
            ->latest('transaction_date')->latest('id')->get()
            ->map(fn (FinanceTransaction $f) => [
                'id' => $f->id,
                'date' => $f->transaction_date->toDateString(),
                'description' => $f->description,
                'category' => $f->category ?? 'other',
                'reference_no' => $f->reference_no,
                'amount' => (float) $f->amount,
                'by' => $f->staff?->name,
                'can_void' => $f->inventory_restock_id === null && $f->laundry_transaction_id === null,
            ]);

        return response()->json([
            'month' => $month,
            'categories' => self::CATEGORIES,
            'statements' => $rows,
            'totals' => [
                'billed' => round((float) $rows->sum('amount_due'), 2),
                'paid' => round((float) $rows->sum('paid'), 2),
                'balance' => round((float) $rows->sum('balance'), 2),
                'expenses' => round((float) $expenses->sum('amount'), 2),
            ],
            'bills' => RecurringBill::query()->orderByDesc('is_active')->orderBy('name')->get(),
            'expenses' => $expenses,
        ]);
    }

    /** @return array<string, mixed> */
    protected function billRules(?RecurringBill $bill = null): array
    {
        return [
            'name' => ['required', 'string', 'max:120', Rule::unique('recurring_bills', 'name')->ignore($bill?->id)],
            'payee' => ['nullable', 'string', 'max:120'],
            'category' => ['required', Rule::in(self::CATEGORIES)],
            'usual_amount' => ['nullable', 'numeric', 'min:0', 'max:10000000'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    public function storeBill(Request $request): JsonResponse
    {
        $bill = RecurringBill::query()->create($request->validate($this->billRules()));
        $this->audit('bill.created', ['bill_id' => $bill->id, 'name' => $bill->name]);

        return response()->json(['bill' => $bill], 201);
    }

    public function updateBill(Request $request, RecurringBill $bill): JsonResponse
    {
        $bill->update($request->validate($this->billRules($bill)));
        $this->audit('bill.updated', ['bill_id' => $bill->id, 'name' => $bill->name, 'active' => $bill->is_active]);

        return response()->json(['bill' => $bill]);
    }

    /** Pay all or part of a month's bill. The first payment can also set the amount billed. */
    public function storePayment(Request $request, BillStatement $statement): JsonResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0', 'max:10000000'],
            'amount_due' => ['nullable', 'numeric', 'gt:0', 'max:10000000'],
            'paid_on' => ['nullable', 'date', 'before_or_equal:today'],
            'reference_no' => ['nullable', 'string', 'max:80'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $payment = DB::transaction(function () use ($statement, $data) {
            $statement = BillStatement::query()->with(['bill', 'payments'])->lockForUpdate()->findOrFail($statement->id);
            if (isset($data['amount_due'])) {
                $statement->update(['amount_due' => $data['amount_due']]);
            }
            if ($statement->amount_due === null) {
                abort(response()->json(['message' => 'Enter the amount billed for this month first.'], 422));
            }
            $left = round((float) $statement->amount_due - $statement->paidTotal(), 2);
            if ((float) $data['amount'] > $left + 0.005) {
                abort(response()->json(['message' => $left > 0 ? 'Only '.number_format($left, 2).' is left to pay on this bill.' : 'This bill is already fully paid.'], 422));
            }

            return FinanceTransaction::query()->create([
                'type' => 'expense',
                'description' => $statement->bill->name.' - '.Carbon::createFromFormat('Y-m', $statement->period_month)->format('M Y'),
                'category' => $statement->bill->category,
                'bill_statement_id' => $statement->id,
                'reference_no' => $data['reference_no'] ?? null,
                'amount' => $data['amount'],
                'staff_id' => $this->staffId(),
                'transaction_date' => $data['paid_on'] ?? today()->toDateString(),
                'notes' => $data['notes'] ?? null,
            ]);
        });

        $statement = $statement->fresh(['bill', 'payments']);
        $this->audit('bill.payment_recorded', ['bill' => $statement->bill->name, 'month' => $statement->period_month, 'amount' => (float) $payment->amount]);

        return response()->json(['payment' => $payment, 'statement' => $statement->toRow()], 201);
    }

    /** A one-off expense that is not a recurring bill (repairs, supplies...). */
    public function storeExpense(Request $request): JsonResponse
    {
        $data = $request->validate([
            'description' => ['required', 'string', 'max:160'],
            'category' => ['required', Rule::in(self::CATEGORIES)],
            'amount' => ['required', 'numeric', 'gt:0', 'max:10000000'],
            'paid_on' => ['nullable', 'date', 'before_or_equal:today'],
            'reference_no' => ['nullable', 'string', 'max:80'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $expense = FinanceTransaction::query()->create([
            'type' => 'expense',
            'description' => $data['description'],
            'category' => $data['category'],
            'reference_no' => $data['reference_no'] ?? null,
            'amount' => $data['amount'],
            'staff_id' => $this->staffId(),
            'transaction_date' => $data['paid_on'] ?? today()->toDateString(),
            'notes' => $data['notes'] ?? null,
        ]);
        $this->audit('expense.recorded', ['description' => $expense->description, 'category' => $expense->category, 'amount' => (float) $expense->amount]);

        return response()->json(['expense' => $expense], 201);
    }

    /** Remove a payment or one-off expense entered by mistake. Stock receipts and sales are voided from their own screens. */
    public function voidExpense(Request $request, FinanceTransaction $expense): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:200']]);
        if ($expense->type !== 'expense' || $expense->inventory_restock_id || $expense->laundry_transaction_id) {
            return response()->json(['message' => 'Only bill payments and manual expenses can be removed here.'], 422);
        }

        $expense->delete();
        $this->audit('expense.voided', ['description' => $expense->description, 'amount' => (float) $expense->amount, 'reason' => $data['reason']]);

        return response()->json(['message' => 'Entry removed.']);
    }
}
