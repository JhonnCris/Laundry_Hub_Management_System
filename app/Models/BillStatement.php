<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class BillStatement extends Model
{
    /** A bill counts as "due soon" this many days before its due date. */
    public const DUE_SOON_DAYS = 3;

    protected $fillable = ['recurring_bill_id', 'period_month', 'amount_due', 'due_date'];

    protected function casts(): array
    {
        return ['amount_due' => 'decimal:2', 'due_date' => 'date'];
    }

    public function bill(): BelongsTo
    {
        return $this->belongsTo(RecurringBill::class, 'recurring_bill_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(FinanceTransaction::class);
    }

    /** Make sure every active bill has a statement for the month (due on the last day of that month). */
    public static function ensureForMonth(string $month): void
    {
        $due = Carbon::createFromFormat('Y-m', $month)->endOfMonth()->toDateString();

        RecurringBill::query()->where('is_active', true)->get()
            ->filter(fn (RecurringBill $bill) => $bill->created_at->format('Y-m') <= $month)
            ->each(fn (RecurringBill $bill) => static::query()->firstOrCreate(
                ['recurring_bill_id' => $bill->id, 'period_month' => $month],
                ['amount_due' => $bill->usual_amount, 'due_date' => $due],
            ));
    }

    public function paidTotal(): float
    {
        return round((float) $this->payments->sum('amount'), 2);
    }

    /** paid | partial | unpaid | no_amount (the billed amount has not been entered yet). */
    public function status(): string
    {
        if ($this->amount_due === null) {
            return 'no_amount';
        }
        $paid = $this->paidTotal();

        return $paid + 0.005 >= (float) $this->amount_due ? 'paid' : ($paid > 0 ? 'partial' : 'unpaid');
    }

    /** overdue | due_soon | ok (a paid statement is always ok). */
    public function timing(): string
    {
        if ($this->status() === 'paid') {
            return 'ok';
        }
        if ($this->due_date->lt(today())) {
            return 'overdue';
        }

        return $this->due_date->lte(today()->addDays(self::DUE_SOON_DAYS)) ? 'due_soon' : 'ok';
    }

    /**
     * @return array<string, mixed>
     */
    public function toRow(): array
    {
        $paid = $this->paidTotal();

        return [
            'id' => $this->id,
            'bill_id' => $this->recurring_bill_id,
            'name' => $this->bill->name,
            'payee' => $this->bill->payee,
            'category' => $this->bill->category,
            'period_month' => $this->period_month,
            'amount_due' => $this->amount_due === null ? null : (float) $this->amount_due,
            'due_date' => $this->due_date->toDateString(),
            'paid' => $paid,
            'balance' => $this->amount_due === null ? null : max(round((float) $this->amount_due - $paid, 2), 0),
            'status' => $this->status(),
            'timing' => $this->timing(),
        ];
    }

    /**
     * Unpaid statements that are overdue or due within a few days (this and last month).
     *
     * @return Collection<int, BillStatement>
     */
    public static function needingAttention(): Collection
    {
        $months = [today()->format('Y-m'), today()->subMonthNoOverflow()->format('Y-m')];
        foreach ($months as $month) {
            static::ensureForMonth($month);
        }

        return static::query()->with(['bill', 'payments'])
            ->whereIn('period_month', $months)
            ->whereDate('due_date', '<=', today()->addDays(self::DUE_SOON_DAYS))
            ->orderBy('due_date')
            ->get()
            ->filter(fn (BillStatement $s) => $s->timing() !== 'ok')
            ->values();
    }
}
