<?php

namespace App\Models;

use Database\Factories\CardPaymentAllocationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'card_payment_id', 'card_installment_id', 'amount'])]
class CardPaymentAllocation extends Model
{
    /** @use HasFactory<CardPaymentAllocationFactory> */
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

    public function installment(): BelongsTo
    {
        return $this->belongsTo(CardInstallment::class, 'card_installment_id');
    }
}
