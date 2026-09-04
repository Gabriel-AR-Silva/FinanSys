<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LedgerEntryController;
use App\Http\Controllers\LedgerEntryReversalController;
use App\Http\Controllers\PocketController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RestoredAccountController;
use App\Http\Controllers\RestoredLedgerEntryController;
use App\Http\Controllers\RestoredPocketController;
use App\Http\Controllers\TransferController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
    ]);
})->name('home');

Route::get('/dashboard', DashboardController::class)
    ->middleware('auth')
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::resource('contas', AccountController::class)
        ->parameters(['contas' => 'account'])
        ->only(['index', 'store', 'update', 'destroy'])
        ->names('accounts');
    Route::post('/contas/{account}/restauracao', [RestoredAccountController::class, 'store'])
        ->name('accounts.restore');
    Route::resource('caixinhas', PocketController::class)
        ->parameters(['caixinhas' => 'pocket'])
        ->only(['index', 'store', 'update', 'destroy'])
        ->names('pockets');
    Route::post('/caixinhas/{pocket}/restauracao', [RestoredPocketController::class, 'store'])->name('pockets.restore');
    Route::resource('lancamentos', LedgerEntryController::class)
        ->parameters(['lancamentos' => 'ledgerEntry'])
        ->only(['index', 'store', 'destroy'])
        ->names('ledger-entries');
    Route::resource('categorias', CategoryController::class)
        ->parameters(['categorias' => 'category'])
        ->only(['index', 'store', 'update'])
        ->names('categories');
    Route::patch('/categorias/{category}/status', [CategoryController::class, 'updateStatus'])
        ->name('categories.status.update');
    Route::post('/lancamentos/{ledgerEntry}/restauracao', [RestoredLedgerEntryController::class, 'store'])
        ->name('ledger-entries.restore');
    Route::post('/lancamentos/{ledgerEntry}/estorno', LedgerEntryReversalController::class)
        ->name('ledger-entries.reversals.store');
    Route::post('/transferencias', [TransferController::class, 'store'])->name('transfers.store');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
});

require __DIR__.'/auth.php';
