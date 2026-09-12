<?php

namespace App\Models;

use App\Enums\RecordStatus;
use Database\Factories\CreditCardFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['user_id', 'name', 'closing_day', 'due_day', 'status', 'operation_id'])]
class CreditCard extends Model
{
    /** @use HasFactory<CreditCardFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return ['closing_day' => 'integer', 'due_day' => 'integer', 'status' => RecordStatus::class];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(CardPurchase::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(CardPayment::class);
    }
}
