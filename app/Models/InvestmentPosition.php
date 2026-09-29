<?php

namespace App\Models;

use Database\Factories\InvestmentPositionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'asset_type', 'ticker', 'name', 'quantity', 'average_cost', 'total_invested', 'current_value', 'valuation_source', 'valued_on'])]
class InvestmentPosition extends Model
{
    /** @use HasFactory<InvestmentPositionFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:8',
            'average_cost' => 'decimal:4',
            'total_invested' => 'decimal:2',
            'current_value' => 'decimal:2',
            'valued_on' => 'immutable_date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(InvestmentMovement::class);
    }
}
