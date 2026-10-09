<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\FinanceTransaction;
use App\Models\InventoryAdjustment;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\InventoryRestock;
use App\Models\LaundryTransaction;
use App\Models\Machine;
use App\Models\MachineRate;
use App\Models\Notification;
use App\Models\Service;
use App\Models\Staff;
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
                'machineUsages.machine:id,name,type',
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
                'machineUsages.machine:id,name,type',
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

        $machines = Machine::query()->withCount('transactions')->with('activeOrder.customer:id,name')->orderBy('name')->get()
            ->each(fn (Machine $m) => $m->setAttribute('current_order', $m->activeOrder ? ['id' => $m->activeOrder->id, 'customer' => $m->activeOrder->customer?->name] : null));
        $machinesMaintenance = $machines->whereIn('status', ['maintenance', 'out_of_service'])->values();

        $inventory = InventoryItem::query()->where('status', 'active')->with('category:id,name')->orderBy('name')->get();
        $categories = InventoryCategory::query()->orderBy('name')->get();
        $services = Service::query()->withCount('transactions')->orderBy('name')->get();
        $restocks = InventoryRestock::query()->with(['item:id,name,unit', 'staff:id,name'])->latest()->limit(200)->get();
        $awaitingStock = InventoryRestock::query()->whereNull('voided_at')->whereColumn('stocked_quantity', '<', 'quantity_received')->count();
        $movements = InventoryAdjustment::query()->with(['item:id,name,unit', 'staff:id,name'])->latest('id')->limit(300)->get();
        $users = User::query()->orderBy('name')->get(['id', 'name', 'email', 'role', 'rejection_reason', 'email_verified_at', 'created_at']);

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
                'receipts_awaiting_stock' => $awaitingStock,
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
            'machine_rates' => MachineRate::query()->orderBy('size')->orderBy('kind')->orderBy('minutes')->get(),
            'machines_attention' => $machinesMaintenance,
            'inventory' => $inventory,
            'categories' => $categories,
            'services' => $services,
            'restocks' => $restocks,
            'movements' => $movements,
            'archive_records' => InventoryAdjustment::archiveFeed(),
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
            'machineUsages.machine:id,name,type',
            'detergent:id,name,unit',
            'handledBy:id,name',
            'garmentTypes:id,name',
            'inventoryItems:id,name,unit',
            'statusLogs' => fn ($q) => $q->latest('changed_at')->limit(10),
        ]);

        return response()->json(['transaction' => $transaction]);
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

    public function storeCategory(Request $request): JsonResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:60', 'unique:inventory_categories,name']]);
        $category = InventoryCategory::query()->create($data);
        $this->audit('inventory.category_created', ['category_id' => $category->id, 'name' => $category->name]);

        return response()->json(['category' => $category], 201);
    }

    public function updateCategory(Request $request, InventoryCategory $category): JsonResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:60', Rule::unique('inventory_categories', 'name')->ignore($category->id)]]);
        $before = $category->name;
        $category->update($data);
        $this->audit('inventory.category_renamed', ['category_id' => $category->id, 'before' => $before, 'name' => $category->name]);

        return response()->json(['category' => $category]);
    }

    public function destroyCategory(InventoryCategory $category): JsonResponse
    {
        if ($category->items()->exists()) {
            return response()->json(['message' => "{$category->name} still has products. Move or archive them first."], 422);
        }
        $category->delete();
        $this->audit('inventory.category_deleted', ['category_id' => $category->id, 'name' => $category->name]);

        return response()->json(['message' => 'Category deleted.']);
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
        if ($item->quantity_on_hand > 0) {
            $item->logMovement($item->quantity_on_hand, 'Opening stock', $this->staffId());
        }

        $this->audit('inventory.item_created', ['item_id' => $item->id, 'name' => $item->name, 'unit_price' => (float) $item->unit_price]);

        return response()->json(['item' => $item->load('category')], 201);
    }

    /** Edit an inventory item's details; a changed quantity is logged as an adjustment. */
    public function updateInventoryItem(Request $request, InventoryItem $item): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'inventory_category_id' => ['required', 'integer', 'exists:inventory_categories,id'],
            'unit' => ['required', 'string', 'max:40'],
            'unit_price' => ['required', 'numeric', 'min:0'],
            'quantity_on_hand' => ['required', 'integer', 'min:0'],
            'low_stock_threshold' => ['required', 'integer', 'min:0'],
        ]);

        DB::transaction(function () use ($item, $data) {
            $item = InventoryItem::query()->lockForUpdate()->findOrFail($item->id);
            $change = (int) $data['quantity_on_hand'] - (int) $item->quantity_on_hand;
            $item->update($data);
            if ($change !== 0) {
                $item->adjustments()->create([
                    'staff_id' => $this->staffId(),
                    'quantity_change' => $change,
                    'reason' => 'Edited by admin',
                ]);
            }
        });

        $this->audit('inventory.item_updated', ['item_id' => $item->id, 'name' => $data['name'], 'unit_price' => (float) $data['unit_price']]);

        return response()->json(['item' => $item->fresh('category')]);
    }

    /** Moves a recorded receipt into inventory (once). */
    public function stockRestock(Request $request, InventoryRestock $restock): JsonResponse
    {
        $data = $request->validate(['quantity' => ['nullable', 'integer', 'min:1']]);

        [$restock, $added] = DB::transaction(function () use ($restock, $data) {
            $restock = InventoryRestock::query()->lockForUpdate()->findOrFail($restock->id);
            if ($restock->voided_at) {
                throw ValidationException::withMessages(['restock' => 'This receipt was voided.']);
            }
            $remaining = $restock->remaining_quantity;
            if ($remaining <= 0) {
                throw ValidationException::withMessages(['restock' => 'This receipt was already added to inventory.']);
            }
            $qty = (int) ($data['quantity'] ?? $remaining);
            if ($qty > $remaining) {
                throw ValidationException::withMessages(['quantity' => "Only {$remaining} left to add from this receipt."]);
            }

            $item = InventoryItem::query()->lockForUpdate()->findOrFail($restock->inventory_item_id);
            $item->increment('quantity_on_hand', $qty);
            $item->logMovement($qty, 'Received'.($restock->invoice_number ? ' · invoice '.$restock->invoice_number : ' · delivery #'.$restock->id), $this->staffId());
            // Stock arrived: staff low-stock reports for this item are resolved.
            Notification::query()->where('type', 'low_stock')->where('is_read', false)
                ->where('notifiable_type', InventoryItem::class)->where('notifiable_id', $item->id)
                ->update(['is_read' => true]);
            if (($item->status ?? 'active') !== 'active') {
                $item->update(['status' => 'active']);
            }
            $restock->stocked_quantity += $qty;
            $restock->stocked_at = $restock->stocked_quantity >= $restock->quantity_received ? now() : null;
            $restock->save();

            return [$restock->load(['item', 'staff']), $qty];
        });

        $this->audit('inventory.restocked', ['restock_id' => $restock->id, 'item_id' => $restock->inventory_item_id, 'quantity' => $added]);

        return response()->json([
            'message' => $added.' added to '.$restock->item->name.'. '.($restock->remaining_quantity > 0 ? $restock->remaining_quantity.' still to add.' : 'Receipt fully stocked.'),
            'restock' => $restock,
        ]);
    }

    /** Cancels a receipt entered by mistake (only while none of it has been added to inventory); its expense is removed. */
    public function voidRestock(Request $request, InventoryRestock $restock): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:200']]);

        DB::transaction(function () use ($restock, $data) {
            $restock = InventoryRestock::query()->lockForUpdate()->findOrFail($restock->id);
            if ($restock->voided_at || $restock->stocked_quantity > 0) {
                throw ValidationException::withMessages(['restock' => $restock->voided_at ? 'This receipt is already voided.' : 'Part of this receipt is already in inventory, so it cannot be voided. Correct the item quantity instead.']);
            }
            FinanceTransaction::query()->where('inventory_restock_id', $restock->id)->delete();
            $restock->update(['voided_at' => now(), 'void_reason' => $data['reason']]);
        });

        $this->audit('inventory.receipt_voided', ['restock_id' => $restock->id, 'reason' => $data['reason']]);

        return response()->json(['message' => 'Receipt voided and its expense removed.']);
    }

    /** Admin Activity Log: who did what, 8 per page, optional search. */
    public function auditLog(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        $page = AuditLog::query()
            ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w->where('action', 'like', "%{$q}%")->orWhere('user_email', 'like', "%{$q}%")))
            ->latest('id')
            ->paginate(8);

        return response()->json($page);
    }

    /** Full-range data for CSV/PDF exports (the dashboard only keeps the latest rows). */
    public function reportData(Request $request): JsonResponse
    {
        $data = $request->validate(['from' => ['required', 'date'], 'to' => ['required', 'date']]);
        $from = Carbon::parse($data['from'])->startOfDay();
        $to = Carbon::parse($data['to'])->endOfDay();

        $orders = LaundryTransaction::query()
            ->with(['customer:id,name', 'basketTag:id,code', 'service:id,name'])
            ->where('status', '!=', 'cancelled')
            ->whereBetween('created_at', [$from, $to])
            ->orderBy('created_at')->get();
        $finance = FinanceTransaction::query()
            ->with('staff:id,name')
            ->where(fn ($q) => $q->whereNull('laundry_transaction_id')
                ->orWhereNotIn('laundry_transaction_id', LaundryTransaction::query()->where('status', 'cancelled')->select('id')))
            ->whereBetween('transaction_date', [$from->toDateString(), $to->toDateString()])
            ->orderBy('transaction_date')->get();

        return response()->json(['orders' => $orders, 'finance' => $finance]);
    }

    /** Records a delivery against an invoice. Inventory is NOT touched until the receipt is added to stock. */
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
            $item = InventoryItem::query()->findOrFail($data['inventory_item_id']);
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
                    'inventory_restock_id' => $row->id,
                    'description' => $desc,
                    'amount' => $data['cost'],
                    'staff_id' => $this->staffId(),
                    'transaction_date' => $receivedOn,
                    'notes' => trim(($data['supplier'] ?? '').(! empty($data['notes']) ? ' · '.$data['notes'] : '')),
                ]);
            }

            return $row->load(['item', 'staff']);
        });

        $this->audit('inventory.receipt_recorded', ['restock_id' => $restock->id, 'item_id' => $restock->inventory_item_id, 'quantity' => $restock->quantity_received, 'cost' => $restock->cost]);

        return response()->json([
            'message' => 'Receipt saved. Use "Add to inventory" on it when the stock is put on the shelf.',
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

        if ($user->role === 'rejected') {
            return response()->json(['message' => 'This sign-up was rejected. A rejected account cannot be approved or changed.'], 422);
        }

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

    /** @return array<string, mixed> */
    protected function serviceRules(?Service $service = null): array
    {
        return [
            'name' => ['required', 'string', 'max:80', Rule::unique('services', 'name')->ignore($service?->id)],
            'base_price' => ['required', 'numeric', 'min:0', 'max:100000'],
            'rate_per_kg' => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    public function storeService(Request $request): JsonResponse
    {
        $service = Service::query()->create($request->validate($this->serviceRules()));
        $this->audit('service.created', ['service_id' => $service->id, 'name' => $service->name, 'base_price' => (float) $service->base_price, 'rate_per_kg' => $service->rate_per_kg]);

        return response()->json(['service' => $service], 201);
    }

    /** Price changes apply to new orders only; past orders keep what was charged. */
    public function updateService(Request $request, Service $service): JsonResponse
    {
        $before = ['base_price' => (float) $service->base_price, 'rate_per_kg' => $service->rate_per_kg];
        $service->update($request->validate($this->serviceRules($service)));
        $this->audit('service.updated', ['service_id' => $service->id, 'name' => $service->name, 'before' => json_encode($before), 'base_price' => (float) $service->base_price, 'rate_per_kg' => $service->rate_per_kg, 'active' => $service->is_active]);

        return response()->json(['service' => $service]);
    }

    /** Declines a pending sign-up. The account is kept (as "rejected") with the reason, and cannot sign in until an admin approves it. */
    public function rejectUser(Request $request, User $user): JsonResponse
    {
        if ($user->role !== 'pending') {
            return response()->json(['message' => 'Only accounts waiting for approval can be rejected.'], 422);
        }
        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:200']]);

        $user->forceFill(['role' => 'rejected', 'rejection_reason' => $data['reason']])->save();
        $this->audit('user.rejected', ['target_user_id' => $user->id, 'target_email' => $user->email, 'reason' => $data['reason']]);

        return response()->json(['message' => 'Account rejected.']);
    }

    /** @return array<string, mixed> */
    protected function machineRules(?Machine $machine = null): array
    {
        return [
            'name' => ['required', 'string', 'max:60', Rule::unique('machines', 'name')->ignore($machine?->id)],
            'type' => ['required', Rule::in(['washer', 'dryer'])],
            'size' => ['required', Rule::in(['giant', 'titan'])],
            'status' => ['required', Rule::in(['available', 'reserved', 'maintenance', 'out_of_service'])],
        ];
    }

    public function storeMachine(Request $request): JsonResponse
    {
        $machine = Machine::query()->create($request->validate($this->machineRules()));
        $this->audit('machine.created', ['machine_id' => $machine->id, 'name' => $machine->name]);

        return response()->json(['machine' => $machine], 201);
    }

    public function updateMachine(Request $request, Machine $machine): JsonResponse
    {
        $data = $request->validate($this->machineRules($machine));

        // A machine running an order keeps its status; it frees up when the order is marked ready or cancelled.
        if ($machine->activeOrder()->exists()) {
            $data['status'] = $machine->status;
        }

        $machine->update($data);
        $this->audit('machine.updated', ['machine_id' => $machine->id, 'name' => $machine->name, 'status' => $machine->status]);

        return response()->json(['machine' => $machine]);
    }

    /** Set the wash price per load (and capacity) or a dryer time price for one machine size. */
    public function saveMachineRate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'size' => ['required', Rule::in(['giant', 'titan'])],
            'kind' => ['required', Rule::in(['wash', 'dry'])],
            'minutes' => ['required_if:kind,dry', 'nullable', 'integer', 'min:10', 'max:120', 'multiple_of:10'],
            'price' => ['required', 'numeric', 'min:0', 'max:100000'],
            'capacity_kg' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);
        $minutes = $data['kind'] === 'wash' ? 0 : (int) $data['minutes'];

        $rate = MachineRate::query()->firstOrNew(['size' => $data['size'], 'kind' => $data['kind'], 'minutes' => $minutes]);
        $before = $rate->exists ? (float) $rate->price : null;
        $rate->fill(['price' => $data['price'], 'capacity_kg' => $data['kind'] === 'wash' ? ($data['capacity_kg'] ?? $rate->capacity_kg) : null])->save();
        $this->audit('machine_rate.saved', ['size' => $rate->size, 'kind' => $rate->kind, 'minutes' => $rate->minutes, 'before' => $before, 'price' => (float) $rate->price]);

        return response()->json(['rate' => $rate]);
    }

    public function destroyMachineRate(MachineRate $rate): JsonResponse
    {
        if ($rate->kind === 'wash') {
            return response()->json(['message' => 'A wash price cannot be removed; change it instead.'], 422);
        }
        $rate->delete();
        $this->audit('machine_rate.deleted', ['size' => $rate->size, 'minutes' => $rate->minutes]);

        return response()->json(['message' => 'Drying time removed.']);
    }

    public function destroyMachine(Machine $machine): JsonResponse
    {
        if ($machine->transactions()->exists()) {
            return response()->json(['message' => "{$machine->name} has order history and cannot be deleted. Set it to Out of service instead."], 422);
        }

        $machine->delete();
        $this->audit('machine.deleted', ['machine_id' => $machine->id, 'name' => $machine->name]);

        return response()->json(['message' => 'Machine deleted.']);
    }
}
