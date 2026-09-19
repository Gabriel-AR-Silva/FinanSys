<?php

use App\Http\Controllers\ExpenseCommitmentController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function (): void {
    Route::get('/compromissos-futuros', [ExpenseCommitmentController::class, 'index'])->name('expense-commitments.index');
    Route::post('/compromissos-futuros', [ExpenseCommitmentController::class, 'store'])->name('expense-commitments.store');
    Route::patch('/compromissos-futuros/{commitment}', [ExpenseCommitmentController::class, 'update'])->whereNumber('commitment')->name('expense-commitments.update');
    Route::post('/compromissos-futuros/{commitment}/pagamentos', [ExpenseCommitmentController::class, 'pay'])->whereNumber('commitment')->name('expense-commitments.pay');
    Route::post('/compromissos-futuros/{commitment}/cancelar', [ExpenseCommitmentController::class, 'cancel'])->whereNumber('commitment')->name('expense-commitments.cancel');
});
