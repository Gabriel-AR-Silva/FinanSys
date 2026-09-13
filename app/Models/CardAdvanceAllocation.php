<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'card_advance_id', 'card_installment_id', 'gross_amount', 'discount_amount', 'net_amount', 'original_due_on'])]
class CardAdvanceAllocation extends Model
{
    protected function casts(): array
    {
        return [
            'gross_amount' => 'decimal:2', 'discount_amount' => 'decimal:2', 'net_amount' => 'decimal:2',
            'original_due_on' => 'immutable_date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function advance(): BelongsTo
    {
        return $this->belongsTo(CardAdvance::class, 'card_advance_id');
    }

    public function installment(): BelongsTo
    {
        return $this->belongsTo(CardInstallment::class, 'card_installment_id');
    }
}
