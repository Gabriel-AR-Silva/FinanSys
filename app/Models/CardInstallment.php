<?php

namespace App\Models;

use App\Enums\CardInstallmentStatus;
use Database\Factories\CardInstallmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'card_purchase_id', 'installment_number', 'gross_amount', 'paid_amount', 'due_on', 'original_due_on', 'status'])]
class CardInstallment extends Model
{
    /** @use HasFactory<CardInstallmentFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['installment_number' => 'integer', 'gross_amount' => 'decimal:2', 'paid_amount' => 'decimal:2', 'due_on' => 'immutable_date', 'original_due_on' => 'immutable_date', 'status' => CardInstallmentStatus::class];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(CardPurchase::class, 'card_purchase_id');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(CardPaymentAllocation::class);
    }

    public function advanceAllocations(): HasMany
    {
        return $this->hasMany(CardAdvanceAllocation::class);
    }
}
