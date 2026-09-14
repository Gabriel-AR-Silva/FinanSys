<?php

namespace App\Queries;

use App\Models\FinancialEvaluation;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class FinancialEvaluationHistoryQuery
{
    public function paginate(User $user, string $view, CarbonImmutable $from, CarbonImmutable $to): LengthAwarePaginator
    {
        return FinancialEvaluation::query()
            ->whereBelongsTo($user)
            ->where('view', $view)
            ->whereBetween('evaluation_date', [$from->toDateString(), $to->toDateString()])
            ->orderByDesc('evaluation_date')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (FinancialEvaluation $evaluation): array => [
                'id' => $evaluation->id,
                'evaluation_date' => $evaluation->evaluation_date->toDateString(),
                'evaluated_at' => $evaluation->evaluated_at->toIso8601String(),
                'view' => $evaluation->view,
                'source' => $evaluation->source,
                'rules_version' => $evaluation->rules_version,
                'revision' => $evaluation->revision,
                'supersedes_id' => $evaluation->supersedes_id,
                'result' => $evaluation->result,
            ]);
    }
}
