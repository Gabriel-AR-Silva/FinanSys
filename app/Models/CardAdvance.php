<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'credit_card_id', 'source_account_id', 'ledger_entry_id', 'gross_amount', 'discount_amount', 'net_amount', 'advanced_on', 'selected_installment_ids', 'operation_id'])]
class CardAdvance extends Model
{
    protected function casts(): array
    {
        return [
            'gross_amount' => 'decimal:2', 'discount_amount' => 'decimal:2', 'net_amount' => 'decimal:2',
            'advanced_on' => 'immutable_date', 'selected_installment_ids' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function creditCard(): BelongsTo
    {
        return $this->belongsTo(CreditCard::class);
    }

    public function sourceAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'source_account_id');
    }

    public function ledgerEntry(): BelongsTo
    {
        return $this->belongsTo(LedgerEntry::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(CardAdvanceAllocation::class);
    }
}
