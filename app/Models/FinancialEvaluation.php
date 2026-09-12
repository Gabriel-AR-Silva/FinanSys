<?php

namespace App\Models;

use Database\Factories\FinancialEvaluationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'evaluation_date', 'view', 'rules_version', 'revision', 'supersedes_id', 'source', 'evaluated_at', 'result'])]
class FinancialEvaluation extends Model
{
    /** @use HasFactory<FinancialEvaluationFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'evaluation_date' => 'immutable_date',
            'evaluated_at' => 'immutable_datetime',
            'revision' => 'integer',
            'supersedes_id' => 'integer',
            'result' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function supersedes(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supersedes_id');
    }
}
