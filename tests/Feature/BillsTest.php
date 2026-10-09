<?php

use App\Models\BillStatement;
use App\Models\FinanceTransaction;
use App\Models\RecurringBill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function statementFor(string $name): array
{
    $admin = User::factory()->create(['role' => 'admin']);
    $row = collect(test()->actingAs($admin)->getJson('/ajax/admin/bills')->json('statements'))->firstWhere('name', $name);

    return [$admin, $row];
}

it('lists this month\'s checklist with the starting bills, due on the last day of the month', function () {
    [, $water] = statementFor('Water bill');
    $rent = collect($this->getJson('/ajax/admin/bills')->json('statements'))->firstWhere('name', 'Commercial space rent');

    expect($water['status'])->toBe('no_amount')
        ->and($water['due_date'])->toBe(today()->endOfMonth()->toDateString())
        ->and($rent['amount_due'])->toEqual(12305)
        ->and($rent['status'])->toBe('unpaid');
});

it('takes partial payments, tracks the balance and turns each payment into an expense', function () {
    [$admin, $rent] = statementFor('Commercial space rent');

    $this->actingAs($admin)->postJson("/ajax/admin/bill-statements/{$rent['id']}/payments", ['amount' => 5000, 'reference_no' => 'OR-1'])->assertCreated()
        ->assertJsonPath('statement.status', 'partial')->assertJsonPath('statement.balance', 7305);
    $this->actingAs($admin)->postJson("/ajax/admin/bill-statements/{$rent['id']}/payments", ['amount' => 9000])->assertStatus(422);
    $this->actingAs($admin)->postJson("/ajax/admin/bill-statements/{$rent['id']}/payments", ['amount' => 7305])->assertCreated()
        ->assertJsonPath('statement.status', 'paid');
    $this->actingAs($admin)->postJson("/ajax/admin/bill-statements/{$rent['id']}/payments", ['amount' => 1])->assertStatus(422);

    $summary = $this->actingAs($admin)->getJson('/ajax/admin/bootstrap?to='.today()->addDay()->toDateString());
    expect((float) FinanceTransaction::where('category', 'rent')->sum('amount'))->toBe(12305.0)
        ->and($summary->json('analytics.expenses_by_category.0.category'))->toBe('rent');
});

it('needs the billed amount before a utility bill can be paid, and accepts it with the first payment', function () {
    [$admin, $water] = statementFor('Water bill');

    $this->actingAs($admin)->postJson("/ajax/admin/bill-statements/{$water['id']}/payments", ['amount' => 100])->assertStatus(422);
    $this->actingAs($admin)->postJson("/ajax/admin/bill-statements/{$water['id']}/payments", ['amount' => 4000, 'amount_due' => 8657])->assertCreated()
        ->assertJsonPath('statement.amount_due', 8657)->assertJsonPath('statement.status', 'partial');
});

it('reminds about unpaid bills that are overdue or due soon', function () {
    $this->travelTo(today()->endOfMonth()->subDay()->setTime(9, 0));
    $admin = User::factory()->create(['role' => 'admin']);
    RecurringBill::query()->update(['created_at' => now()->subMonths(2)]);

    $thisMonth = fn () => collect($this->actingAs($admin)->getJson('/ajax/admin/bootstrap')->json('bills_attention'))
        ->where('period_month', now()->format('Y-m'))->pluck('name');
    expect($thisMonth())->toContain('Commercial space rent', 'Water bill');

    $rent = BillStatement::query()->whereHas('bill', fn ($q) => $q->where('name', 'Commercial space rent'))->where('period_month', now()->format('Y-m'))->first();
    $this->actingAs($admin)->postJson("/ajax/admin/bill-statements/{$rent->id}/payments", ['amount' => 12305])->assertCreated();
    expect($thisMonth())->not->toContain('Commercial space rent');
});

it('does not flag a month before a bill existed', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($admin)->getJson('/ajax/admin/bills?month='.today()->subMonths(3)->format('Y-m'))->assertOk()->assertJsonPath('statements', []);
});

it('records and voids one-off expenses but protects stock and sales rows', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $id = $this->actingAs($admin)->postJson('/ajax/admin/expenses', ['description' => 'Fix hose', 'category' => 'repairs', 'amount' => 350])->assertCreated()->json('expense.id');
    $this->actingAs($admin)->postJson('/ajax/admin/expenses', ['description' => 'Bad', 'category' => 'nonsense', 'amount' => 5])->assertStatus(422);

    $stock = FinanceTransaction::create(['type' => 'expense', 'description' => 'Stock', 'category' => 'stock', 'amount' => 10, 'transaction_date' => today(), 'inventory_restock_id' => null]);
    $stock->forceFill(['inventory_restock_id' => 999999])->saveQuietly();

    $this->actingAs($admin)->postJson("/ajax/admin/expenses/{$id}/void", ['reason' => 'x'])->assertStatus(422);
    $this->actingAs($admin)->postJson("/ajax/admin/expenses/{$id}/void", ['reason' => 'Entered twice'])->assertOk();
    $this->actingAs($admin)->postJson("/ajax/admin/expenses/{$stock->id}/void", ['reason' => 'Entered twice'])->assertStatus(422);
    expect(FinanceTransaction::find($id))->toBeNull();
});

it('keeps bills away from staff', function () {
    $staff = User::factory()->create(['role' => 'staff']);
    $this->actingAs($staff)->getJson('/ajax/admin/bills')->assertForbidden();
    $this->actingAs($staff)->postJson('/ajax/admin/expenses', ['description' => 'x', 'category' => 'other', 'amount' => 1])->assertForbidden();
});
