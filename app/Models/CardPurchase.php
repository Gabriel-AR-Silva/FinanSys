<?php

namespace App\Models;

use App\Enums\ExpensePlanningType;
use Database\Factories\CardPurchaseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['user_id', 'credit_card_id', 'category_id', 'description', 'planning_type', 'gross_amount', 'purchased_on', 'installments_count', 'operation_id'])]
class CardPurchase extends Model
{
    /** @use HasFactory<CardPurchaseFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return ['planning_type' => ExpensePlanningType::class, 'gross_amount' => 'decimal:2', 'purchased_on' => 'immutable_date', 'installments_count' => 'integer'];
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

    public function installments(): HasMany
    {
        return $this->hasMany(CardInstallment::class);
    }
}
