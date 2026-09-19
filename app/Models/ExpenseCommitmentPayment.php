<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'expense_commitment_id', 'ledger_entry_id', 'amount', 'paid_on', 'operation_id'])]
class ExpenseCommitmentPayment extends Model
{
    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'paid_on' => 'immutable_date'];
    }

    public function commitment(): BelongsTo
    {
        return $this->belongsTo(ExpenseCommitment::class, 'expense_commitment_id');
    }

    public function ledgerEntry(): BelongsTo
    {
        return $this->belongsTo(LedgerEntry::class);
    }
}
