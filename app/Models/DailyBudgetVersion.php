<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable(['user_id', 'actor_id', 'amount', 'effective_at', 'recorded_at', 'origin', 'reason', 'operation_id'])]
class DailyBudgetVersion extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'effective_at' => 'immutable_datetime',
            'recorded_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('A historical budget version cannot be overwritten.');
        });

        static::deleting(function (): never {
            throw new LogicException('A historical budget version cannot be deleted through the model.');
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
