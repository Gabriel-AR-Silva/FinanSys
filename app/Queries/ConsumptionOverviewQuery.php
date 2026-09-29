<?php

namespace App\Queries;

use App\Enums\CardInstallmentStatus;
use App\Enums\LedgerEntryType;
use App\Models\CardCharge;
use App\Models\CardPurchase;
use App\Models\Category;
use App\Models\LedgerEntry;
use App\Models\User;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ConsumptionOverviewQuery
{
    public function forUser(User $user, int $period = 30, ?int $categoryId = null): array
    {
        $start = CarbonImmutable::now('America/Sao_Paulo')->startOfDay()->subDays($period - 1);
        $end = CarbonImmutable::now('America/Sao_Paulo')->endOfDay();
        $filter = fn (Builder $query) => $query->when($categoryId !== null, fn (Builder $q) => $q->where('category_id', $categoryId));

        $incomeEntries = $filter(LedgerEntry::query()->whereBelongsTo($user)->where('type', LedgerEntryType::Income)->whereBetween('occurred_at', [$start, $end]))
            ->get(['id', 'category_id', 'amount', 'occurred_at', 'description']);
        $expenseEntries = $filter(LedgerEntry::query()->whereBelongsTo($user)->where('type', LedgerEntryType::Expense)->whereBetween('occurred_at', [$start, $end]))
            ->get(['id', 'category_id', 'amount', 'occurred_at', 'description']);
        $purchases = $filter(CardPurchase::query()->whereBelongsTo($user)->whereBetween('purchased_on', [$start->toDateString(), $end->toDateString()]))
            ->with('creditCard:id,name')->get(['id', 'credit_card_id', 'category_id', 'description', 'gross_amount', 'purchased_on']);
        $charges = $filter(CardCharge::query()->whereBelongsTo($user)->where('status', '!=', CardInstallmentStatus::Reversed)->whereBetween('charged_on', [$start->toDateString(), $end->toDateString()]))
            ->with('creditCard:id,name')->get(['id', 'credit_card_id', 'category_id', 'description', 'amount', 'charged_on']);

        $income = $this->sum($incomeEntries->pluck('amount'));
        $cardConsumption = $this->sum($purchases->pluck('gross_amount'))->plus($this->sum($charges->pluck('amount')));
        $expense = $this->sum($expenseEntries->pluck('amount'))->plus($cardConsumption);
        $net = $income->minus($expense);
        $expenseValues = $expenseEntries->pluck('amount')->concat($purchases->pluck('gross_amount'))->concat($charges->pluck('amount'));
        $largestExpense = $expenseValues->reduce(
            fn (BigDecimal $largest, mixed $amount): BigDecimal => BigDecimal::of((string) $amount)->compareTo($largest) > 0 ? BigDecimal::of((string) $amount) : $largest,
            BigDecimal::zero(),
        );

        return [
            'summary' => [
                'income' => (string) $income,
                'expense' => (string) $expense,
                'net' => (string) $net,
                'savings_rate' => $income->isZero() ? null : (string) $net->multipliedBy(100)->dividedBy($income, 2, RoundingMode::HalfUp),
                'average_daily_expense' => (string) $expense->dividedBy($period, 2, RoundingMode::HalfUp),
                'transaction_count' => $incomeEntries->count() + $expenseEntries->count() + $purchases->count() + $charges->count(),
                'largest_expense' => (string) $largestExpense,
                'card_consumption' => (string) $cardConsumption,
            ],
            'category_breakdown' => $this->categoryBreakdown($user, $incomeEntries, $expenseEntries, $purchases, $charges),
            'recent_activity' => $this->recentActivity($incomeEntries, $expenseEntries, $purchases, $charges),
        ];
    }

    private function categoryBreakdown(User $user, Collection $incomeEntries, Collection $expenseEntries, Collection $purchases, Collection $charges): array
    {
        $rows = collect();
        foreach ($incomeEntries as $entry) $rows->push(['category_id' => $entry->category_id, 'type' => 'income', 'amount' => BigDecimal::of($entry->amount)]);
        foreach ($expenseEntries as $entry) $rows->push(['category_id' => $entry->category_id, 'type' => 'expense', 'amount' => BigDecimal::of($entry->amount)]);
        foreach ($purchases as $purchase) $rows->push(['category_id' => $purchase->category_id, 'type' => 'expense', 'amount' => BigDecimal::of($purchase->gross_amount)]);
        foreach ($charges as $charge) $rows->push(['category_id' => $charge->category_id, 'type' => 'expense', 'amount' => BigDecimal::of($charge->amount)]);

        $categories = Category::query()->whereBelongsTo($user)->whereIn('id', $rows->pluck('category_id')->filter()->unique())->get(['id', 'name'])->keyBy('id');

        return $rows->groupBy(fn (array $row): string => ($row['category_id'] ?? 'none').':'.$row['type'])
            ->map(function (Collection $items) use ($categories): array {
                $first = $items->first();
                $total = $items->reduce(fn (BigDecimal $sum, array $item): BigDecimal => $sum->plus($item['amount']), BigDecimal::zero());

                return [
                    'id' => $first['category_id'],
                    'name' => $first['category_id'] === null ? 'Sem categoria' : ($categories->get($first['category_id'])?->name ?? 'Categoria removida'),
                    'type' => $first['type'],
                    'total' => (string) ($first['type'] === 'expense' ? $total->negated() : $total),
                ];
            })->sortByDesc(fn (array $item): string => (string) BigDecimal::of($item['total'])->abs())->values()->all();
    }

    private function recentActivity(Collection $incomeEntries, Collection $expenseEntries, Collection $purchases, Collection $charges): array
    {
        return $incomeEntries->map(fn ($entry): array => ['key' => 'income-'.$entry->id, 'type_label' => 'Receita', 'description' => $entry->description, 'reference_name' => 'Caixa', 'occurred_at' => $entry->occurred_at->toIso8601String(), 'amount' => $entry->amount, 'is_positive' => true])
            ->concat($expenseEntries->map(fn ($entry): array => ['key' => 'expense-'.$entry->id, 'type_label' => 'Despesa', 'description' => $entry->description, 'reference_name' => 'Caixa', 'occurred_at' => $entry->occurred_at->toIso8601String(), 'amount' => $entry->amount, 'is_positive' => false]))
            ->concat($purchases->map(fn ($purchase): array => ['key' => 'card-purchase-'.$purchase->id, 'type_label' => 'Compra no cartão', 'description' => $purchase->description, 'reference_name' => $purchase->creditCard?->name ?? 'Cartão', 'occurred_at' => $purchase->purchased_on->toDateString(), 'amount' => $purchase->gross_amount, 'is_positive' => false]))
            ->concat($charges->map(fn ($charge): array => ['key' => 'card-charge-'.$charge->id, 'type_label' => 'Encargo do cartão', 'description' => $charge->description, 'reference_name' => $charge->creditCard?->name ?? 'Cartão', 'occurred_at' => $charge->charged_on->toDateString(), 'amount' => $charge->amount, 'is_positive' => false]))
            ->sortByDesc('occurred_at')->take(5)->values()->all();
    }

    private function sum(Collection $values): BigDecimal
    {
        return $values->reduce(fn (BigDecimal $total, mixed $value): BigDecimal => $total->plus((string) $value), BigDecimal::zero());
    }
}
