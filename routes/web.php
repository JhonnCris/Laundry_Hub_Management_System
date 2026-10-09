<?php

use App\Http\Controllers\AdminApiController;
use App\Http\Controllers\BillsApiController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\NotifyController;
use App\Http\Controllers\StaffApiController;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::get('/', function () {
    return view('welcome');
})->name('home');

// Customer opt-in for the "ready for pickup" push (signed link per order, no login).
Route::get('firebase-messaging-sw.js', [NotifyController::class, 'serviceWorker']);
Route::middleware(['signed', 'throttle:30,1'])->group(function () {
    Route::get('notify/{transaction}', [NotifyController::class, 'show'])->name('notify.show');
    Route::post('notify/{transaction}', [NotifyController::class, 'subscribe'])->name('notify.subscribe');
    Route::post('notify/{transaction}/test', [NotifyController::class, 'test'])->name('notify.test');
});

Route::get('dashboard', function () {
    /** @var User|null $user */
    $user = Auth::user();
    $role = $user->role ?? 'staff';

    if ($role === 'admin') {
        return redirect()->route('admin.dashboard');
    }

    if (in_array($role, ['pending', 'rejected'], true)) {
        return view('pending-approval');
    }

    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware(['auth', 'verified'])->prefix('admin')->group(function () {
    Route::get('/', function () {
        /** @var User|null $user */
        $user = Auth::user();
        $role = $user->role ?? 'staff';

        if ($role !== 'admin') {
            return redirect()->route('dashboard');
        }

        return view('admin');
    })->name('admin.dashboard');
});

/*
| JSON API — staff operations
*/
Route::middleware(['auth', 'verified', 'staff.access', 'throttle:120,1'])->prefix('ajax/staff')->group(function () {
    Route::get('bootstrap', [StaffApiController::class, 'bootstrap']);
    Route::post('transactions', [StaffApiController::class, 'saveTransaction']);
    Route::patch('transactions/{transaction}/status', [StaffApiController::class, 'updateStatus']);
    Route::post('transactions/{transaction}/cancel', [StaffApiController::class, 'cancelTransaction']);
    Route::post('baskets', [StaffApiController::class, 'storeBasket']);
    Route::post('attendance/clock-in', [StaffApiController::class, 'clockIn']);
    Route::post('attendance/clock-out', [StaffApiController::class, 'clockOut']);
    Route::post('customers', [StaffApiController::class, 'storeCustomer']);
    Route::patch('customers/{customer}', [StaffApiController::class, 'updateCustomer']);
    Route::post('inventory/{item}/archive', [StaffApiController::class, 'archiveItem']);
    Route::post('inventory/{item}/notify-low', [StaffApiController::class, 'notifyLowStock']);
    Route::get('history', [StaffApiController::class, 'history']);
    Route::post('transactions/{transaction}/receipt-email', [StaffApiController::class, 'sendReceiptEmail']);
    Route::patch('machines/{machine}/status', [StaffApiController::class, 'updateMachineStatus']);
});

/*
| JSON API — admin
*/
Route::middleware(['auth', 'verified', 'can:admin', 'throttle:120,1'])->prefix('ajax/admin')->group(function () {
    Route::get('bootstrap', [AdminApiController::class, 'bootstrap']);
    Route::get('transactions/{transaction}', [AdminApiController::class, 'showTransaction']);
    Route::post('exports', [AdminApiController::class, 'logExport']);
    Route::post('notifications/{notification}/read', [AdminApiController::class, 'readNotification']);
    Route::post('inventory', [AdminApiController::class, 'storeInventoryItem']);
    Route::patch('inventory/{item}', [AdminApiController::class, 'updateInventoryItem']);
    Route::get('bills', [BillsApiController::class, 'index']);
    Route::post('bills', [BillsApiController::class, 'storeBill']);
    Route::patch('bills/{bill}', [BillsApiController::class, 'updateBill']);
    Route::post('bill-statements/{statement}/payments', [BillsApiController::class, 'storePayment']);
    Route::post('expenses', [BillsApiController::class, 'storeExpense']);
    Route::post('expenses/{expense}/void', [BillsApiController::class, 'voidExpense']);
    Route::post('categories', [AdminApiController::class, 'storeCategory']);
    Route::patch('categories/{category}', [AdminApiController::class, 'updateCategory']);
    Route::delete('categories/{category}', [AdminApiController::class, 'destroyCategory']);
    Route::post('machine-rates', [AdminApiController::class, 'saveMachineRate']);
    Route::delete('machine-rates/{rate}', [AdminApiController::class, 'destroyMachineRate']);
    Route::post('procurement', [AdminApiController::class, 'storeRestock']);
    Route::post('procurement/{restock}/stock', [AdminApiController::class, 'stockRestock']);
    Route::post('services', [AdminApiController::class, 'storeService']);
    Route::patch('services/{service}', [AdminApiController::class, 'updateService']);
    Route::post('machines', [AdminApiController::class, 'storeMachine']);
    Route::patch('machines/{machine}', [AdminApiController::class, 'updateMachine']);
    Route::delete('machines/{machine}', [AdminApiController::class, 'destroyMachine']);
    Route::post('procurement/{restock}/void', [AdminApiController::class, 'voidRestock']);
    Route::get('audit', [AdminApiController::class, 'auditLog']);
    Route::get('report', [AdminApiController::class, 'reportData']);
    Route::post('users/{user}/reject', [AdminApiController::class, 'rejectUser']);
    Route::post('users', [AdminApiController::class, 'storeUser']);
    Route::patch('users/{user}', [AdminApiController::class, 'updateUser']);
});

Route::post('ajax/locale', LocaleController::class)->middleware(['auth', 'throttle:30,1']);

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Volt::route('settings/profile', 'settings.profile')->name('settings.profile');
    Volt::route('settings/password', 'settings.password')->name('settings.password');
    Volt::route('settings/appearance', 'settings.appearance')->name('settings.appearance');
});

require __DIR__.'/auth.php';
