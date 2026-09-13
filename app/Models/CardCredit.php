<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'credit_card_id', 'card_purchase_reversal_id', 'amount', 'applied_amount', 'credited_on'])]
class CardCredit extends Model
{
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'applied_amount' => 'decimal:2',
            'credited_on' => 'immutable_date',
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

    public function reversal(): BelongsTo
    {
        return $this->belongsTo(CardPurchaseReversal::class, 'card_purchase_reversal_id');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(CardCreditAllocation::class);
    }
}
