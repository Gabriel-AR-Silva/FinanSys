<?php

namespace App\Queries;

use App\Models\FinancialGoal;
use App\Models\User;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;

final class FinancialGoalPlanningQuery
{
    public function __construct(private PocketBalanceQuery $pocketBalances) {}

    /** @return array{goals:list<array<string,mixed>>,summary:array<string,mixed>,evaluated_at:string} */
    public function forUser(User $user, ?CarbonImmutable $evaluatedAt = null): array
    {
        $today = ($evaluatedAt ?? CarbonImmutable::now('America/Sao_Paulo'))
            ->setTimezone('America/Sao_Paulo')
            ->startOfDay();

        $pockets = $this->pocketBalances->forUser($user)->keyBy('id');
        $goals = FinancialGoal::query()
            ->whereBelongsTo($user)
            ->orderBy('target_date')
            ->orderBy('id')
            ->get();

        $targetTotal = BigDecimal::zero();
        $reservedTotal = BigDecimal::zero();
        $remainingTotal = BigDecimal::zero();

        $items = $goals->map(function (FinancialGoal $goal) use ($today, $pockets, &$targetTotal, &$reservedTotal, &$remainingTotal): array {
            $target = BigDecimal::of($goal->target_amount);
            $pocket = $goal->pocket_id === null ? null : $pockets->get($goal->pocket_id);
            $reserved = BigDecimal::of($pocket?->balance ?? '0.00');
            if ($reserved->isNegative()) {
                $reserved = BigDecimal::zero();
            }

            $remaining = $target->minus($reserved);
            if ($remaining->isNegative()) {
                $remaining = BigDecimal::zero();
            }

            $targetDate = $goal->target_date->startOfDay();
            $daysRemaining = $targetDate->isBefore($today)
                ? 0
                : (int) $today->diffInDays($targetDate);

            $status = match (true) {
                $remaining->isZero() => 'completed',
                $targetDate->isBefore($today) => 'overdue',
                $targetDate->isSameDay($today) => 'due_today',
                default => 'active',
            };

            $dailyRequired = match (true) {
                $remaining->isZero() => '0.00',
                $daysRemaining > 0 => (string) $remaining
                    ->dividedBy($daysRemaining, 2, RoundingMode::Ceiling)
                    ->toScale(2),
                default => null,
            };

            $progress = $target->isZero()
                ? '0.00'
                : (string) $reserved
                    ->dividedBy($target, 4, RoundingMode::HalfUp)
                    ->multipliedBy(100)
                    ->toScale(2, RoundingMode::HalfUp);

            $targetTotal = $targetTotal->plus($target);
            $reservedTotal = $reservedTotal->plus($reserved);
            $remainingTotal = $remainingTotal->plus($remaining);

            return [
                'id' => (int) $goal->getKey(),
                'name' => $goal->name,
                'target_amount' => (string) $target->toScale(2),
                'target_date' => $goal->target_date->toDateString(),
                'pocket_id' => $goal->pocket_id,
                'pocket_name' => $pocket?->name,
                'reserved_amount' => (string) $reserved->toScale(2),
                'remaining_amount' => (string) $remaining->toScale(2),
                'days_remaining' => $daysRemaining,
                'daily_required' => $dailyRequired,
                'progress_percentage' => $progress,
                'status' => $status,
            ];
        })->values()->all();

        return [
            'goals' => $items,
            'summary' => [
                'count' => count($items),
                'target_total' => (string) $targetTotal->toScale(2),
                'reserved_total' => (string) $reservedTotal->toScale(2),
                'remaining_total' => (string) $remainingTotal->toScale(2),
                'completed_count' => count(array_filter($items, fn (array $item): bool => $item['status'] === 'completed')),
            ],
            'evaluated_at' => $today->toIso8601String(),
        ];
    }
}
