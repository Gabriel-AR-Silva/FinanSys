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
    public function forUser(User $user, int $period = 30, ?int $categoryId = null): array
    {
        $entries = LedgerEntry::query()->whereBelongsTo($user);
        $start = CarbonImmutable::now()->startOfDay()->subDays($period - 1);
        $end = CarbonImmutable::now()->endOfDay();
        $periodEntries = LedgerEntry::query()->whereBelongsTo($user)
            ->whereIn('type', [LedgerEntryType::Income, LedgerEntryType::Expense])
            ->whereBetween('occurred_at', [$start, $end])
            ->when($categoryId !== null, fn (Builder $query) => $query->where('category_id', $categoryId));
        $periodSummary = (clone $periodEntries)->toBase()
            ->selectRaw('COALESCE(SUM(CASE WHEN type = ? THEN amount ELSE 0 END), 0) AS income', [LedgerEntryType::Income->value])
            ->selectRaw('COALESCE(SUM(CASE WHEN type = ? THEN amount ELSE 0 END), 0) AS expense', [LedgerEntryType::Expense->value])
            ->selectRaw('COUNT(*) AS transaction_count')
            ->selectRaw('COALESCE(MAX(CASE WHEN type = ? THEN amount ELSE NULL END), 0) AS largest_expense', [LedgerEntryType::Expense->value])
            ->first();
        $income = BigDecimal::of((string) $periodSummary->income);
        $expense = BigDecimal::of((string) $periodSummary->expense);
        $net = $income->minus($expense);
        $savingsRate = $income->isZero()
            ? '0'
            : (string) $net->multipliedBy(100)->dividedBy($income, 2, RoundingMode::HalfUp);

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
            'period_summary' => [
                'income' => (string) $income,
                'expense' => (string) $expense,
                'net' => (string) $net,
                'savings_rate' => $savingsRate,
                'average_daily_expense' => (string) $expense->dividedBy($period, 2, RoundingMode::HalfUp),
                'transaction_count' => (int) $periodSummary->transaction_count,
                'largest_expense' => (string) $periodSummary->largest_expense,
            ],
            'recent_entries' => (clone $entries)
                ->whereBetween('occurred_at', [$start, $end])
                ->when($categoryId !== null, fn (Builder $query) => $query->where('category_id', $categoryId))
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
            'cash_flow' => $this->cashFlow($user, $period, $categoryId),
            'category_breakdown' => $this->categoryBreakdown($user, $period, $categoryId),
        ];
    }

    private function categoryBreakdown(User $user, int $period, ?int $categoryId): array
    {
        $start = CarbonImmutable::now()->startOfDay()->subDays($period - 1);
        $end = CarbonImmutable::now()->endOfDay();
        $rows = LedgerEntry::query()->whereBelongsTo($user)
            ->whereIn('type', [LedgerEntryType::Income, LedgerEntryType::Expense])
            ->whereBetween('occurred_at', [$start, $end])
            ->when($categoryId !== null, fn (Builder $query) => $query->where('category_id', $categoryId))
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
            })->sortByDesc(fn (array $item): string => (string) BigDecimal::of($item['total'])->abs())
            ->values()->all();
    }

    private function cashFlow(User $user, int $period, ?int $categoryId): array
    {
        $end = CarbonImmutable::now()->endOfDay();
        $start = CarbonImmutable::now()->startOfDay()->subDays($period - 1);
        $rows = LedgerEntry::query()->whereBelongsTo($user)
            ->whereIn('type', [LedgerEntryType::Income, LedgerEntryType::Expense])
            ->whereBetween('occurred_at', [$start, $end])
            ->when($categoryId !== null, fn (Builder $query) => $query->where('category_id', $categoryId))
            ->selectRaw('DATE(occurred_at) AS entry_date')
            ->selectRaw('SUM(CASE WHEN type = ? THEN amount ELSE 0 END) AS income', [LedgerEntryType::Income->value])
            ->selectRaw('SUM(CASE WHEN type = ? THEN amount ELSE 0 END) AS expense', [LedgerEntryType::Expense->value])
            ->groupBy('entry_date')
            ->get()
            ->keyBy('entry_date');
        $points = [];

        for ($date = $start; $date->lte($end); $date = $date->addDay()) {
            $row = $rows->get($date->toDateString());
            $income = BigDecimal::of((string) ($row?->getAttribute('income') ?? '0'));
            $expense = BigDecimal::of((string) ($row?->getAttribute('expense') ?? '0'));
            $points[] = [
                'date' => $date->toDateString(),
                'income' => (string) $income,
                'expense' => (string) $expense,
                'net' => (string) $income->minus($expense),
            ];
        }

        return ['period' => $period, 'points' => $points];
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
