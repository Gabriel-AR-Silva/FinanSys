<?php

namespace App\Models;

use Database\Factories\ExpenseRefundFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'expense_ledger_entry_id', 'refund_ledger_entry_id', 'operation_id'])]
class ExpenseRefund extends Model
{
    /** @use HasFactory<ExpenseRefundFactory> */
    use HasFactory;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function expenseEntry(): BelongsTo
    {
        return $this->belongsTo(LedgerEntry::class, 'expense_ledger_entry_id');
    }

    public function refundEntry(): BelongsTo
    {
        return $this->belongsTo(LedgerEntry::class, 'refund_ledger_entry_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereHas('refundEntry', fn (Builder $entry): Builder => $entry
            ->whereNull('reversal_of_operation_id')
            ->whereNotExists(fn ($reversals) => $reversals->selectRaw('1')->from('ledger_entries as refund_reversals')
                ->whereColumn('refund_reversals.user_id', 'ledger_entries.user_id')
                ->whereColumn('refund_reversals.reversal_of_operation_id', 'ledger_entries.operation_id')));
    }
}
