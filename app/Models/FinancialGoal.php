<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['user_id', 'pocket_id', 'name', 'target_amount', 'target_date', 'operation_id'])]
class FinancialGoal extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'target_amount' => 'decimal:2',
            'target_date' => 'immutable_date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function pocket(): BelongsTo
    {
        return $this->belongsTo(Pocket::class);
    }
}
