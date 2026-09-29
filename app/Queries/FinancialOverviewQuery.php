<?php

namespace App\Queries;

use App\Enums\CardInstallmentStatus;
use App\Enums\LedgerEntryReferenceType;
use App\Enums\LedgerEntryType;
use App\Models\CardCharge;
use App\Models\CardPurchase;
use App\Models\CardPurchaseReversal;
use App\Models\Category;
use App\Models\ExpenseRefund;
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
        $start = CarbonImmutable::now('America/Sao_Paulo')->startOfDay()->subDays($period - 1);
        $end = CarbonImmutable::now('America/Sao_Paulo')->endOfDay();
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
        $ledgerExpense = BigDecimal::of((string) $periodSummary->expense);
        $cardConsumption = $this->cardConsumption($user, $start, $end, $categoryId);
        $expense = $ledgerExpense->plus($cardConsumption['total']);
        $net = $income->minus($expense);
        $savingsRate = $income->isZero()
            ? null
            : (string) $net->multipliedBy(100)->dividedBy($income, 2, RoundingMode::HalfUp);

        return [
            'general_balance' => $this->balance(clone $entries),
            'accounts_balance' => $this->balance((clone $entries)->where('reference_type', LedgerEntryReferenceType::Account->value)),
            'pockets_balance' => $this->balance((clone $entries)->where('reference_type', LedgerEntryReferenceType::Pocket->value)),
            'monthly_income' => (clone $entries)
                ->where('type', LedgerEntryType::Income)
                ->whereBetween('occurred_at', [now()->startOfMonth(), now()->endOfMonth()])
                ->sum('amount'),
            'monthly_expense' => $this->monthlyExpense($user),
            'period_summary' => [
                'income' => (string) $income,
                'expense' => (string) $expense,
                'net' => (string) $net,
                'savings_rate' => $savingsRate,
                'average_daily_expense' => (string) $expense->dividedBy($period, 2, RoundingMode::HalfUp),
                'transaction_count' => (int) $periodSummary->transaction_count + $cardConsumption['count'],
                'largest_expense' => (string) (BigDecimal::of((string) $periodSummary->largest_expense)->compareTo($cardConsumption['largest']) >= 0
                    ? BigDecimal::of((string) $periodSummary->largest_expense)
                    : $cardConsumption['largest']),
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
            'consumption_flow' => $this->consumptionFlow($user, $period, $categoryId),
            'category_breakdown' => $this->categoryBreakdown($user, $period, $categoryId),
        ];
    }

    /** @return array{total:BigDecimal,count:int,largest:BigDecimal} */
    private function cardConsumption(User $user, CarbonImmutable $start, CarbonImmutable $end, ?int $categoryId): array
    {
        $startDate = $start->setTimezone('America/Sao_Paulo')->toDateString();
        $endDate = $end->setTimezone('America/Sao_Paulo')->toDateString();

        $reversedPurchaseIds = CardPurchaseReversal::query()
            ->whereBelongsTo($user)
            ->whereDate('reversed_on', '<=', $endDate)
            ->pluck('card_purchase_id');

        $purchases = CardPurchase::query()
            ->whereBelongsTo($user)
            ->whereBetween('purchased_on', [$startDate, $endDate])
            ->whereNotIn('id', $reversedPurchaseIds)
            ->when($categoryId !== null, fn (Builder $query) => $query->where('category_id', $categoryId))
            ->get(['gross_amount']);

        $charges = CardCharge::query()
            ->whereBelongsTo($user)
            ->whereBetween('charged_on', [$startDate, $endDate])
            ->where('status', '!=', CardInstallmentStatus::Reversed)
            ->when($categoryId !== null, fn (Builder $query) => $query->where('category_id', $categoryId))
            ->get(['amount']);

        $amounts = $purchases->pluck('gross_amount')->concat($charges->pluck('amount'));
        $total = $amounts->reduce(
            fn (BigDecimal $sum, $amount): BigDecimal => $sum->plus((string) $amount),
            BigDecimal::zero(),
        );
        $largest = $amounts->reduce(
            function (BigDecimal $max, $amount): BigDecimal {
                $candidate = BigDecimal::of((string) $amount);

                return $candidate->compareTo($max) > 0 ? $candidate : $max;
            },
            BigDecimal::zero(),
        );

        return ['total' => $total, 'count' => $amounts->count(), 'largest' => $largest];
    }

    private function categoryBreakdown(User $user, int $period, ?int $categoryId): array
    {
        $start = CarbonImmutable::now('America/Sao_Paulo')->startOfDay()->subDays($period - 1);
        $end = CarbonImmutable::now('America/Sao_Paulo')->endOfDay();
        $rows = LedgerEntry::query()->whereBelongsTo($user)
            ->whereIn('type', [LedgerEntryType::Income, LedgerEntryType::Expense])
            ->whereBetween('occurred_at', [$start, $end])
            ->when($categoryId !== null, fn (Builder $query) => $query->where('category_id', $categoryId))
            ->select(['category_id', 'type'])
            ->selectRaw('SUM(amount) AS total')
            ->groupBy('category_id', 'type')
            ->get()
            ->map(fn (LedgerEntry $row): array => [
                'category_id' => $row->category_id,
                'type' => $row->type->value,
                'total' => (string) BigDecimal::of((string) $row->getAttribute('total')),
            ]);

        $startDate = $start->setTimezone('America/Sao_Paulo')->toDateString();
        $endDate = $end->setTimezone('America/Sao_Paulo')->toDateString();
        $reversedPurchaseIds = CardPurchaseReversal::query()->whereBelongsTo($user)
            ->whereDate('reversed_on', '<=', $endDate)->pluck('card_purchase_id');

        $cardRows = CardPurchase::query()->whereBelongsTo($user)
            ->whereBetween('purchased_on', [$startDate, $endDate])
            ->whereNotIn('id', $reversedPurchaseIds)
            ->when($categoryId !== null, fn (Builder $query) => $query->where('category_id', $categoryId))
            ->get(['category_id', 'gross_amount'])
            ->map(fn (CardPurchase $purchase): array => [
                'category_id' => $purchase->category_id,
                'type' => LedgerEntryType::Expense->value,
                'total' => $purchase->gross_amount,
            ])
            ->concat(
                CardCharge::query()->whereBelongsTo($user)
                    ->whereBetween('charged_on', [$startDate, $endDate])
                    ->where('status', '!=', CardInstallmentStatus::Reversed)
                    ->when($categoryId !== null, fn (Builder $query) => $query->where('category_id', $categoryId))
                    ->get(['category_id', 'amount'])
                    ->map(fn (CardCharge $charge): array => [
                        'category_id' => $charge->category_id,
                        'type' => LedgerEntryType::Expense->value,
                        'total' => $charge->amount,
                    ])
            );

        $rows = $rows->concat($cardRows);
        $categories = Category::query()->whereBelongsTo($user)
            ->whereIn('id', $rows->pluck('category_id')->filter()->unique())
            ->get(['id', 'name', 'type'])->keyBy('id');

        return $rows->map(function (array $row) use ($categories): array {
            $category = $row['category_id'] === null ? null : $categories->get($row['category_id']);
            $total = BigDecimal::of((string) $row['total']);
            $isExpense = $row['type'] === LedgerEntryType::Expense->value;

            return [
                'id' => $category?->id,
                'name' => $category?->name ?? 'Sem categoria',
                'type' => $category?->type->value ?? $row['type'],
                'total' => (string) ($isExpense ? $total->negated() : $total),
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
        $end = CarbonImmutable::now('America/Sao_Paulo')->endOfDay();
        $start = CarbonImmutable::now('America/Sao_Paulo')->startOfDay()->subDays($period - 1);
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

    private function consumptionFlow(User $user, int $period, ?int $categoryId): array
    {
        $end = CarbonImmutable::now('America/Sao_Paulo')->endOfDay();
        $start = CarbonImmutable::now('America/Sao_Paulo')->startOfDay()->subDays($period - 1);
        $points = [];

        for ($date = $start; $date->lte($end); $date = $date->addDay()) {
            $dayStart = $date->startOfDay();
            $dayEnd = $date->endOfDay();
            $ledgerEntries = LedgerEntry::query()
                ->whereBelongsTo($user)
                ->where('type', LedgerEntryType::Expense)
                ->whereBetween('occurred_at', [$dayStart, $dayEnd])
                ->when($categoryId !== null, fn (Builder $query) => $query->where('category_id', $categoryId))
                ->with('expenseRefunds.refundEntry')
                ->get();
            $refundOperations = $ledgerEntries->pluck('expenseRefunds')->flatten()->pluck('refundEntry')->filter()->pluck('operation_id');
            $reversedRefundOperations = LedgerEntry::query()->whereBelongsTo($user)
                ->whereIn('reversal_of_operation_id', $refundOperations)
                ->pluck('reversal_of_operation_id')
                ->all();
            $ledger = $ledgerEntries->reduce(
                fn (BigDecimal $total, LedgerEntry $entry): BigDecimal => $total->plus($this->netExpense($entry, $dayStart, $dayEnd, $reversedRefundOperations)),
                BigDecimal::zero(),
            );
            $card = $this->cardConsumption($user, $dayStart, $dayEnd, $categoryId)['total'];

            $points[] = [
                'date' => $date->toDateString(),
                'realized' => (string) $ledger->plus($card),
            ];
        }

        return ['period' => $period, 'points' => $points];
    }


    /** @param list<string> $reversedRefundOperations */
    private function netExpense(LedgerEntry $entry, CarbonImmutable $start, CarbonImmutable $end, array $reversedRefundOperations): string
    {
        $refund = $entry->expenseRefunds->pluck('refundEntry')->filter()
            ->filter(fn (LedgerEntry $refundEntry): bool => $refundEntry->occurred_at->betweenIncluded($start, $end))
            ->reject(fn (LedgerEntry $refundEntry): bool => in_array($refundEntry->operation_id, $reversedRefundOperations, true))
            ->reduce(fn (BigDecimal $total, LedgerEntry $refundEntry): BigDecimal => $total->plus($refundEntry->amount), BigDecimal::zero());

        return (string) BigDecimal::of($entry->amount)->minus($refund);
    }

    private function chart(User $user, int $period): array
    {
        $end = CarbonImmutable::now('America/Sao_Paulo')->endOfDay();
        $start = CarbonImmutable::now('America/Sao_Paulo')->startOfDay()->subDays($period - 1);
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
        return [LedgerEntryType::OpeningBalance, LedgerEntryType::Income, LedgerEntryType::Refund, LedgerEntryType::TransferIn];
    }

    private function typeLabel(LedgerEntryType $type): string
    {
        return match ($type) {
            LedgerEntryType::OpeningBalance => 'Saldo inicial',
            LedgerEntryType::Income => 'Receita',
            LedgerEntryType::Expense => 'Despesa',
            LedgerEntryType::Refund => 'Reembolso',
            LedgerEntryType::TransferIn => 'Transferência recebida',
            LedgerEntryType::TransferOut => 'Transferência enviada',
            LedgerEntryType::CardPayment => 'Pagamento de cartão',
            LedgerEntryType::CardAdvance => 'Antecipação de cartão',
        };
    }

    private function monthlyExpense(User $user): string
    {
        $start = now('America/Sao_Paulo')->startOfMonth();
        $end = now('America/Sao_Paulo')->endOfMonth();
        $gross = BigDecimal::of((string) LedgerEntry::query()->whereBelongsTo($user)
            ->where('type', LedgerEntryType::Expense)->whereBetween('occurred_at', [$start, $end])->sum('amount'));
        $refundEntries = ExpenseRefund::query()->whereBelongsTo($user)
            ->whereHas('expenseEntry', fn (Builder $query) => $query->whereBetween('occurred_at', [$start, $end]))
            ->whereHas('refundEntry', fn (Builder $query) => $query->whereBetween('occurred_at', [$start, $end]))
            ->with('refundEntry')->get()->pluck('refundEntry')->filter();
        $reversed = LedgerEntry::query()->whereBelongsTo($user)
            ->whereIn('reversal_of_operation_id', $refundEntries->pluck('operation_id'))
            ->pluck('reversal_of_operation_id')->all();
        $refunded = $refundEntries
            ->reject(fn (LedgerEntry $entry): bool => in_array($entry->operation_id, $reversed, true))
            ->reduce(fn (BigDecimal $total, LedgerEntry $entry): BigDecimal => $total->plus($entry->amount), BigDecimal::zero());

        $card = $this->cardConsumption(
            $user,
            CarbonImmutable::instance($start),
            CarbonImmutable::instance($end),
            null,
        )['total'];

        return (string) $gross->minus($refunded)->plus($card)->toScale(2, RoundingMode::Unnecessary);
    }
}
