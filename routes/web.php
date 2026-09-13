<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\CardAdvanceController;
use App\Http\Controllers\CardChargeController;
use App\Http\Controllers\CardCreditAllocationController;
use App\Http\Controllers\CardPaymentController;
use App\Http\Controllers\CardPurchaseController;
use App\Http\Controllers\CardPurchaseReversalController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CreditCardController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExpenseRefundController;
use App\Http\Controllers\FinancialEvaluationController;
use App\Http\Controllers\FinancialSettingsController;
use App\Http\Controllers\InternalAlertController;
use App\Http\Controllers\LedgerEntryController;
use App\Http\Controllers\LedgerEntryReversalController;
use App\Http\Controllers\PocketController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReceiptForecastController;
use App\Http\Controllers\ReceiptForecastReceiptController;
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
    Route::get('/cartoes', [CreditCardController::class, 'index'])->name('credit-cards.index');
    Route::post('/cartoes', [CreditCardController::class, 'store'])->name('credit-cards.store');
    Route::post('/cartoes/compras', [CardPurchaseController::class, 'store'])->name('card-purchases.store');
    Route::post('/cartoes/compras/estornos', [CardPurchaseReversalController::class, 'store'])->name('card-purchase-reversals.store');
    Route::post('/cartoes/creditos/aplicacoes', [CardCreditAllocationController::class, 'store'])->name('card-credit-allocations.store');
    Route::post('/cartoes/encargos', [CardChargeController::class, 'store'])->name('card-charges.store');
    Route::post('/cartoes/pagamentos', [CardPaymentController::class, 'store'])->name('card-payments.store');
    Route::post('/cartoes/antecipacoes', [CardAdvanceController::class, 'store'])->name('card-advances.store');
    Route::get('/recebimentos-previstos', [ReceiptForecastController::class, 'index'])->name('receipt-forecasts.index');
    Route::post('/recebimentos-previstos', [ReceiptForecastController::class, 'store'])->name('receipt-forecasts.store');
    Route::put('/recebimentos-previstos/{forecast}', [ReceiptForecastController::class, 'update'])->whereNumber('forecast')->name('receipt-forecasts.update');
    Route::put('/recebimentos-previstos/{forecast}/cancelar', [ReceiptForecastController::class, 'cancel'])->whereNumber('forecast')->name('receipt-forecasts.cancel');
    Route::post('/recebimentos-previstos/{forecast}/recebimentos', [ReceiptForecastReceiptController::class, 'store'])->whereNumber('forecast')->name('receipt-forecasts.receipts.store');
    Route::get('/configuracao-financeira', [FinancialSettingsController::class, 'edit'])->name('financial-settings.edit');
    Route::get('/historico-financeiro', [FinancialEvaluationController::class, 'index'])->name('financial-evaluations.index');
    Route::get('/avisos-financeiros', [InternalAlertController::class, 'index'])->name('internal-alerts.index');
    Route::put('/configuracao-financeira', [FinancialSettingsController::class, 'update'])->name('financial-settings.update');
    Route::post('/configuracao-financeira/categorias', [FinancialSettingsController::class, 'storeCategory'])->name('financial-settings.categories.store');
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
    Route::post('/reembolsos', [ExpenseRefundController::class, 'store'])->name('expense-refunds.store');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
});

require __DIR__.'/auth.php';
