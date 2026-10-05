<?php

namespace App\Http\Controllers;

use App\Models\BasketTag;
use App\Models\FinanceTransaction;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\InventoryRestock;
use App\Models\LaundryTransaction;
use App\Models\Machine;
use App\Models\Notification;
use App\Models\Staff;
use App\Models\TransactionStatusLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AdminApiController extends Controller
{
    protected function staffId(): ?int
    {
        return Staff::query()->where('email', Auth::user()->email)->value('id');
    }

    public function bootstrap(Request $request): JsonResponse
    {
        $from = $request->query('from');
        $to = $request->query('to');
        // Default: last 7 days so charts/KPIs show data without empty date inputs
        $toDate = $to ? Carbon::parse($to)->endOfDay() : now()->endOfDay();
        $fromDate = $from ? Carbon::parse($from)->startOfDay() : now()->subDays(6)->startOfDay();

        // Cancelled orders live in the archive records and never count as revenue.
        $excludeCancelledIncome = fn ($q) => $q->whereNull('laundry_transaction_id')
            ->orWhereNotIn('laundry_transaction_id', LaundryTransaction::query()->where('status', 'cancelled')->select('id'));

        $laundrySales = LaundryTransaction::query()
            ->where('status', '!=', 'cancelled')
            ->where('payment_status', 'paid')
            ->whereBetween('created_at', [$fromDate, $toDate])
            ->sum('total_amount');

        $financeIncome = FinanceTransaction::query()
            ->where($excludeCancelledIncome)
            ->where('type', 'income')
            ->whereBetween('transaction_date', [$fromDate->toDateString(), $toDate->toDateString()])
            ->sum('amount');

        // Prefer laundry paid totals; fall back to finance income if laundry is empty
        $periodSales = (float) $laundrySales > 0 ? (float) $laundrySales : (float) $financeIncome;

        $periodExpense = (float) FinanceTransaction::query()
            ->where('type', 'expense')
            ->whereBetween('transaction_date', [$fromDate->toDateString(), $toDate->toDateString()])
            ->sum('amount');

        $activeQuery = LaundryTransaction::query()
            ->whereIn('status', ['pending', 'processing', 'ready_for_pickup']);
        $statusCounts = (clone $activeQuery)->selectRaw('status, COUNT(*) as n')->groupBy('status')->pluck('n', 'status');
        $statusBreakdown = [
            'pending' => (int) ($statusCounts['pending'] ?? 0),
            'processing' => (int) ($statusCounts['processing'] ?? 0),
            'ready_for_pickup' => (int) ($statusCounts['ready_for_pickup'] ?? 0),
        ];
        $activeCount = array_sum($statusBreakdown);

        $completedCount = LaundryTransaction::query()
            ->where('status', 'claimed')
            ->whereBetween('updated_at', [$fromDate, $toDate])
            ->count();

        $paidOrdersCount = LaundryTransaction::query()
            ->where('status', '!=', 'cancelled')
            ->where('payment_status', 'paid')
            ->whereBetween('created_at', [$fromDate, $toDate])
            ->count();

        $lowStock = InventoryItem::query()
            ->where('status', 'active')
            ->with('category:id,name')
            ->get()
            ->filter(fn ($i) => $i->isLowStock())
            ->values();

        $recentOrders = LaundryTransaction::query()
            ->with([
                'customer:id,name,contact_number,email',
                'basketTag:id,code',
                'service:id,name,base_price',
                'machine:id,name,type',
                'detergent:id,name,unit',
                'handledBy:id,name',
                'garmentTypes:id,name',
                'inventoryItems:id,name,unit',
            ])
            ->where('status', '!=', 'cancelled')
            ->whereBetween('created_at', [$fromDate->copy()->subDays(30), $toDate])
            ->latest()
            ->limit(50)
            ->get();

        $cancelledOrders = LaundryTransaction::query()
            ->with(['customer:id,name,contact_number,email', 'basketTag:id,code', 'service:id,name,base_price'])
            ->where('status', 'cancelled')
            ->latest('updated_at')
            ->limit(100)
            ->get();
        $cancelledCount = LaundryTransaction::query()->where('status', 'cancelled')->count();

        $activeOrders = (clone $activeQuery)
            ->with([
                'customer:id,name,contact_number,email',
                'basketTag:id,code',
                'service:id,name,base_price',
                'machine:id,name,type',
                'detergent:id,name,unit',
                'handledBy:id,name',
                'garmentTypes:id,name',
                'inventoryItems:id,name,unit',
            ])
            ->latest()
            ->get();

        $financeRows = FinanceTransaction::query()
            ->where($excludeCancelledIncome)
            ->with('staff:id,name')
            ->whereBetween('transaction_date', [$fromDate->toDateString(), $toDate->toDateString()])
            ->latest('transaction_date')
            ->limit(200)
            ->get();
        $financeTotal = FinanceTransaction::query()
            ->where($excludeCancelledIncome)
            ->whereBetween('transaction_date', [$fromDate->toDateString(), $toDate->toDateString()])
            ->count();

        $receiptsInRange = InventoryRestock::query()
            ->whereBetween('restocked_at', [$fromDate->toDateString(), $toDate->toDateString()]);
        $receiptsCount = (clone $receiptsInRange)->count();
        $receiptsSpend = (float) (clone $receiptsInRange)->sum('cost');

        $machines = Machine::query()->orderBy('name')->get(['id', 'name', 'type', 'status']);
        $machinesMaintenance = $machines->whereIn('status', ['maintenance', 'out_of_service'])->values();

        $inventory = InventoryItem::query()->where('status', 'active')->with('category:id,name')->orderBy('name')->get();
        $categories = InventoryCategory::query()->orderBy('name')->get();
        $restocks = InventoryRestock::query()->with(['item:id,name', 'staff:id,name'])->latest()->limit(30)->get();
        $users = User::query()->orderBy('name')->get(['id', 'name', 'email', 'role', 'email_verified_at', 'created_at']);

        // Daily sales series (paid laundry) for charts
        $paidInRange = LaundryTransaction::query()
            ->where('status', '!=', 'cancelled')
            ->where('payment_status', 'paid')
            ->whereBetween('created_at', [$fromDate, $toDate])
            ->get(['id', 'total_amount', 'created_at', 'transaction_type', 'service_id']);

        $salesByDay = [];
        $cursor = $fromDate->copy()->startOfDay();
        $endCursor = $toDate->copy()->startOfDay();
        while ($cursor->lte($endCursor)) {
            $key = $cursor->toDateString();
            $salesByDay[$key] = 0.0;
            $cursor->addDay();
        }
        foreach ($paidInRange as $tx) {
            $key = $tx->created_at->toDateString();
            if (isset($salesByDay[$key])) {
                $salesByDay[$key] += (float) $tx->total_amount;
            }
        }
        $salesSeries = collect($salesByDay)->map(fn ($amount, $date) => [
            'date' => $date,
            'amount' => round($amount, 2),
        ])->values();

        // Expense series from finance
        $expenseByDay = FinanceTransaction::query()
            ->where('type', 'expense')
            ->whereBetween('transaction_date', [$fromDate->toDateString(), $toDate->toDateString()])
            ->selectRaw('transaction_date as date, SUM(amount) as amount')
            ->groupBy('transaction_date')
            ->orderBy('transaction_date')
            ->get()
            ->map(fn ($r) => [
                'date' => Carbon::parse($r->date)->toDateString(),
                'amount' => round((float) $r->amount, 2),
            ])
            ->values();

        // Service mix
        $serviceMix = DB::table('laundry_transactions as lt')
            ->leftJoin('services as s', 's.id', '=', 'lt.service_id')
            ->where('lt.status', '!=', 'cancelled')
            ->where('lt.payment_status', 'paid')
            ->whereBetween('lt.created_at', [$fromDate, $toDate])
            ->whereNotNull('lt.service_id')
            ->selectRaw('lt.service_id, s.name as service_name, COUNT(*) as orders, SUM(lt.total_amount) as revenue')
            ->groupBy('lt.service_id', 's.name')
            ->orderByDesc('revenue')
            ->get()
            ->map(fn ($r) => [
                'service' => $r->service_name ?? 'Service #'.$r->service_id,
                'orders' => (int) $r->orders,
                'revenue' => round((float) $r->revenue, 2),
            ])
            ->values();

        $typeMix = [
            'drop_off' => (int) $paidInRange->where('transaction_type', 'drop_off')->count(),
            'self_service' => (int) $paidInRange->where('transaction_type', 'self_service')->count(),
        ];

        // Busiest hours (0–23) from all transactions created in range
        $hourCounts = array_fill(0, 24, 0);
        $allInRange = LaundryTransaction::query()
            ->where('status', '!=', 'cancelled')
            ->whereBetween('created_at', [$fromDate, $toDate])
            ->get(['created_at']);
        foreach ($allInRange as $tx) {
            $h = (int) $tx->created_at->format('G');
            $hourCounts[$h]++;
        }
        $ordersByHour = collect($hourCounts)->map(fn ($count, $hour) => [
            'hour' => sprintf('%02d:00', $hour),
            'count' => (int) $count,
        ])->values();

        // Popular products (transaction_items in range)
        $txIds = $paidInRange->pluck('id');
        $popularProducts = [];
        if ($txIds->isNotEmpty()) {
            $popularProducts = DB::table('transaction_items')
                ->join('inventory_items', 'inventory_items.id', '=', 'transaction_items.inventory_item_id')
                ->whereIn('transaction_items.laundry_transaction_id', $txIds)
                ->selectRaw('inventory_items.id, inventory_items.name, inventory_items.unit, SUM(transaction_items.quantity) as qty_sold, SUM(transaction_items.line_total) as revenue')
                ->groupBy('inventory_items.id', 'inventory_items.name', 'inventory_items.unit')
                ->orderByDesc('qty_sold')
                ->limit(10)
                ->get()
                ->map(fn ($r) => [
                    'id' => $r->id,
                    'name' => $r->name,
                    'unit' => $r->unit,
                    'qty_sold' => (int) $r->qty_sold,
                    'revenue' => round((float) $r->revenue, 2),
                ])
                ->values();
        }

        $inventoryValue = $inventory->sum(fn ($i) => (float) $i->unit_price * (int) $i->quantity_on_hand);

        return response()->json([
            'range' => [
                'from' => $fromDate->toDateString(),
                'to' => $toDate->toDateString(),
            ],
            'metrics' => [
                'active_laundry' => $activeCount,
                'status_breakdown' => $statusBreakdown,
                'period_sales' => $periodSales,
                'today_sales' => $periodSales,
                'today_expenses' => $periodExpense,
                'period_expenses' => $periodExpense,
                'net' => $periodSales - $periodExpense,
                'low_stock_count' => $lowStock->count(),
                'completed_orders' => $completedCount,
                'paid_orders' => $paidOrdersCount,
                'machines_attention' => $machinesMaintenance->count(),
                'inventory_skus' => $inventory->count(),
                'inventory_value' => round($inventoryValue, 2),
                'finance_total' => $financeTotal,
                'cancelled_orders' => $cancelledCount,
                'receipts_count' => $receiptsCount,
                'receipts_spend' => round($receiptsSpend, 2),
            ],
            'low_stock' => $lowStock,
            'recent_orders' => $recentOrders,
            'active_orders' => $activeOrders,
            'staff_reports' => Notification::query()
                ->where('type', 'low_stock')->where('is_read', false)
                ->latest()->limit(30)->get(['id', 'message', 'notifiable_id', 'created_at']),
            'cancelled_orders' => $cancelledOrders,
            'finance' => $financeRows,
            'machines' => $machines,
            'machines_attention' => $machinesMaintenance,
            'inventory' => $inventory,
            'categories' => $categories,
            'restocks' => $restocks,
            'users' => $users,
            'analytics' => [
                'sales_by_day' => $salesSeries,
                'expenses_by_day' => $expenseByDay,
                'service_mix' => $serviceMix,
                'type_mix' => $typeMix,
                'popular_products' => $popularProducts,
                'orders_by_hour' => $ordersByHour,
            ],
        ]);
    }

    public function showTransaction(LaundryTransaction $transaction): JsonResponse
    {
        $transaction->load([
            'customer:id,name,contact_number,email',
            'basketTag:id,code',
            'service:id,name,base_price',
            'machine:id,name,type',
            'detergent:id,name,unit',
            'handledBy:id,name',
            'garmentTypes:id,name',
            'inventoryItems:id,name,unit',
            'statusLogs' => fn ($q) => $q->latest('changed_at')->limit(10),
        ]);

        return response()->json(['transaction' => $transaction]);
    }

    /**
     * Cancel an order: it leaves all sales totals, stock is returned and the
     * order is kept in the archive records with the reason.
     */
    public function cancelTransaction(Request $request, LaundryTransaction $transaction): JsonResponse
    {
        $this->authorize('cancel', $transaction);

        $data = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:200'],
        ]);

        DB::transaction(function () use ($transaction, $data) {
            $order = LaundryTransaction::query()->lockForUpdate()->findOrFail($transaction->id);

            // A claimed laundry order is done; a claimed snacks/drinks sale can still be voided.
            $isPurchaseOnly = $order->transaction_type === 'drop_off'
                && ! $order->service_id
                && ! $order->detergent_item_id
                && ! $order->garmentTypes()->exists();
            if ($order->status === 'cancelled' || ($order->status === 'claimed' && ! $isPurchaseOnly)) {
                throw ValidationException::withMessages([
                    'status' => "A {$order->status} order cannot be cancelled.",
                ]);
            }

            foreach ($order->inventoryItems as $item) {
                $item->increment('quantity_on_hand', (int) $item->pivot->quantity);
            }
            if ($order->detergent_item_id && $order->detergent_quantity > 0) {
                InventoryItem::query()->whereKey($order->detergent_item_id)->increment('quantity_on_hand', (int) $order->detergent_quantity);
            }
            if ($order->basket_tag_id) {
                BasketTag::query()->whereKey($order->basket_tag_id)->update(['status' => 'available']);
            }

            $order->update([
                'status' => 'cancelled',
                'notes' => trim(($order->notes ? $order->notes."\n" : '').'[Cancelled: '.$data['reason'].']'),
            ]);
            TransactionStatusLog::query()->create([
                'laundry_transaction_id' => $order->id,
                'status' => 'cancelled',
                'changed_by' => $this->staffId(),
                'changed_at' => now(),
            ]);
        });

        $this->audit('order.cancelled', ['order_id' => $transaction->id, 'reason' => $data['reason']]);

        return response()->json(['message' => 'Order cancelled and archived.', 'transaction' => $transaction->fresh()]);
    }

    /** Records who exported which report (the CSV itself is built in the browser). */
    public function logExport(Request $request): JsonResponse
    {
        $data = $request->validate([
            'report' => ['required', Rule::in(['sales', 'expenses', 'full'])],
            'format' => ['nullable', Rule::in(['pdf', 'csv'])],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $this->audit('report.exported', $data);

        return response()->json(['message' => 'Export recorded.']);
    }

    public function readNotification(Notification $notification): JsonResponse
    {
        $notification->update(['is_read' => true]);

        return response()->json(['message' => 'Alert dismissed.']);
    }

    public function storeInventoryItem(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'inventory_category_id' => ['required', 'integer', 'exists:inventory_categories,id'],
            'unit' => ['required', 'string', 'max:40'],
            'unit_price' => ['required', 'numeric', 'min:0'],
            'quantity_on_hand' => ['required', 'integer', 'min:0'],
            'low_stock_threshold' => ['required', 'integer', 'min:0'],
        ]);

        $item = InventoryItem::query()->create($data);

        $this->audit('inventory.item_created', ['item_id' => $item->id, 'name' => $item->name, 'unit_price' => (float) $item->unit_price]);

        return response()->json(['item' => $item->load('category')], 201);
    }

    public function adjustInventory(Request $request, InventoryItem $item): JsonResponse
    {
        $data = $request->validate([
            'quantity_change' => ['required', 'integer'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($item, $data) {
            $item->increment('quantity_on_hand', $data['quantity_change']);
            $item->adjustments()->create([
                'staff_id' => $this->staffId(),
                'quantity_change' => $data['quantity_change'],
                'reason' => $data['reason'] ?? null,
            ]);
        });

        $this->audit('inventory.adjusted', ['item_id' => $item->id, 'change' => $data['quantity_change'], 'reason' => $data['reason'] ?? null]);

        return response()->json(['item' => $item->fresh('category')]);
    }

    public function storeRestock(Request $request): JsonResponse
    {
        $data = $request->validate([
            'inventory_item_id' => ['required', 'integer', 'exists:inventory_items,id'],
            'quantity_received' => ['required', 'integer', 'min:1'],
            'quantity_invoiced' => ['nullable', 'integer', 'min:0'],
            'supplier' => ['nullable', 'string', 'max:120'],
            'invoice_number' => ['nullable', 'string', 'max:80'],
            'invoice_date' => ['nullable', 'date'],
            'restocked_at' => ['nullable', 'date'],
            'cost' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $restock = DB::transaction(function () use ($data) {
            $item = InventoryItem::query()->lockForUpdate()->findOrFail($data['inventory_item_id']);
            $item->increment('quantity_on_hand', $data['quantity_received']);
            // Stock arrived: staff low-stock reports for this item are resolved.
            Notification::query()->where('type', 'low_stock')->where('is_read', false)
                ->where('notifiable_type', InventoryItem::class)->where('notifiable_id', $item->id)
                ->update(['is_read' => true]);
            if (($item->status ?? 'active') !== 'active') {
                $item->update(['status' => 'active']);
            }

            $receivedOn = $data['restocked_at'] ?? today();
            $invoiceRef = $data['invoice_number'] ?? null;

            $row = InventoryRestock::query()->create([
                'inventory_item_id' => $item->id,
                'staff_id' => $this->staffId(),
                'quantity_received' => $data['quantity_received'],
                'quantity_invoiced' => $data['quantity_invoiced'] ?? $data['quantity_received'],
                'supplier' => $data['supplier'] ?? null,
                'invoice_number' => $invoiceRef,
                'invoice_date' => $data['invoice_date'] ?? $receivedOn,
                'cost' => $data['cost'] ?? null,
                'notes' => $data['notes'] ?? null,
                'restocked_at' => $receivedOn,
            ]);

            if (! empty($data['cost']) && (float) $data['cost'] > 0) {
                $desc = 'Stock receipt: '.$item->name;
                if ($invoiceRef) {
                    $desc .= ' (Invoice '.$invoiceRef.')';
                }
                FinanceTransaction::query()->create([
                    'type' => 'expense',
                    'description' => $desc,
                    'amount' => $data['cost'],
                    'staff_id' => $this->staffId(),
                    'transaction_date' => $receivedOn,
                    'notes' => trim(($data['supplier'] ?? '').($data['notes'] ? ' · '.$data['notes'] : '')),
                ]);
            }

            return $row->load(['item', 'staff']);
        });

        $this->audit('inventory.restocked', ['restock_id' => $restock->id, 'item_id' => $restock->inventory_item_id, 'quantity' => $restock->quantity_received, 'cost' => $restock->cost]);

        return response()->json([
            'message' => 'Stock receipt recorded. Inventory quantity updated.',
            'restock' => $restock,
        ], 201);
    }

    public function storeUser(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email', 'unique:staff,email'],
            'password' => ['required', 'string', Password::defaults()],
            'role' => ['required', Rule::in(['admin', 'staff'])],
        ]);

        $user = new User([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
        ]);
        // role and verification are never mass-assignable
        $user->forceFill(['role' => $data['role'], 'email_verified_at' => now()])->save();
        $this->audit('user.created', ['target_user_id' => $user->id, 'target_email' => $user->email, 'role' => $user->role]);

        if ($data['role'] === 'staff') {
            Staff::query()->firstOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'password' => Hash::make($data['password']),
                    'role' => 'staff',
                ]
            );
        }

        return response()->json(['user' => $user], 201);
    }

    public function updateUser(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'email' => ['sometimes', 'email', 'max:190', Rule::unique('users', 'email')->ignore($user->id), Rule::unique('staff', 'email')->ignore($user->email, 'email')],
            'role' => ['sometimes', Rule::in(['admin', 'staff', 'pending'])],
            'password' => ['nullable', 'string', Password::defaults()],
        ]);

        if ($user->is(Auth::user()) && ($data['role'] ?? $user->role) !== 'admin') {
            return response()->json(['message' => 'You cannot remove your own admin access.'], 422);
        }

        if (! empty($data['password'])) {
            $user->password = $data['password'];
        }
        $roleBefore = $user->role;
        $user->fill(collect($data)->only(['name', 'email'])->all());
        if (isset($data['role'])) {
            $user->forceFill(['role' => $data['role']]);
        }
        $user->save();
        $this->audit('user.updated', [
            'target_user_id' => $user->id,
            'role_before' => $roleBefore,
            'role_after' => $user->role,
            'password_changed' => ! empty($data['password']),
        ]);

        // Keep the linked staff record (attendance, handled-by) in sync.
        // (the User model already moves an existing staff row when email/name change)
        if ($user->role === 'staff') {
            Staff::query()->firstOrCreate(
                ['email' => $user->email],
                ['name' => $user->name, 'password' => $user->getAuthPassword(), 'role' => 'staff']
            );
        }

        return response()->json(['user' => $user]);
    }
}
