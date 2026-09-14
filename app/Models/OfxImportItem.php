<?php

namespace App\Models;

use App\Enums\OfxClassification;
use App\Enums\OfxReviewStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfxImportItem extends Model
{
    protected $table = 'bank_statement_import_items';

    protected function casts(): array
    {
        return [
            'occurred_at' => 'immutable_datetime',
            'classification' => OfxClassification::class,
            'review_status' => OfxReviewStatus::class,
        ];
    }

    public function import(): BelongsTo
    {
        return $this->belongsTo(BankStatementImport::class, 'bank_statement_import_id');
    }
}
