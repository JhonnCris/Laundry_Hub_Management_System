<?php

namespace App\Http\Controllers;

use App\Mail\LaundryReadyMail;
use App\Models\BasketTag;
use App\Models\Customer;
use App\Models\FinanceTransaction;
use App\Models\GarmentType;
use App\Models\InventoryAdjustment;
use App\Models\InventoryItem;
use App\Models\InventoryRestock;
use App\Models\LaundryTransaction;
use App\Models\Machine;
use App\Models\Notification;
use App\Models\Service;
use App\Models\Staff;
use App\Models\StaffAttendance;
use App\Models\TransactionGarment;
use App\Models\TransactionItem;
use App\Models\TransactionStatusLog;
use App\Services\FcmService;
use App\Services\SmsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StaffApiController extends Controller
{
    protected function staffId(): ?int
    {
        $user = Auth::user();
        if (! $user) {
            return null;
        }

        return Staff::query()->where('email', $user->email)->value('id');
    }

    /** Bootstrap data for staff dashboard UI */
    public function bootstrap(): JsonResponse
    {
        $staffId = $this->staffId();

        $garments = GarmentType::query()->orderBy('name')->get(['id', 'name', 'is_custom']);
        $services = Service::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'base_price']);
        $machines = Machine::query()->orderBy('name')->get(['id', 'name', 'type', 'status']);
        $baskets = BasketTag::query()->orderBy('code')->get(['id', 'code', 'status']);
        $inventory = InventoryItem::query()->with('category:id,name')->orderBy('name')->get();
        $activeInventory = $inventory->filter(fn ($i) => ($i->status ?? 'active') === 'active')->values();
        $archivedInventory = $inventory->filter(fn ($i) => ($i->status ?? 'active') !== 'active')->values();
        $customers = Customer::query()
            ->withCount('transactions')
            ->withMax('transactions', 'created_at')
            ->orderBy('name')
            ->get(['id', 'name', 'contact_number', 'email']);

        $activeLaundry = LaundryTransaction::query()
            ->with([
                'customer:id,name,contact_number',
                'basketTag:id,code',
                'service:id,name',
                'machine:id,name',
                'detergent:id,name,unit,unit_price',
                'handledBy:id,name',
                'garmentTypes:id,name',
                'inventoryItems:id,name,unit',
            ])
            ->whereIn('status', ['pending', 'processing', 'ready_for_pickup'])
            ->latest()
            ->limit(50)
            ->get();

        // Who currently holds each basket: the customer of its latest unfinished order.
        $basketAssignments = LaundryTransaction::query()
            ->with('customer:id,name')
            ->whereNotNull('basket_tag_id')
            ->whereIn('status', ['pending', 'processing', 'ready_for_pickup'])
            ->latest()
            ->get()
            ->unique('basket_tag_id')
            ->mapWithKeys(fn ($t) => [$t->basket_tag_id => [
                'order_id' => $t->id,
                'customer' => $t->customer?->name,
                'order_status' => $t->status,
            ]])
            ->all();

        $attendance = null;
        $recentAttendance = collect();
        if ($staffId) {
            $attendance = StaffAttendance::query()
                ->where('staff_id', $staffId)
                ->whereDate('work_date', today())
                ->first();
            $recentAttendance = StaffAttendance::query()
                ->where('staff_id', $staffId)
                ->orderByDesc('work_date')
                ->limit(10)
                ->get();
        }

        $snacks = $activeInventory->filter(fn ($i) => optional($i->category)->name === 'Snack/Drink')->values();
        $detergents = $activeInventory->filter(fn ($i) => in_array(optional($i->category)->name, ['Detergent', 'Consumable'], true))->values();

        $customersWithStats = $customers->map(function ($c) {
            return [
                'id' => $c->id,
                'name' => $c->name,
                'contact_number' => $c->contact_number,
                'email' => $c->email,
                'orders_count' => $c->transactions_count,
                'last_service' => $c->transactions_max_created_at,
            ];
        });

        $lowStockItems = $activeInventory->filter(fn ($i) => $i->isLowStock())->values();
        $dbNotifications = Notification::query()
            ->where('is_read', false)
            ->latest()
            ->limit(20)
            ->get(['id', 'type', 'message', 'is_read', 'created_at']);

        $notifications = $dbNotifications->map(fn ($n) => [
            'id' => $n->id,
            'type' => $n->type,
            'message' => $n->message,
            'created_at' => $n->created_at,
            'source' => 'db',
        ])->values();

        // Always surface live low-stock as alerts (even if no notification row yet)
        foreach ($lowStockItems as $item) {
            $notifications->push([
                'id' => 'stock-'.$item->id,
                'type' => 'low_stock',
                'message' => $item->name.' is low on stock ('.$item->quantity_on_hand.' '.$item->unit.' left).',
                'created_at' => now(),
                'source' => 'live',
            ]);
        }

        return response()->json([
            'user' => [
                'name' => Auth::user()->name,
                'email' => Auth::user()->email,
                'role' => Auth::user()->role ?? 'staff',
            ],
            'staff_id' => $staffId,
            'garment_types' => $garments,
            'services' => $services,
            'machines' => $machines,
            'baskets' => $baskets->map(fn ($b) => [
                'id' => $b->id,
                'code' => $b->code,
                'status' => $b->status,
                'assigned' => $basketAssignments[$b->id] ?? null,
            ])->values(),
            'available_baskets' => $baskets->where('status', 'available')->reject(fn ($b) => isset($basketAssignments[$b->id]))->values(),
            'inventory' => $activeInventory->values(),
            'archived_inventory' => $archivedInventory->values(),
            'snacks' => $snacks,
            'detergents' => $detergents,
            'customers' => $customersWithStats,
            'active_laundry' => $this->withNotifyUrls($activeLaundry),
            'attendance_today' => $attendance,
            'attendance_recent' => $recentAttendance,
            'low_stock_count' => $lowStockItems->count(),
            'reported_low_stock_ids' => Notification::query()
                ->where('type', 'low_stock')->where('is_read', false)
                ->where('notifiable_type', InventoryItem::class)
                ->pluck('notifiable_id')->values(),
            'active_laundry_count' => LaundryTransaction::query()->whereIn('status', ['pending', 'processing', 'ready_for_pickup'])->count(),
            'notifications' => $notifications,
        ]);
    }

    public function saveTransaction(Request $request): JsonResponse
    {
        $data = $request->validate([
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'transaction_type' => ['required', Rule::in(['drop_off', 'self_service'])],
            'basket_code' => ['nullable', 'string', 'max:20'],
            'service_id' => ['nullable', 'integer', 'exists:services,id'],
            'service_amount' => ['required', 'numeric', 'min:0', 'max:100000'],
            'machine_id' => ['nullable', 'integer', 'exists:machines,id'],
            'load_weight_kg' => ['nullable', 'numeric', 'min:0', 'max:50'],
            'cycle_minutes' => ['nullable', 'integer', 'min:0', 'max:240'],
            'detergent_item_id' => ['nullable', 'integer', 'exists:inventory_items,id'],
            'detergent_quantity' => ['nullable', 'integer', 'min:0', 'max:99'],
            'garments' => ['nullable', 'array'],
            'garments.*.name' => ['required_with:garments', 'string', 'max:80'],
            'garments.*.quantity' => ['required_with:garments', 'integer', 'min:1', 'max:999'],
            'items' => ['nullable', 'array'],
            'items.*.inventory_item_id' => ['required_with:items', 'integer', 'exists:inventory_items,id'],
            'items.*.quantity' => ['required_with:items', 'integer', 'min:1', 'max:99'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'cash_tendered' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'total_amount' => ['required', 'numeric', 'min:0', 'max:100000'],
            'client_token' => ['nullable', 'string', 'max:64'],
        ]);

        if ($data['transaction_type'] === 'self_service' && (float) ($data['load_weight_kg'] ?? 0) <= 0) {
            return response()->json(['message' => 'Load weight (kg) is required for self-service.'], 422);
        }

        $cash = (float) $data['cash_tendered'];
        $total = (float) $data['total_amount'];
        if ($cash + 0.001 < $total) {
            return response()->json(['message' => 'Cash tendered is less than total amount.'], 422);
        }

        $staffId = $this->staffId();
        $customer = Customer::query()->findOrFail($data['customer_id']);

        // Same payment submitted twice (double click / replay) is rejected.
        $tokenKey = ! empty($data['client_token']) ? 'tx-token:'.Auth::id().':'.$data['client_token'] : null;
        if ($tokenKey && ! Cache::add($tokenKey, true, 30)) {
            return response()->json(['message' => 'This payment was already submitted. Check Active Laundry before trying again.'], 409);
        }

        try {
            $tx = DB::transaction(function () use ($data, $customer, $cash, $total, $staffId) {
                $basketId = null;
                if (! empty($data['basket_code']) && $data['transaction_type'] === 'drop_off') {
                    $basket = BasketTag::query()->where('code', $data['basket_code'])->first();
                    if ($basket) {
                        $holder = LaundryTransaction::query()
                            ->with('customer:id,name')
                            ->where('basket_tag_id', $basket->id)
                            ->whereIn('status', ['pending', 'processing', 'ready_for_pickup'])
                            ->first();
                        if ($holder) {
                            throw ValidationException::withMessages([
                                'basket_code' => "Basket {$basket->code} is already in use by ".($holder->customer?->name ?? 'another customer').'. Pick another basket.',
                            ]);
                        }
                        $basket->update(['status' => 'in_use']);
                        $basketId = $basket->id;
                    }
                }

                $detergentQty = (int) ($data['detergent_quantity'] ?? 0);
                $detergentId = $data['detergent_item_id'] ?? null;
                if ($detergentQty <= 0) {
                    $detergentId = null;
                }

                // Never trust client-side prices: flat services use the stored price,
                // per-kg self-service must match a known rate × billable kg.
                $subtotal = (float) $data['service_amount'];
                if ($data['transaction_type'] === 'drop_off' && ! empty($data['service_id'])) {
                    $subtotal = (float) Service::query()->whereKey($data['service_id'])->value('base_price');
                } elseif ($data['transaction_type'] === 'self_service') {
                    $billableKg = max((float) $data['load_weight_kg'], 3);
                    $validRate = collect([20, 25, 40])->contains(
                        fn ($rate) => abs($rate * $billableKg - $subtotal) < 0.01
                    );
                    if (! $validRate) {
                        throw ValidationException::withMessages([
                            'service_amount' => 'Service charge does not match the load weight. Please refresh and try again.',
                        ]);
                    }
                }
                foreach ($data['items'] ?? [] as $line) {
                    $item = InventoryItem::query()->find($line['inventory_item_id']);
                    if ($item) {
                        $subtotal += (float) $item->unit_price * (int) $line['quantity'];
                    }
                }
                if ($detergentId && $detergentQty > 0) {
                    $det = InventoryItem::query()->find($detergentId);
                    if ($det) {
                        $subtotal += (float) $det->unit_price * $detergentQty;
                    }
                }
                if (abs($subtotal - $total) > 0.01) {
                    throw ValidationException::withMessages([
                        'total_amount' => 'Total does not match current prices. Please refresh and try again.',
                    ]);
                }

                $txn = LaundryTransaction::query()->create([
                    'customer_id' => $customer->id,
                    'basket_tag_id' => $basketId,
                    'machine_id' => $data['machine_id'] ?? null,
                    'machine_time_slot_id' => null,
                    'service_id' => $data['service_id'] ?? null,
                    'detergent_item_id' => $detergentId,
                    'detergent_quantity' => $detergentQty,
                    'handled_by' => $staffId,
                    'transaction_type' => $data['transaction_type'],
                    'status' => $data['transaction_type'] === 'self_service' ? 'processing' : 'pending',
                    'payment_status' => 'paid',
                    'subtotal' => $subtotal,
                    'total_amount' => $total,
                    'cash_tendered' => $cash,
                    'change_given' => $cash - $total,
                    'notes' => $data['notes'] ?? null,
                    'load_weight_kg' => $data['load_weight_kg'] ?? null,
                    'cycle_minutes' => $data['cycle_minutes'] ?? null,
                ]);

                foreach ($data['garments'] ?? [] as $g) {
                    if ((int) $g['quantity'] <= 0) {
                        continue;
                    }
                    $type = GarmentType::query()->firstOrCreate(
                        ['name' => $g['name']],
                        ['is_custom' => true]
                    );
                    TransactionGarment::query()->updateOrCreate(
                        [
                            'laundry_transaction_id' => $txn->id,
                            'garment_type_id' => $type->id,
                        ],
                        ['quantity' => (int) $g['quantity']]
                    );
                }

                foreach ($data['items'] ?? [] as $line) {
                    $item = InventoryItem::query()->lockForUpdate()->find($line['inventory_item_id']);
                    if (! $item) {
                        continue;
                    }
                    $qty = (int) $line['quantity'];
                    if ($item->quantity_on_hand < $qty) {
                        throw ValidationException::withMessages([
                            'items' => "Not enough stock for {$item->name} ({$item->quantity_on_hand} left).",
                        ]);
                    }
                    $unit = (float) $item->unit_price;
                    TransactionItem::query()->create([
                        'laundry_transaction_id' => $txn->id,
                        'inventory_item_id' => $item->id,
                        'quantity' => $qty,
                        'unit_price' => $unit,
                        'line_total' => $unit * $qty,
                    ]);
                    $item->decrement('quantity_on_hand', $qty);
                }

                if ($detergentId && $detergentQty > 0) {
                    $det = InventoryItem::query()->lockForUpdate()->find($detergentId);
                    if ($det) {
                        if ($det->quantity_on_hand < $detergentQty) {
                            throw ValidationException::withMessages([
                                'detergent_quantity' => "Not enough stock for {$det->name} ({$det->quantity_on_hand} left).",
                            ]);
                        }
                        $det->decrement('quantity_on_hand', $detergentQty);
                    }
                }

                TransactionStatusLog::query()->create([
                    'laundry_transaction_id' => $txn->id,
                    'status' => $txn->status,
                    'changed_by' => $staffId,
                    'changed_at' => now(),
                ]);

                FinanceTransaction::query()->create([
                    'type' => 'income',
                    'description' => 'Laundry order #'.$txn->id,
                    'amount' => $total,
                    'staff_id' => $staffId,
                    'laundry_transaction_id' => $txn->id,
                    'transaction_date' => today(),
                ]);

                return $txn->load(['customer', 'basketTag', 'service', 'inventoryItems', 'garmentTypes']);
            });
        } catch (\Throwable $e) {
            // The order was NOT saved (e.g. not enough stock): let the cashier fix it and retry.
            if ($tokenKey) {
                Cache::forget($tokenKey);
            }

            throw $e;
        }

        $this->audit('order.created', ['order_id' => $tx->id, 'total' => (float) $tx->total_amount, 'type' => $tx->transaction_type]);

        $notifyUrl = app(FcmService::class)->webConfig() !== null
            ? URL::temporarySignedRoute('notify.show', now()->addDays(14), ['transaction' => $tx->id])
            : null;

        return response()->json([
            'notify_url' => $notifyUrl,
            'message' => 'Transaction saved',
            'transaction' => $tx,
            'change_given' => (float) $tx->change_given,
        ]);
    }

    public function updateStatus(Request $request, LaundryTransaction $transaction): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['pending', 'processing', 'ready_for_pickup', 'claimed', 'cancelled'])],
        ]);

        if ($transaction->status === 'claimed') {
            return response()->json(['message' => 'This order is already claimed and cannot be changed.'], 422);
        }

        if ($data['status'] === 'cancelled') {
            abort_unless(Gate::allows('cancel', $transaction), 403, 'Only an admin can cancel an order.');

            return response()->json(['message' => 'Use Cancel order in the admin console so stock and sales are corrected.'], 422);
        }

        if ($data['status'] === $transaction->status) {
            return response()->json(['message' => 'Already up to date.', 'transaction' => $transaction, 'notify' => null]);
        }

        $allowed = [
            'pending' => ['processing'],
            'processing' => ['pending', 'ready_for_pickup'],
            'ready_for_pickup' => ['processing', 'claimed'],
            'cancelled' => [],
        ];
        if (! in_array($data['status'], $allowed[$transaction->status] ?? [], true)) {
            return response()->json(['message' => "An order cannot move from {$transaction->status} to {$data['status']}."], 422);
        }
        $previousStatus = $transaction->status;

        $staffId = $this->staffId();

        DB::transaction(function () use ($transaction, $data, $staffId) {
            $transaction->update(['status' => $data['status']]);
            TransactionStatusLog::query()->create([
                'laundry_transaction_id' => $transaction->id,
                'status' => $data['status'],
                'changed_by' => $staffId,
                'changed_at' => now(),
            ]);

            if ($data['status'] === 'claimed' && $transaction->basket_tag_id) {
                $stillHeld = LaundryTransaction::query()
                    ->where('basket_tag_id', $transaction->basket_tag_id)
                    ->where('id', '!=', $transaction->id)
                    ->whereIn('status', ['pending', 'processing', 'ready_for_pickup'])
                    ->exists();
                if (! $stillHeld) {
                    BasketTag::query()->where('id', $transaction->basket_tag_id)->update(['status' => 'available']);
                }
            }
        });

        $this->audit('order.status_changed', ['order_id' => $transaction->id, 'from' => $previousStatus, 'to' => $data['status']]);

        $notify = $data['status'] === 'ready_for_pickup'
            ? $this->notifyCustomerReady($transaction->fresh(['customer', 'basketTag']))
            : null;

        return response()->json(['message' => 'Status updated', 'transaction' => $transaction->fresh(), 'notify' => $notify]);
    }

    /**
     * Tell the customer their laundry is ready (email + SMS). A failure here never blocks the
     * status change; each channel reports sent | logged | skipped | failed.
     *
     * @return array{email: string, sms: string, push: string}
     */
    protected function notifyCustomerReady(LaundryTransaction $transaction): array
    {
        $customer = $transaction->customer;
        $result = ['email' => 'skipped', 'sms' => 'skipped', 'push' => 'skipped'];

        if ($customer?->email && ! $transaction->email_sent_at) {
            try {
                Mail::to($customer->email)->send(new LaundryReadyMail($transaction));
                $live = ! in_array(config('mail.default'), ['log', 'array'], true);
                $result['email'] = $live ? 'sent' : 'logged';
                if ($live) {
                    $transaction->update(['email_sent_at' => now()]);
                }
            } catch (\Throwable $e) {
                Log::warning('Ready-for-pickup email failed', ['order_id' => $transaction->id, 'error' => $e->getMessage()]);
                $result['email'] = 'failed';
            }
        }

        if ($customer?->contact_number && ! $transaction->sms_sent_at) {
            $sms = app(SmsService::class);
            $code = $transaction->basketTag?->code ?? '#'.$transaction->id;
            $ok = $sms->send($customer->contact_number, "SSK Laba Dami: Hi {$customer->name}, your laundry (basket {$code}) is ready for pickup.");
            $result['sms'] = ! $ok ? 'failed' : ($sms->isLive() ? 'sent' : 'logged');
            if ($ok && $sms->isLive()) {
                $transaction->update(['sms_sent_at' => now()]);
            }
        }

        $pushToken = Cache::get("fcm:order:{$transaction->id}");
        if ($pushToken) {
            $fcm = app(FcmService::class);
            if (! $fcm->isConfigured()) {
                $result['push'] = 'logged';
            } else {
                $code = $transaction->basketTag?->code ?? '#'.$transaction->id;
                $outcome = $fcm->send($pushToken, 'Your laundry is ready', "Order {$code} is ready for pickup at SSK Laba Dami.", url('/'));
                $result['push'] = $outcome === 'sent' ? 'sent' : 'failed';
                if ($outcome !== 'failed') {
                    Cache::forget("fcm:order:{$transaction->id}");
                }
            }
        }

        $this->audit('order.customer_notified', ['order_id' => $transaction->id, ...$result]);

        return $result;
    }

    public function reassignBasket(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string'],
        ]);

        $basket = BasketTag::query()->where('code', $data['code'])->firstOrFail();
        if ($basket->status !== 'available') {
            return response()->json(['message' => 'Basket is not available.'], 422);
        }

        return response()->json(['basket' => $basket]);
    }

    public function addGarmentType(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
        ]);

        $type = GarmentType::query()->firstOrCreate(
            ['name' => $data['name']],
            ['is_custom' => true]
        );

        return response()->json(['garment_type' => $type]);
    }

    public function clockIn(): JsonResponse
    {
        $staffId = $this->staffId();
        if (! $staffId) {
            return response()->json(['message' => 'No staff record linked to this user email.'], 422);
        }

        $row = StaffAttendance::query()->firstOrCreate(
            [
                'staff_id' => $staffId,
                'work_date' => today()->toDateString(),
            ],
            [
                'clock_in' => now(),
            ]
        );

        if ($row->clock_out) {
            return response()->json(['message' => 'Already completed attendance for today.', 'attendance' => $row], 422);
        }

        if (! $row->wasRecentlyCreated && $row->clock_in) {
            return response()->json(['message' => 'Already clocked in.', 'attendance' => $row]);
        }

        return response()->json(['message' => 'Clocked in', 'attendance' => $row]);
    }

    public function clockOut(): JsonResponse
    {
        $staffId = $this->staffId();
        if (! $staffId) {
            return response()->json(['message' => 'No staff record linked to this user email.'], 422);
        }

        $row = StaffAttendance::query()
            ->where('staff_id', $staffId)
            ->whereDate('work_date', today())
            ->first();

        if (! $row || ! $row->clock_in) {
            return response()->json(['message' => 'Clock in first.'], 422);
        }
        if ($row->clock_out) {
            return response()->json(['message' => 'Already clocked out.', 'attendance' => $row]);
        }

        $row->update(['clock_out' => now()]);

        return response()->json(['message' => 'Clocked out', 'attendance' => $row->fresh()]);
    }

    public function restockItem(Request $request): JsonResponse
    {
        $data = $request->validate([
            'inventory_item_id' => ['required', 'integer', 'exists:inventory_items,id'],
            'quantity_received' => ['required', 'integer', 'min:1'],
            'supplier' => ['nullable', 'string', 'max:120'],
        ]);

        $staffId = $this->staffId();

        $item = DB::transaction(function () use ($data, $staffId) {
            $item = InventoryItem::query()->lockForUpdate()->findOrFail($data['inventory_item_id']);
            $item->increment('quantity_on_hand', $data['quantity_received']);
            if (($item->status ?? 'active') !== 'active') {
                $item->update(['status' => 'active']);
            }

            InventoryRestock::query()->create([
                'inventory_item_id' => $item->id,
                'staff_id' => $staffId,
                'quantity_received' => $data['quantity_received'],
                'supplier' => $data['supplier'] ?? null,
                'restocked_at' => today(),
            ]);

            return $item->fresh('category');
        });

        return response()->json(['message' => 'Restocked', 'item' => $item]);
    }

    public function archiveItem(Request $request, InventoryItem $item): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'in:expired,spoiled,damaged'],
            'quantity' => ['nullable', 'integer', 'min:0'],
        ]);

        $staffId = $this->staffId();
        $status = 'archived_'.$data['reason'];

        DB::transaction(function () use ($item, $data, $staffId, $status) {
            $qty = $data['quantity'] ?? $item->quantity_on_hand;
            if ($qty > 0 && $qty <= $item->quantity_on_hand) {
                $item->decrement('quantity_on_hand', $qty);
                InventoryAdjustment::query()->create([
                    'inventory_item_id' => $item->id,
                    'staff_id' => $staffId,
                    'quantity_change' => -$qty,
                    'reason' => ucfirst($data['reason']).' / archived',
                ]);
            }
            if ($item->fresh()->quantity_on_hand <= 0) {
                $item->update(['status' => $status, 'quantity_on_hand' => 0]);
            } else {
                // partial archive still logs; keep active if stock remains
                $item->update(['status' => 'active']);
            }
        });

        $this->audit('inventory.archived', ['item_id' => $item->id, 'reason' => $data['reason'], 'quantity' => $data['quantity'] ?? null]);

        return response()->json(['message' => 'Item archived', 'item' => $item->fresh('category')]);
    }

    /** Staff flags a low-stock item so it shows in the admin alerts panel. */
    public function notifyLowStock(InventoryItem $item): JsonResponse
    {
        if ($item->status !== 'active' || ! $item->isLowStock()) {
            return response()->json(['message' => 'This item is not low on stock.'], 422);
        }

        $alreadyReported = Notification::query()
            ->where('type', 'low_stock')
            ->where('is_read', false)
            ->where('notifiable_type', InventoryItem::class)
            ->where('notifiable_id', $item->id)
            ->exists();

        if (! $alreadyReported) {
            Notification::query()->create([
                'type' => 'low_stock',
                'message' => "{$item->name} is low: {$item->quantity_on_hand} {$item->unit} left (reported by ".Auth::user()->name.')',
                'notifiable_type' => InventoryItem::class,
                'notifiable_id' => $item->id,
                'is_read' => false,
            ]);
            $this->audit('inventory.low_stock_reported', ['item_id' => $item->id]);
        }

        return response()->json(['message' => $alreadyReported ? 'Admin was already notified about this item.' : 'Admin notified.']);
    }

    /**
     * Give each order still waiting for pickup its signed "notify me" link (shown as a QR on the receipt).
     *
     * @param  Collection<int, LaundryTransaction>  $orders
     */
    protected function withNotifyUrls($orders)
    {
        if (app(FcmService::class)->webConfig() === null) {
            return $orders;
        }

        return $orders->each(function (LaundryTransaction $order) {
            if ($order->transaction_type === 'drop_off' && in_array($order->status, ['pending', 'processing', 'ready_for_pickup'], true)) {
                $order->setAttribute('notify_url', URL::temporarySignedRoute('notify.show', now()->addDays(14), ['transaction' => $order->id]));
            }
        });
    }

    /** Text the customer a short receipt through the configured SMS provider. */
    public function sendReceiptSms(LaundryTransaction $transaction): JsonResponse
    {
        $phone = $transaction->customer?->contact_number;
        if (blank($phone)) {
            return response()->json(['message' => 'This customer has no phone number on file.'], 422);
        }

        $sms = app(SmsService::class);
        $shop = config('shop.name');
        $ok = $sms->send($phone, "{$shop} receipt #{$transaction->id}: ₱".number_format((float) $transaction->total_amount, 2).'. Thank you!');
        $result = ! $ok ? 'failed' : ($sms->isLive() ? 'sent' : 'logged');
        $this->audit('order.receipt_sms', ['order_id' => $transaction->id, 'result' => $result]);

        return response()->json(['result' => $result]);
    }

    /** Completed (claimed) orders, newest first. */
    public function history(): JsonResponse
    {
        $orders = LaundryTransaction::query()
            ->with([
                'customer:id,name,contact_number',
                'basketTag:id,code',
                'service:id,name',
                'detergent:id,name,unit,unit_price',
                'handledBy:id,name',
                'garmentTypes:id,name',
                'inventoryItems:id,name,unit',
                'statusLogs' => fn ($q) => $q->where('status', 'claimed'),
            ])
            ->where('status', 'claimed')
            ->latest('updated_at')
            ->limit(200)
            ->get();

        return response()->json(['orders' => $this->withNotifyUrls($orders)]);
    }

    public function updateMachineStatus(Request $request, Machine $machine): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:available,in_use,reserved,maintenance,out_of_service'],
        ]);
        $before = $machine->status;
        $machine->update(['status' => $data['status']]);
        $this->audit('machine.status_changed', ['machine_id' => $machine->id, 'from' => $before, 'to' => $data['status']]);

        return response()->json(['message' => 'Machine updated', 'machine' => $machine]);
    }

    public function storeCustomer(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'contact_number' => ['required', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:190'],
            'address' => ['nullable', 'string', 'max:255'],
        ]);

        $customer = Customer::query()->create($data);

        return response()->json([
            'message' => 'Customer created',
            'customer' => $customer,
            'start_transaction' => true,
        ], 201);
    }

    public function updateCustomer(Request $request, Customer $customer): JsonResponse
    {
        // Design: regular edit is limited to phone/SMS and email only
        $data = $request->validate([
            'contact_number' => ['required', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:190'],
        ]);

        $customer->update($data);

        return response()->json([
            'message' => 'Customer updated',
            'customer' => $customer->fresh(),
        ]);
    }

    public function storeBasket(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:20', 'unique:basket_tags,code'],
        ]);

        $code = strtoupper(trim($data['code']));
        if (! str_starts_with($code, '#')) {
            $code = '#'.$code;
        }
        // normalize #14 -> #014 style optional: keep user format if already padded
        if (preg_match('/^#(\d+)$/', $code, $m)) {
            $code = '#'.str_pad($m[1], 3, '0', STR_PAD_LEFT);
        }

        // re-check unique after normalize
        if (BasketTag::query()->where('code', $code)->exists()) {
            return response()->json(['message' => "Basket {$code} already exists."], 422);
        }

        $basket = BasketTag::query()->create([
            'code' => $code,
            'status' => 'available',
        ]);

        return response()->json([
            'message' => 'Basket added',
            'basket' => $basket,
        ], 201);
    }
}
