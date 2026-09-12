<?php

namespace App\Models;

use App\Enums\ReceiptForecastUnlinkReason;
use Database\Factories\ReceiptForecastLinkFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'receipt_forecast_id', 'ledger_entry_id', 'operation_id', 'linked_at', 'unlinked_at', 'unlink_reason', 'version'])]
class ReceiptForecastLink extends Model
{
    /** @use HasFactory<ReceiptForecastLinkFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'linked_at' => 'immutable_datetime',
            'unlinked_at' => 'immutable_datetime',
            'unlink_reason' => ReceiptForecastUnlinkReason::class,
            'version' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function forecast(): BelongsTo
    {
        return $this->belongsTo(ReceiptForecast::class, 'receipt_forecast_id');
    }

    public function ledgerEntry(): BelongsTo
    {
        return $this->belongsTo(LedgerEntry::class);
    }
}
