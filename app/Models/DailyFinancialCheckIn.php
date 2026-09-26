<?php

namespace App\Models;

use Database\Factories\DailyFinancialCheckInFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable([
    'user_id', 'actor_id', 'local_date', 'revision', 'supersedes_id',
    'daily_budget_version_id', 'budget_amount', 'eligible_spent', 'margin',
    'rules_version', 'source', 'confirmed_at', 'reason', 'operation_id',
])]
class DailyFinancialCheckIn extends Model
{
    /** @use HasFactory<DailyFinancialCheckInFactory> */
    use HasFactory;

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'local_date' => 'immutable_date',
            'revision' => 'integer',
            'supersedes_id' => 'integer',
            'daily_budget_version_id' => 'integer',
            'budget_amount' => 'decimal:2',
            'eligible_spent' => 'decimal:2',
            'margin' => 'decimal:2',
            'confirmed_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('A daily financial check-in revision cannot be overwritten.');
        });

        static::deleting(function (): never {
            throw new LogicException('A daily financial check-in revision cannot be deleted through the model.');
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

    public function budgetVersion(): BelongsTo
    {
        return $this->belongsTo(DailyBudgetVersion::class, 'daily_budget_version_id');
    }

    public function supersedes(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supersedes_id');
    }
}
