<?php

namespace App\Queries;

use App\Enums\LedgerEntryReferenceType;
use App\Enums\LedgerEntryType;
use App\Models\Category;
use App\Models\LedgerEntry;
use App\Models\User;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

class FinancialOverviewQuery
{
    public function forUser(User $user, int $period = 30): array
    {
        $entries = LedgerEntry::query()->whereBelongsTo($user);

        return [
            'general_balance' => $this->balance(clone $entries),
            'accounts_balance' => $this->balance((clone $entries)->where('reference_type', LedgerEntryReferenceType::Account->value)),
            'pockets_balance' => $this->balance((clone $entries)->where('reference_type', LedgerEntryReferenceType::Pocket->value)),
            'monthly_income' => (clone $entries)
                ->where('type', LedgerEntryType::Income)
                ->whereBetween('occurred_at', [now()->startOfMonth(), now()->endOfMonth()])
                ->sum('amount'),
            'monthly_expense' => (clone $entries)
                ->where('type', LedgerEntryType::Expense)
                ->whereBetween('occurred_at', [now()->startOfMonth(), now()->endOfMonth()])
                ->sum('amount'),
            'recent_entries' => (clone $entries)
                ->with('reference:id,name')
                ->latest('occurred_at')
                ->latest('id')
                ->limit(5)
                ->get()
                ->map(fn (LedgerEntry $entry): array => [
                    'id' => $entry->id,
                    'type' => $entry->type->value,
                    'type_label' => $this->typeLabel($entry->type),
                    'amount' => $entry->amount,
                    'is_positive' => in_array($entry->type, $this->positiveTypes(), true),
                    'reference_name' => $entry->reference?->name,
                    'occurred_at' => $entry->occurred_at,
                    'description' => $entry->description,
                ]),
            'chart' => $this->chart($user, $period),
            'category_breakdown' => $this->categoryBreakdown($user, $period),
        ];
    }

    private function categoryBreakdown(User $user, int $period): array
    {
        $start = CarbonImmutable::now()->startOfDay()->subDays($period - 1);
        $end = CarbonImmutable::now()->endOfDay();
        $rows = LedgerEntry::query()->whereBelongsTo($user)
            ->whereIn('type', [LedgerEntryType::Income, LedgerEntryType::Expense])
            ->whereBetween('occurred_at', [$start, $end])
            ->select(['category_id', 'type'])
            ->selectRaw('SUM(amount) AS total')
            ->groupBy('category_id', 'type')
            ->get();
        $categories = Category::query()->whereBelongsTo($user)
            ->whereIn('id', $rows->pluck('category_id')->filter())
            ->get(['id', 'name', 'type'])
            ->keyBy('id');

        return $rows->map(function (LedgerEntry $row) use ($categories): array {
            $category = $row->category_id === null ? null : $categories->get($row->category_id);
            $type = $row->type;
            $total = BigDecimal::of((string) $row->getAttribute('total'));

            return [
                'id' => $category?->id,
                'name' => $category?->name ?? 'Sem categoria',
                'type' => $category?->type->value ?? $type->value,
                'total' => (string) ($type === LedgerEntryType::Expense ? $total->negated() : $total),
            ];
        })->groupBy(fn (array $item): string => ($item['id'] ?? 'none').':'.$item['type'])
            ->map(function ($items): array {
                $first = $items->first();
                $total = $items->reduce(
                    fn (BigDecimal $sum, array $item): BigDecimal => $sum->plus($item['total']),
                    BigDecimal::zero(),
                );

                return [...$first, 'total' => (string) $total];
            })->values()->all();
    }

    private function chart(User $user, int $period): array
    {
        $end = CarbonImmutable::now()->endOfDay();
        $start = CarbonImmutable::now()->startOfDay()->subDays($period - 1);
        $entries = LedgerEntry::query()->whereBelongsTo($user);
        $opening = BigDecimal::of($this->balance((clone $entries)->where('occurred_at', '<', $start)));
        $positiveValues = array_map(fn (LedgerEntryType $type): string => $type->value, $this->positiveTypes());
        $placeholders = implode(', ', array_fill(0, count($positiveValues), '?'));
        $deltas = (clone $entries)
            ->whereBetween('occurred_at', [$start, $end])
            ->selectRaw('DATE(occurred_at) AS entry_date')
            ->selectRaw("SUM(CASE WHEN type IN ({$placeholders}) THEN amount ELSE -amount END) AS delta", $positiveValues)
            ->groupBy('entry_date')
            ->pluck('delta', 'entry_date');
        $running = $opening;
        $points = [];

        for ($date = $start; $date->lte($end); $date = $date->addDay()) {
            $delta = BigDecimal::of((string) ($deltas[$date->toDateString()] ?? '0'));
            $running = $running->plus($delta);
            $points[] = [
                'date' => $date->toDateString(),
                'balance' => (string) $running,
                'change' => (string) $delta,
            ];
        }

        $change = $running->minus($opening);
        $percentage = $opening->isZero()
            ? null
            : (string) $change->dividedBy($opening->abs(), 2, RoundingMode::HalfUp)->multipliedBy(100);

        return [
            'period' => $period,
            'starting_balance' => (string) $opening,
            'ending_balance' => (string) $running,
            'change' => (string) $change,
            'change_percentage' => $percentage,
            'points' => $points,
        ];
    }

    private function balance(Builder $query): string
    {
        $positiveValues = array_map(fn (LedgerEntryType $type): string => $type->value, $this->positiveTypes());
        $placeholders = implode(', ', array_fill(0, count($positiveValues), '?'));

        return (string) $query
            ->selectRaw("COALESCE(SUM(CASE WHEN type IN ({$placeholders}) THEN amount ELSE -amount END), 0) AS balance", $positiveValues)
            ->value('balance');
    }

    /** @return list<LedgerEntryType> */
    private function positiveTypes(): array
    {
        return [LedgerEntryType::OpeningBalance, LedgerEntryType::Income, LedgerEntryType::TransferIn];
    }

    private function typeLabel(LedgerEntryType $type): string
    {
        return match ($type) {
            LedgerEntryType::OpeningBalance => 'Saldo inicial',
            LedgerEntryType::Income => 'Receita',
            LedgerEntryType::Expense => 'Despesa',
            LedgerEntryType::TransferIn => 'Transferência recebida',
            LedgerEntryType::TransferOut => 'Transferência enviada',
        };
    }
}
