<?php

use App\Http\Controllers\AdminApiController;
use App\Http\Controllers\StaffApiController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::get('dashboard', function () {
    /** @var \App\Models\User|null $user */
    $user = Auth::user();
    $role = $user->role ?? 'staff';

    if ($role === 'admin') {
        return redirect()->route('admin.dashboard');
    }

    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware(['auth', 'verified'])->prefix('admin')->group(function () {
    Route::get('/', function () {
        /** @var \App\Models\User|null $user */
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
Route::middleware(['auth', 'verified'])->prefix('api/staff')->group(function () {
    Route::get('bootstrap', [StaffApiController::class, 'bootstrap']);
    Route::post('transactions', [StaffApiController::class, 'saveTransaction']);
    Route::patch('transactions/{transaction}/status', [StaffApiController::class, 'updateStatus']);
    Route::post('baskets/check', [StaffApiController::class, 'reassignBasket']);
    Route::post('garment-types', [StaffApiController::class, 'addGarmentType']);
    Route::post('attendance/clock-in', [StaffApiController::class, 'clockIn']);
    Route::post('attendance/clock-out', [StaffApiController::class, 'clockOut']);
    Route::post('customers', [StaffApiController::class, 'storeCustomer']);
    Route::patch('customers/{customer}', [StaffApiController::class, 'updateCustomer']);
    Route::post('inventory/restock', [StaffApiController::class, 'restockItem']);
    Route::post('inventory/{item}/archive', [StaffApiController::class, 'archiveItem']);
    Route::patch('machines/{machine}/status', [StaffApiController::class, 'updateMachineStatus']);
});

/*
| JSON API — admin
*/
Route::middleware(['auth', 'verified'])->prefix('api/admin')->group(function () {
    Route::get('bootstrap', [AdminApiController::class, 'bootstrap']);
    Route::post('inventory', [AdminApiController::class, 'storeInventoryItem']);
    Route::post('inventory/{item}/adjust', [AdminApiController::class, 'adjustInventory']);
    Route::post('procurement', [AdminApiController::class, 'storeRestock']);
    Route::post('users', [AdminApiController::class, 'storeUser']);
    Route::patch('users/{user}', [AdminApiController::class, 'updateUser']);
});

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Volt::route('settings/profile', 'settings.profile')->name('settings.profile');
    Volt::route('settings/password', 'settings.password')->name('settings.password');
    Volt::route('settings/appearance', 'settings.appearance')->name('settings.appearance');
});

require __DIR__.'/auth.php';
