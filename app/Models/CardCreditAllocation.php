<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'card_credit_id', 'card_installment_id', 'card_charge_id', 'amount', 'applied_on', 'operation_id'])]
class CardCreditAllocation extends Model
{
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'applied_on' => 'immutable_date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function credit(): BelongsTo
    {
        return $this->belongsTo(CardCredit::class, 'card_credit_id');
    }

    public function installment(): BelongsTo
    {
        return $this->belongsTo(CardInstallment::class, 'card_installment_id');
    }

    public function charge(): BelongsTo
    {
        return $this->belongsTo(CardCharge::class, 'card_charge_id');
    }
}
