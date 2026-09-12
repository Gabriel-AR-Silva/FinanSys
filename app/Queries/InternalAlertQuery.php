<?php

namespace App\Queries;

use App\Models\InternalAlert;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class InternalAlertQuery
{
    public function paginate(User $user, string $view, CarbonImmutable $from, CarbonImmutable $to, ?string $status, bool $deficitOnly): LengthAwarePaginator
    {
        return InternalAlert::query()->whereBelongsTo($user)
            ->where('view', $view)
            ->whereBetween('alert_date', [$from->toDateString(), $to->toDateString()])
            ->when($status === 'active', fn ($query) => $query->whereNull('recovered_at'))
            ->when($status === 'recovered', fn ($query) => $query->whereNotNull('recovered_at'))
            ->when($deficitOnly, fn ($query) => $query->where('deficit_seen', true))
            ->orderByDesc('alert_date')->orderByDesc('id')
            ->paginate(20)->withQueryString()
            ->through(fn (InternalAlert $alert): array => [
                'id' => $alert->id,
                'alert_date' => $alert->alert_date->toDateString(),
                'view' => $alert->view,
                'current_situation' => $alert->current_situation,
                'worst_situation' => $alert->worst_situation,
                'current_deficit' => $alert->current_deficit,
                'deficit_seen' => $alert->deficit_seen,
                'recovered_at' => $alert->recovered_at?->toIso8601String(),
                'updated_at' => $alert->updated_at->toIso8601String(),
                'evaluated_at' => $alert->payload['evaluated_at'] ?? null,
                'reasons' => $alert->payload['reasons'] ?? [],
            ]);
    }
}
