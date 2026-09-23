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
use App\Services\SmsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

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
        $customers = Customer::query()->orderBy('name')->get(['id', 'name', 'contact_number']);

        $activeLaundry = LaundryTransaction::query()
            ->with(['customer:id,name', 'basketTag:id,code', 'service:id,name', 'machine:id,name'])
            ->whereIn('status', ['pending', 'processing', 'ready_for_pickup'])
            ->latest()
            ->limit(50)
            ->get();

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
            $tx = LaundryTransaction::query()->where('customer_id', $c->id);

            return [
                'id' => $c->id,
                'name' => $c->name,
                'contact_number' => $c->contact_number,
                'orders_count' => (clone $tx)->count(),
                'last_service' => optional((clone $tx)->latest('created_at')->first())->created_at,
            ];
        });

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
            'baskets' => $baskets,
            'available_baskets' => $baskets->where('status', 'available')->values(),
            'inventory' => $activeInventory->values(),
            'archived_inventory' => $archivedInventory->values(),
            'snacks' => $snacks,
            'detergents' => $detergents,
            'customers' => $customersWithStats,
            'active_laundry' => $activeLaundry,
            'attendance_today' => $attendance,
            'attendance_recent' => $recentAttendance,
        ]);
    }

    public function saveTransaction(Request $request): JsonResponse
    {
        $data = $request->validate([
            'first_name' => ['nullable', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'contact_number' => ['nullable', 'string', 'max:40'],
            'contact_email' => ['nullable', 'email', 'max:150'],
            'transaction_type' => ['required', Rule::in(['drop_off', 'self_service'])],
            'basket_code' => ['nullable', 'string', 'max:20'],
            'service_id' => ['nullable', 'integer', 'exists:services,id'],
            'service_amount' => ['required', 'numeric', 'min:0'],
            'machine_id' => ['nullable', 'integer', 'exists:machines,id'],
            'load_weight_kg' => ['nullable', 'numeric', 'min:0'],
            'cycle_minutes' => ['nullable', 'integer', 'min:0'],
            'detergent_item_id' => ['nullable', 'integer', 'exists:inventory_items,id'],
            'detergent_quantity' => ['nullable', 'integer', 'min:0'],
            'garments' => ['nullable', 'array'],
            'garments.*.name' => ['required_with:garments', 'string', 'max:80'],
            'garments.*.quantity' => ['required_with:garments', 'integer', 'min:1'],
            'items' => ['nullable', 'array'],
            'items.*.inventory_item_id' => ['required_with:items', 'integer', 'exists:inventory_items,id'],
            'items.*.quantity' => ['required_with:items', 'integer', 'min:1'],
            'notes' => ['nullable', 'string'],
            'cash_tendered' => ['required', 'numeric', 'min:0'],
            'total_amount' => ['required', 'numeric', 'min:0'],
        ]);

        $name = trim(implode(' ', array_filter([
            $data['first_name'] ?? '',
            $data['middle_name'] ?? '',
            $data['last_name'] ?? '',
        ])));

        if ($data['transaction_type'] === 'drop_off' && $name === '') {
            return response()->json(['message' => 'Customer first and last name are required for drop-off.'], 422);
        }

        if ($data['transaction_type'] === 'self_service' && (float) ($data['load_weight_kg'] ?? 0) <= 0) {
            return response()->json(['message' => 'Load weight (kg) is required for self-service.'], 422);
        }

        $cash = (float) $data['cash_tendered'];
        $total = (float) $data['total_amount'];
        if ($cash + 0.001 < $total) {
            return response()->json(['message' => 'Cash tendered is less than total amount.'], 422);
        }

        $staffId = $this->staffId();

        $tx = DB::transaction(function () use ($data, $name, $cash, $total, $staffId) {
            $customer = null;
            if ($name !== '') {
                $customer = Customer::query()->firstOrCreate(
                    [
                        'name' => $name,
                        'contact_number' => $data['contact_number'] ?? null,
                    ],
                    ['email' => $data['contact_email'] ?? null]
                );
            } else {
                $customer = Customer::query()->firstOrCreate(
                    ['name' => 'Walk-in'],
                    ['contact_number' => $data['contact_number'] ?? null, 'email' => $data['contact_email'] ?? null]
                );
            }

            if (! empty($data['contact_email']) && blank($customer->email)) {
                $customer->update(['email' => $data['contact_email']]);
            }

            $basketId = null;
            if (! empty($data['basket_code']) && $data['transaction_type'] === 'drop_off') {
                $basket = BasketTag::query()->where('code', $data['basket_code'])->first();
                if ($basket) {
                    $basket->update(['status' => 'in_use']);
                    $basketId = $basket->id;
                }
            }

            $detergentQty = (int) ($data['detergent_quantity'] ?? 0);
            $detergentId = $data['detergent_item_id'] ?? null;
            if ($detergentQty <= 0) {
                $detergentId = null;
            }

            $subtotal = (float) $data['service_amount'];
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

        return response()->json([
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

        if ($data['status'] === 'claimed' && (! $transaction->email_sent_at || ! $transaction->sms_sent_at)) {
            return response()->json(['message' => 'Send the email and SMS notification to the customer before marking this order claimed.'], 422);
        }

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
                BasketTag::query()->where('id', $transaction->basket_tag_id)->update(['status' => 'available']);
            }
        });

        return response()->json(['message' => 'Status updated', 'transaction' => $transaction->fresh()]);
    }

    public function notifyEmail(LaundryTransaction $transaction): JsonResponse
    {
        $transaction->loadMissing('customer', 'basketTag');
        $email = $transaction->customer?->email;

        if (blank($email)) {
            return response()->json(['message' => 'This customer has no email on file.'], 422);
        }

        Mail::to($email)->send(new LaundryReadyMail($transaction));

        $transaction->update(['email_sent_at' => now()]);

        Notification::query()->create([
            'type' => 'laundry_ready',
            'message' => 'Ready-for-pickup email sent to '.$email.' for basket '.($transaction->basketTag?->code ?? ('#'.$transaction->id)),
            'notifiable_type' => LaundryTransaction::class,
            'notifiable_id' => $transaction->id,
            'is_read' => false,
        ]);

        return response()->json(['message' => 'Email sent', 'transaction' => $transaction->fresh()]);
    }

    public function notifySms(Request $request, LaundryTransaction $transaction, SmsService $sms): JsonResponse
    {
        $transaction->loadMissing('customer', 'basketTag');
        $contact = $transaction->customer?->contact_number;

        if (blank($contact)) {
            return response()->json(['message' => 'This customer has no contact number on file.'], 422);
        }

        $basket = $transaction->basketTag?->code ?? ('#'.$transaction->id);
        $sent = $sms->send($contact, "SSK Laba Dami: Your laundry (basket {$basket}) is ready for pickup.");

        if (! $sent) {
            return response()->json(['message' => 'Could not send SMS.'], 422);
        }

        $transaction->update(['sms_sent_at' => now()]);

        Notification::query()->create([
            'type' => 'laundry_ready',
            'message' => 'Ready-for-pickup SMS sent to '.$contact.' for basket '.$basket,
            'notifiable_type' => LaundryTransaction::class,
            'notifiable_id' => $transaction->id,
            'is_read' => false,
        ]);

        return response()->json(['message' => 'SMS sent', 'transaction' => $transaction->fresh()]);
    }

    public function storeMachine(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'type' => ['required', Rule::in(['washer', 'dryer'])],
        ]);

        $machine = Machine::query()->create([
            'name' => $data['name'],
            'type' => $data['type'],
            'status' => 'available',
        ]);

        return response()->json(['message' => 'Machine added', 'machine' => $machine], 201);
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

        return response()->json(['message' => 'Item archived', 'item' => $item->fresh('category')]);
    }

    public function updateMachineStatus(Request $request, Machine $machine): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:available,in_use,reserved,maintenance,out_of_service'],
        ]);
        $machine->update(['status' => $data['status']]);

        return response()->json(['message' => 'Machine updated', 'machine' => $machine]);
    }
}
