<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\CardAdvanceController;
use App\Http\Controllers\CardChargeController;
use App\Http\Controllers\CardCorrectionController;
use App\Http\Controllers\CardCreditAllocationController;
use App\Http\Controllers\CardPaymentController;
use App\Http\Controllers\CardPurchaseController;
use App\Http\Controllers\CardPurchaseReversalController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CreditCardController;
use App\Http\Controllers\DailyBudgetController;
use App\Http\Controllers\DailyFinancialCheckInController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExpenseRefundController;
use App\Http\Controllers\FinancialDiagnosticExportController;
use App\Http\Controllers\FinancialEvaluationController;
use App\Http\Controllers\FinancialGoalController;
use App\Http\Controllers\FinancialSettingsController;
use App\Http\Controllers\HelpCenterController;
use App\Http\Controllers\InternalAlertController;
use App\Http\Controllers\LedgerEntryController;
use App\Http\Controllers\LedgerEntryCorrectionController;
use App\Http\Controllers\LedgerEntryReversalController;
use App\Http\Controllers\OfxCardCreditPixConfirmationController;
use App\Http\Controllers\OfxImportConfirmationController;
use App\Http\Controllers\OfxImportController;
use App\Http\Controllers\OfxImportReviewController;
use App\Http\Controllers\OperationalDataResetController;
use App\Http\Controllers\PatrimonialAssetController;
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
    Route::get('/orcamento-diario', [DailyBudgetController::class, 'edit'])->name('daily-budgets.edit');
    Route::post('/orcamento-diario', [DailyBudgetController::class, 'store'])->name('daily-budgets.store');
    Route::post('/check-ins-financeiros', [DailyFinancialCheckInController::class, 'store'])->name('daily-check-ins.store');
    Route::post('/check-ins-financeiros/lote', [DailyFinancialCheckInController::class, 'storeBatch'])->name('daily-check-ins.batch.store');
    Route::patch('/check-ins-financeiros/correcao', [DailyFinancialCheckInController::class, 'correct'])->name('daily-check-ins.correct');
    Route::get('/cartoes', [CreditCardController::class, 'index'])->name('credit-cards.index');
    Route::get('/cartoes/correcoes', [CardCorrectionController::class, 'index'])->name('card-corrections.index');
    Route::post('/cartoes', [CreditCardController::class, 'store'])->name('credit-cards.store');
    Route::patch('/cartoes/{card}/limite', [CreditCardController::class, 'updateLimit'])->whereNumber('card')->name('credit-cards.limit.update');
    Route::delete('/cartoes/{card}', [CreditCardController::class, 'destroy'])->whereNumber('card')->name('credit-cards.destroy');
    Route::post('/cartoes/compras', [CardPurchaseController::class, 'store'])->name('card-purchases.store');
    Route::delete('/cartoes/compras/{purchase}', [CardPurchaseController::class, 'destroy'])->whereNumber('purchase')->name('card-purchases.destroy');
    Route::post('/cartoes/compras/estornos', [CardPurchaseReversalController::class, 'store'])->name('card-purchase-reversals.store');
    Route::post('/cartoes/creditos/aplicacoes', [CardCreditAllocationController::class, 'store'])->name('card-credit-allocations.store');
    Route::post('/cartoes/encargos', [CardChargeController::class, 'store'])->name('card-charges.store');
    Route::post('/cartoes/pagamentos', [CardPaymentController::class, 'store'])->name('card-payments.store');
    Route::put('/cartoes/pagamentos/{payment}', [CardPaymentController::class, 'update'])->whereNumber('payment')->name('card-payments.update');
    Route::delete('/cartoes/pagamentos/{payment}', [CardPaymentController::class, 'destroy'])->whereNumber('payment')->name('card-payments.destroy');
    Route::post('/cartoes/antecipacoes', [CardAdvanceController::class, 'store'])->name('card-advances.store');
    Route::get('/recebimentos-previstos', [ReceiptForecastController::class, 'index'])->name('receipt-forecasts.index');
    Route::post('/recebimentos-previstos', [ReceiptForecastController::class, 'store'])->name('receipt-forecasts.store');
    Route::put('/recebimentos-previstos/{forecast}', [ReceiptForecastController::class, 'update'])->whereNumber('forecast')->name('receipt-forecasts.update');
    Route::put('/recebimentos-previstos/{forecast}/cancelar', [ReceiptForecastController::class, 'cancel'])->whereNumber('forecast')->name('receipt-forecasts.cancel');
    Route::post('/recebimentos-previstos/{forecast}/recebimentos', [ReceiptForecastReceiptController::class, 'store'])->whereNumber('forecast')->name('receipt-forecasts.receipts.store');
    Route::get('/configuracao-financeira', [FinancialSettingsController::class, 'edit'])->name('financial-settings.edit');
    Route::get('/historico-financeiro', [FinancialEvaluationController::class, 'index'])->name('financial-evaluations.index');
    Route::get('/metas', [FinancialGoalController::class, 'index'])->name('financial-goals.index');
    Route::post('/metas', [FinancialGoalController::class, 'store'])->name('financial-goals.store');
    Route::put('/metas/{goal}', [FinancialGoalController::class, 'update'])->whereNumber('goal')->name('financial-goals.update');
    Route::delete('/metas/{goal}', [FinancialGoalController::class, 'destroy'])->whereNumber('goal')->name('financial-goals.destroy');
    Route::get('/avisos-financeiros', [InternalAlertController::class, 'index'])->name('internal-alerts.index');
    Route::get('/patrimonio', [PatrimonialAssetController::class, 'index'])->name('patrimony.index');
    Route::post('/patrimonio', [PatrimonialAssetController::class, 'store'])->name('patrimony.store');
    Route::put('/patrimonio/{asset}', [PatrimonialAssetController::class, 'update'])->whereNumber('asset')->name('patrimony.update');
    Route::delete('/patrimonio/{asset}', [PatrimonialAssetController::class, 'destroy'])->whereNumber('asset')->name('patrimony.destroy');
    Route::get('/ajuda', HelpCenterController::class)->name('help.index');
    Route::put('/configuracao-financeira', [FinancialSettingsController::class, 'update'])->name('financial-settings.update');
    Route::post('/configuracao-financeira/categorias', [FinancialSettingsController::class, 'storeCategory'])->name('financial-settings.categories.store');
    Route::middleware('feature:ofx')->group(function () {
        Route::get('/importacoes/ofx', [OfxImportController::class, 'index'])->name('ofx-imports.index');
        Route::post('/importacoes/ofx', [OfxImportController::class, 'store'])
            ->middleware('throttle:6,1')
            ->name('ofx-imports.store');
        Route::patch('/importacoes/ofx/itens/{item}', [OfxImportReviewController::class, 'update'])
            ->whereNumber('item')
            ->middleware('throttle:30,1')
            ->name('ofx-imports.items.update');
        Route::post('/importacoes/ofx/{import}/confirmar', [OfxImportConfirmationController::class, 'store'])
            ->whereNumber('import')
            ->middleware('throttle:12,1')
            ->name('ofx-imports.confirm');
        Route::post('/importacoes/ofx/{import}/pix-no-credito/{item}/confirmar', [OfxCardCreditPixConfirmationController::class, 'store'])
            ->whereNumber(['import', 'item'])
            ->middleware('throttle:12,1')
            ->name('ofx-imports.pix-credit.confirm');
    });
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
    Route::get('/lancamentos/{ledgerEntry}/editar', [LedgerEntryCorrectionController::class, 'edit'])
        ->whereNumber('ledgerEntry')->name('ledger-entries.correction.edit');
    Route::patch('/lancamentos/{ledgerEntry}/correcao', [LedgerEntryCorrectionController::class, 'update'])
        ->whereNumber('ledgerEntry')->name('ledger-entries.correction.update');
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
    Route::get('/profile/exportacao-financeira.json', FinancialDiagnosticExportController::class)
        ->middleware('throttle:6,1')
        ->name('financial-diagnostic-export.show');
    Route::post('/configuracoes-avancadas/limpeza/desafio', [OperationalDataResetController::class, 'challenge'])
        ->middleware('throttle:6,1')
        ->name('operational-data-reset.challenge');
    Route::delete('/configuracoes-avancadas/limpeza', [OperationalDataResetController::class, 'destroy'])
        ->middleware('throttle:3,1')
        ->name('operational-data-reset.destroy');
});

require __DIR__.'/auth.php';
