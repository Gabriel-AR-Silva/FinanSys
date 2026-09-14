<?php

namespace App\Models;

use Database\Factories\InternalAlertFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'alert_date', 'view', 'financial_evaluation_id', 'current_situation', 'worst_situation', 'current_deficit', 'deficit_seen', 'recovered_at', 'payload'])]
class InternalAlert extends Model
{
    /** @use HasFactory<InternalAlertFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'alert_date' => 'immutable_date',
            'current_deficit' => 'decimal:2',
            'deficit_seen' => 'boolean',
            'recovered_at' => 'immutable_datetime',
            'payload' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function evaluation(): BelongsTo
    {
        return $this->belongsTo(FinancialEvaluation::class, 'financial_evaluation_id');
    }
}
