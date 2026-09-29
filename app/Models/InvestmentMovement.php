<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'investment_position_id', 'type', 'occurred_on', 'quantity', 'unit_price', 'amount', 'fees', 'notes'])]
class InvestmentMovement extends Model
{
    protected function casts(): array
    {
        return [
            'occurred_on' => 'immutable_date',
            'quantity' => 'decimal:8',
            'unit_price' => 'decimal:4',
            'amount' => 'decimal:2',
            'fees' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(InvestmentPosition::class, 'investment_position_id');
    }
}
