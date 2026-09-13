<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['user_id', 'credit_card_id', 'card_purchase_id', 'reversed_on', 'cancelled_pending_amount', 'credited_paid_amount', 'reason', 'operation_id'])]
class CardPurchaseReversal extends Model
{
    protected function casts(): array
    {
        return [
            'reversed_on' => 'immutable_date',
            'cancelled_pending_amount' => 'decimal:2',
            'credited_paid_amount' => 'decimal:2',
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

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(CardPurchase::class, 'card_purchase_id');
    }

    public function credit(): HasOne
    {
        return $this->hasOne(CardCredit::class);
    }
}
