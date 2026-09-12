<?php

namespace App\Models;

use App\Enums\ReceiptForecastStatus;
use Database\Factories\ReceiptForecastFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'category_id', 'amount', 'expected_on', 'status', 'operation_id', 'series_id', 'series_position', 'original_day', 'version'])]
class ReceiptForecast extends Model
{
    /** @use HasFactory<ReceiptForecastFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'expected_on' => 'immutable_date', 'status' => ReceiptForecastStatus::class, 'series_position' => 'integer', 'original_day' => 'integer', 'version' => 'integer'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function links(): HasMany
    {
        return $this->hasMany(ReceiptForecastLink::class);
    }

    public function activeLinks(): HasMany
    {
        return $this->links()->whereNull('unlinked_at');
    }
}
