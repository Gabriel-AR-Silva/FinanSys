<?php

namespace App\Models;

use Database\Factories\ReceiptForecastLinkOperationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'receipt_forecast_link_id', 'receipt_forecast_id', 'ledger_entry_id', 'operation_id', 'kind'])]
class ReceiptForecastLinkOperation extends Model
{
    /** @use HasFactory<ReceiptForecastLinkOperationFactory> */
    use HasFactory;

    public function link(): BelongsTo
    {
        return $this->belongsTo(ReceiptForecastLink::class, 'receipt_forecast_link_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
