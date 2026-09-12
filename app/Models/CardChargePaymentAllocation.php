<?php

namespace App\Models;

use Database\Factories\CardChargePaymentAllocationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'card_payment_id', 'card_charge_id', 'amount'])]
class CardChargePaymentAllocation extends Model
{
    /** @use HasFactory<CardChargePaymentAllocationFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(CardPayment::class, 'card_payment_id');
    }

    public function charge(): BelongsTo
    {
        return $this->belongsTo(CardCharge::class, 'card_charge_id');
    }
}
