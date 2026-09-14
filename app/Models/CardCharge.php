<?php

namespace App\Models;

use App\Enums\CardChargeType;
use App\Enums\CardInstallmentStatus;
use App\Enums\ExpensePlanningType;
use Database\Factories\CardChargeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'credit_card_id', 'category_id', 'type', 'description', 'planning_type', 'amount', 'paid_amount', 'charged_on', 'due_on', 'status', 'operation_id'])]
class CardCharge extends Model
{
    /** @use HasFactory<CardChargeFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'type' => CardChargeType::class,
            'planning_type' => ExpensePlanningType::class,
            'amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'charged_on' => 'immutable_date',
            'due_on' => 'immutable_date',
            'status' => CardInstallmentStatus::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function creditCard(): BelongsTo
    {
        return $this->belongsTo(CreditCard::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(CardChargePaymentAllocation::class);
    }
}
