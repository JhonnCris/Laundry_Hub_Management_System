<?php

namespace App\Http\Controllers;

use App\Models\FinanceTransaction;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\InventoryRestock;
use App\Models\LaundryTransaction;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AdminApiController extends Controller
{
    protected function ensureAdmin(): void
    {
        abort_unless((Auth::user()->role ?? '') === 'admin', 403, 'Admin only');
    }

    protected function staffId(): ?int
    {
        return Staff::query()->where('email', Auth::user()->email)->value('id');
    }

    public function bootstrap(): JsonResponse
    {
        $this->ensureAdmin();

        $todayIncome = FinanceTransaction::query()
            ->where('type', 'income')
            ->whereDate('transaction_date', today())
            ->sum('amount');
        $todayExpense = FinanceTransaction::query()
            ->where('type', 'expense')
            ->whereDate('transaction_date', today())
            ->sum('amount');

        $activeCount = LaundryTransaction::query()
            ->whereIn('status', ['pending', 'processing', 'ready_for_pickup'])
            ->count();

        $lowStock = InventoryItem::query()
            ->with('category:id,name')
            ->get()
            ->filter(fn ($i) => $i->isLowStock())
            ->values();

        $recentOrders = LaundryTransaction::query()
            ->with(['customer:id,name', 'basketTag:id,code', 'service:id,name'])
            ->latest()
            ->limit(15)
            ->get();

        $financeRows = FinanceTransaction::query()
            ->with('staff:id,name')
            ->latest('transaction_date')
            ->limit(30)
            ->get();

        $inventory = InventoryItem::query()->with('category:id,name')->orderBy('name')->get();
        $categories = InventoryCategory::query()->orderBy('name')->get();
        $restocks = InventoryRestock::query()->with(['item:id,name', 'staff:id,name'])->latest()->limit(20)->get();
        $users = User::query()->orderBy('name')->get(['id', 'name', 'email', 'role', 'email_verified_at', 'created_at']);

        return response()->json([
            'metrics' => [
                'active_laundry' => $activeCount,
                'today_sales' => (float) $todayIncome,
                'today_expenses' => (float) $todayExpense,
                'net' => (float) $todayIncome - (float) $todayExpense,
                'low_stock_count' => $lowStock->count(),
            ],
            'low_stock' => $lowStock,
            'recent_orders' => $recentOrders,
            'finance' => $financeRows,
            'inventory' => $inventory,
            'categories' => $categories,
            'restocks' => $restocks,
            'users' => $users,
        ]);
    }

    public function storeInventoryItem(Request $request): JsonResponse
    {
        $this->ensureAdmin();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'inventory_category_id' => ['required', 'integer', 'exists:inventory_categories,id'],
            'unit' => ['required', 'string', 'max:40'],
            'unit_price' => ['required', 'numeric', 'min:0'],
            'quantity_on_hand' => ['required', 'integer', 'min:0'],
            'low_stock_threshold' => ['required', 'integer', 'min:0'],
        ]);

        $item = InventoryItem::query()->create($data);

        return response()->json(['item' => $item->load('category')], 201);
    }

    public function adjustInventory(Request $request, InventoryItem $item): JsonResponse
    {
        $this->ensureAdmin();
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

        return response()->json(['item' => $item->fresh('category')]);
    }

    public function storeRestock(Request $request): JsonResponse
    {
        $this->ensureAdmin();
        $data = $request->validate([
            'inventory_item_id' => ['required', 'integer', 'exists:inventory_items,id'],
            'quantity_received' => ['required', 'integer', 'min:1'],
            'supplier' => ['nullable', 'string', 'max:120'],
            'restocked_at' => ['nullable', 'date'],
            'cost' => ['nullable', 'numeric', 'min:0'],
        ]);

        $restock = DB::transaction(function () use ($data) {
            $item = InventoryItem::query()->lockForUpdate()->findOrFail($data['inventory_item_id']);
            $item->increment('quantity_on_hand', $data['quantity_received']);

            $row = InventoryRestock::query()->create([
                'inventory_item_id' => $item->id,
                'staff_id' => $this->staffId(),
                'quantity_received' => $data['quantity_received'],
                'supplier' => $data['supplier'] ?? null,
                'restocked_at' => $data['restocked_at'] ?? today(),
            ]);

            if (! empty($data['cost']) && (float) $data['cost'] > 0) {
                FinanceTransaction::query()->create([
                    'type' => 'expense',
                    'description' => 'Procurement: '.$item->name,
                    'amount' => $data['cost'],
                    'staff_id' => $this->staffId(),
                    'transaction_date' => $data['restocked_at'] ?? today(),
                    'notes' => $data['supplier'] ?? null,
                ]);
            }

            return $row->load(['item', 'staff']);
        });

        return response()->json(['restock' => $restock], 201);
    }

    public function storeUser(Request $request): JsonResponse
    {
        $this->ensureAdmin();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', Rule::in(['admin', 'staff'])],
        ]);

        $user = User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'role' => $data['role'],
            'email_verified_at' => now(),
        ]);

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
        $this->ensureAdmin();
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'email' => ['sometimes', 'email', 'max:190', Rule::unique('users', 'email')->ignore($user->id)],
            'role' => ['sometimes', Rule::in(['admin', 'staff'])],
            'password' => ['nullable', 'string', 'min:8'],
        ]);

        if (! empty($data['password'])) {
            $user->password = $data['password'];
        }
        $user->fill(collect($data)->except('password')->all());
        $user->save();

        return response()->json(['user' => $user]);
    }
}
