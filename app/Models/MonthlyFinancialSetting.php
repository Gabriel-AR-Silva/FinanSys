<?php

namespace App\Models;

use App\Enums\ProtectionType;
use Database\Factories\MonthlyFinancialSettingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'month', 'protection_type', 'protection_value', 'version'])]
class MonthlyFinancialSetting extends Model
{
    /** @use HasFactory<MonthlyFinancialSettingFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['protection_type' => ProtectionType::class, 'protection_value' => 'decimal:2', 'version' => 'integer'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function essentials(): HasMany
    {
        return $this->hasMany(EssentialBudget::class);
    }
}
