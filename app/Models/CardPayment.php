<?php

namespace App\Models;

use Database\Factories\CardPaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'credit_card_id', 'source_account_id', 'ledger_entry_id', 'amount', 'paid_on', 'selected_charge_ids', 'operation_id'])]
class CardPayment extends Model
{
    /** @use HasFactory<CardPaymentFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'paid_on' => 'immutable_date', 'selected_charge_ids' => 'array'];
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
        return $this->hasMany(CardPaymentAllocation::class);
    }

    public function chargeAllocations(): HasMany
    {
        return $this->hasMany(CardChargePaymentAllocation::class);
    }
}
