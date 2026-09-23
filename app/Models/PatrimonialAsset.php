<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'name', 'category', 'estimated_value', 'debt_balance', 'valued_on'])]
class PatrimonialAsset extends Model
{
    protected function casts(): array
    {
        return [
            'estimated_value' => 'decimal:2',
            'debt_balance' => 'decimal:2',
            'valued_on' => 'immutable_date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
