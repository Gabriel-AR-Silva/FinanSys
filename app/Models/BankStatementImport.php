<?php

namespace App\Models;

use App\Enums\OfxReviewStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id', 'account_id', 'institution', 'institution_id', 'currency',
    'source_account_hash', 'source_account_suffix', 'period_start', 'period_end',
    'ledger_balance', 'ledger_balance_at', 'status', 'transaction_count',
])]
class BankStatementImport extends Model
{
    protected function casts(): array
    {
        return [
            'period_start' => 'immutable_datetime',
            'period_end' => 'immutable_datetime',
            'ledger_balance_at' => 'immutable_datetime',
            'status' => OfxReviewStatus::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(BankStatementImportItem::class);
    }
}
