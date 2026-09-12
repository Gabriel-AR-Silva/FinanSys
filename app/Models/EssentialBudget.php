<?php

namespace App\Models;

use Database\Factories\EssentialBudgetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['user_id', 'monthly_financial_setting_id', 'category_id', 'amount'])]
class EssentialBudget extends Model
{
    /** @use HasFactory<EssentialBudgetFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function monthlyFinancialSetting(): BelongsTo
    {
        return $this->belongsTo(MonthlyFinancialSetting::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
