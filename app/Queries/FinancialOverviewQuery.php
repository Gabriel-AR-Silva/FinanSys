<?php

namespace App\Queries;

use App\Enums\CardInstallmentStatus;
use App\Enums\LedgerEntryReferenceType;
use App\Enums\LedgerEntryType;
use App\Models\CardCharge;
use App\Models\CardPurchase;
use App\Models\CardPurchaseReversal;
use App\Models\Category;
use App\Models\LedgerEntry;
use App\Models\User;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class FinancialOverviewQuery
{
    public function forUser(User $user, int $period = 30, ?int $categoryId = null): array
    {
        $entries = LedgerEntry::query()->whereBelongsTo($user);
        $start = CarbonImmutable::now('America/Sao_Paulo')->startOfDay()->subDays($period - 1);
        $end = CarbonImmutable::now('America/Sao_Paulo')->endOfDay();
        $incomeEntries = LedgerEntry::query()->whereBelongsTo($user)
            ->where('type', LedgerEntryType::Income)
            ->whereNull('reversal_of_operation_id')
            ->whereBetween('occurred_at', [$start, $end])
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('ledger_entries as reversals')
                ->whereColumn('reversals.reversal_of_operation_id', 'ledger_entries.operation_id')
                ->where('reversals.user_id', $user->id)
                ->whereNull('reversals.deleted_at'))
            ->when($categoryId !== null, fn (Builder $query) => $query->where('category_id', $categoryId))
            ->get(['id', 'amount']);
        $ledgerConsumption = $this->ledgerExpenseConsumption($user, $start, $end, $categoryId);
        $income = $this->sumAmounts($incomeEntries->pluck('amount'));
        $ledgerExpense = $this->sumAmounts($ledgerConsumption->pluck('amount'));
        $cardConsumption = $this->cardConsumption($user, $start, $end, $categoryId);
        $expense = $ledgerExpense->plus($cardConsumption['total']);
        $largestLedgerExpense = $ledgerConsumption->reduce(function (BigDecimal $largest, array $row): BigDecimal {
            $candidate = BigDecimal::of($row['amount']);

            return $candidate->compareTo($largest) > 0 ? $candidate : $largest;
        }, BigDecimal::zero());
        $net = $income->minus($expense);
        $savingsRate = $income->isZero()
            ? null
            : (string) $net->multipliedBy(100)->dividedBy($income, 2, RoundingMode::HalfUp);

        return [
            'general_balance' => $this->balance(clone $entries),
            'accounts_balance' => $this->balance((clone $entries)->where('reference_type', LedgerEntryReferenceType::Account->value)),
            'pockets_balance' => $this->balance((clone $entries)->where('reference_type', LedgerEntryReferenceType::Pocket->value)),
            'monthly_income' => $this->monthlyIncome($user),
            'monthly_expense' => $this->monthlyExpense($user),
            'period_summary' => [
                'income' => (string) $income,
                'expense' => (string) $expense,
                'net' => (string) $net,
                'savings_rate' => $savingsRate,
                'average_daily_expense' => (string) $expense->dividedBy($period, 2, RoundingMode::HalfUp),
                'transaction_count' => $incomeEntries->count() + $ledgerConsumption->count() + $cardConsumption['count'],
                'largest_expense' => (string) ($largestLedgerExpense->compareTo($cardConsumption['largest']) >= 0
                    ? $largestLedgerExpense
                    : $cardConsumption['largest']),
            ],
            'recent_entries' => $this->recentActivity($user, $start, $end, $categoryId),
            'chart' => $this->chart($user, $period),
            'cash_flow' => $this->cashFlow($user, $period, $categoryId),
            'consumption_flow' => $this->consumptionFlow($user, $period, $categoryId),
            'category_breakdown' => $this->categoryBreakdown($user, $period, $categoryId),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function recentActivity(User $user, CarbonImmutable $start, CarbonImmutable $end, ?int $categoryId): array
    {
        $ledger = LedgerEntry::query()
            ->whereBelongsTo($user)
            ->whereBetween('occurred_at', [$start, $end])
            ->when($categoryId !== null, fn (Builder $query) => $query->where('category_id', $categoryId))
            ->with('reference:id,name')
            ->latest('occurred_at')
            ->latest('id')
            ->limit(10)
            ->get()
            ->map(fn (LedgerEntry $entry): array => [
                'id' => 'ledger-'.$entry->id,
                'type' => $entry->type->value,
                'type_label' => $this->typeLabel($entry->type),
                'amount' => $entry->amount,
                'is_positive' => in_array($entry->type, $this->positiveTypes(), true),
                'reference_name' => $entry->reference?->name,
                'occurred_at' => $entry->occurred_at,
                'description' => $entry->description,
                'sort_at' => $entry->occurred_at->timestamp,
                'sort_id' => $entry->id,
            ]);

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
            ->with('creditCard:id,name')
            ->latest('purchased_on')
            ->latest('id')
            ->limit(10)
            ->get()
            ->map(fn (CardPurchase $purchase): array => [
                'id' => 'card-purchase-'.$purchase->id,
                'type' => 'card_purchase',
                'type_label' => 'Compra no cartão',
                'amount' => $purchase->gross_amount,
                'is_positive' => false,
                'reference_name' => $purchase->creditCard?->name,
                'occurred_at' => $purchase->purchased_on->toDateString(),
                'description' => $purchase->description,
                'sort_at' => $purchase->purchased_on->endOfDay()->timestamp,
                'sort_id' => $purchase->id,
            ]);
        $charges = CardCharge::query()
            ->whereBelongsTo($user)
            ->whereBetween('charged_on', [$startDate, $endDate])
            ->where('status', '!=', CardInstallmentStatus::Reversed)
            ->when($categoryId !== null, fn (Builder $query) => $query->where('category_id', $categoryId))
            ->with('creditCard:id,name')
            ->latest('charged_on')
            ->latest('id')
            ->limit(10)
            ->get()
            ->map(fn (CardCharge $charge): array => [
                'id' => 'card-charge-'.$charge->id,
                'type' => 'card_charge',
                'type_label' => 'Encargo do cartão',
                'amount' => $charge->amount,
                'is_positive' => false,
                'reference_name' => $charge->creditCard?->name,
                'occurred_at' => $charge->charged_on->toDateString(),
                'description' => $charge->description,
                'sort_at' => $charge->charged_on->endOfDay()->timestamp,
                'sort_id' => $charge->id,
            ]);

        return $ledger->concat($purchases)->concat($charges)
            ->sortByDesc(fn (array $entry): string => str_pad((string) $entry['sort_at'], 20, '0', STR_PAD_LEFT).':'.str_pad((string) $entry['sort_id'], 20, '0', STR_PAD_LEFT))
            ->take(5)
            ->map(function (array $entry): array {
                unset($entry['sort_at'], $entry['sort_id']);

                return $entry;
            })
            ->values()
            ->all();
    }

    /** @return array{total:BigDecimal,count:int,largest:BigDecimal} */
    private function cardConsumption(User $user, CarbonImmutable $start, CarbonImmutable $end, ?int $categoryId): array
    {
        $amounts = $this->cardConsumptionRows($user, $start, $end, $categoryId)->pluck('amount');
        $total = $this->sumAmounts($amounts);
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
        $incomeRows = LedgerEntry::query()->whereBelongsTo($user)
            ->where('type', LedgerEntryType::Income)
            ->whereNull('reversal_of_operation_id')
            ->whereBetween('occurred_at', [$start, $end])
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('ledger_entries as reversals')
                ->whereColumn('reversals.reversal_of_operation_id', 'ledger_entries.operation_id')
                ->where('reversals.user_id', $user->id)
                ->whereNull('reversals.deleted_at'))
            ->when($categoryId !== null, fn (Builder $query) => $query->where('category_id', $categoryId))
            ->get(['category_id', 'amount'])
            ->map(fn (LedgerEntry $entry): array => [
                'category_id' => $entry->category_id,
                'type' => LedgerEntryType::Income->value,
                'total' => $entry->amount,
            ]);
        $expenseRows = $this->ledgerExpenseConsumption($user, $start, $end, $categoryId)
            ->map(fn (array $row): array => [
                'category_id' => $row['entry']->category_id,
                'type' => LedgerEntryType::Expense->value,
                'total' => $row['amount'],
            ]);
        $rows = $incomeRows->concat($expenseRows);

        $cardRows = $this->cardConsumptionRows($user, $start, $end, $categoryId)
            ->map(fn (array $row): array => [
                'category_id' => $row['category_id'],
                'type' => LedgerEntryType::Expense->value,
                'total' => $row['amount'],
            ]);

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
            })->filter(fn (array $item): bool => BigDecimal::of($item['total'])->isZero() === false)
            ->sortByDesc(fn (array $item): string => (string) BigDecimal::of($item['total'])->abs())
            ->values()->all();
    }

    private function cashFlow(User $user, int $period, ?int $categoryId): array
    {
        $end = CarbonImmutable::now('America/Sao_Paulo')->endOfDay();
        $start = CarbonImmutable::now('America/Sao_Paulo')->startOfDay()->subDays($period - 1);
        $rows = LedgerEntry::query()->whereBelongsTo($user)
            ->whereIn('type', [
                LedgerEntryType::Income,
                LedgerEntryType::Expense,
                LedgerEntryType::Refund,
                LedgerEntryType::CardPayment,
                LedgerEntryType::CardAdvance,
            ])
            ->whereBetween('occurred_at', [$start, $end])
            ->when($categoryId !== null, fn (Builder $query) => $query->where('category_id', $categoryId))
            ->selectRaw('DATE(occurred_at) AS entry_date')
            ->selectRaw(
                'SUM(CASE WHEN type IN (?, ?) THEN amount ELSE 0 END) AS income',
                [LedgerEntryType::Income->value, LedgerEntryType::Refund->value],
            )
            ->selectRaw(
                'SUM(CASE WHEN type IN (?, ?, ?) THEN amount ELSE 0 END) AS expense',
                [LedgerEntryType::Expense->value, LedgerEntryType::CardPayment->value, LedgerEntryType::CardAdvance->value],
            )
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
        $ledgerByDate = $this->ledgerExpenseConsumption($user, $start, $end, $categoryId)
            ->groupBy(fn (array $row): string => $row['entry']->occurred_at->setTimezone('America/Sao_Paulo')->toDateString())
            ->map(fn (Collection $rows): string => (string) $this->sumAmounts($rows->pluck('amount')));
        $cardByDate = $this->cardConsumptionRows($user, $start, $end, $categoryId)
            ->groupBy('date')
            ->map(fn (Collection $rows): string => (string) $this->sumAmounts($rows->pluck('amount')));
        $points = [];

        for ($date = $start; $date->lte($end); $date = $date->addDay()) {
            $key = $date->toDateString();
            $points[] = [
                'date' => $key,
                'realized' => (string) BigDecimal::of($ledgerByDate->get($key, '0'))
                    ->plus($cardByDate->get($key, '0')),
            ];
        }

        return ['period' => $period, 'points' => $points];
    }

    /** @return Collection<int, array{entry:LedgerEntry,amount:string}> */
    private function ledgerExpenseConsumption(User $user, CarbonImmutable $start, CarbonImmutable $end, ?int $categoryId): Collection
    {
        $entries = LedgerEntry::query()
            ->whereBelongsTo($user)
            ->where('type', LedgerEntryType::Expense)
            ->whereNull('reversal_of_operation_id')
            ->whereBetween('occurred_at', [$start, $end])
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('ledger_entries as reversals')
                ->whereColumn('reversals.reversal_of_operation_id', 'ledger_entries.operation_id')
                ->where('reversals.user_id', $user->id)
                ->whereNull('reversals.deleted_at'))
            ->when($categoryId !== null, fn (Builder $query) => $query->where('category_id', $categoryId))
            ->with('expenseRefunds.refundEntry')
            ->get();
        $refundOperations = $entries->pluck('expenseRefunds')->flatten()->pluck('refundEntry')->filter()->pluck('operation_id');
        $reversedRefundOperations = LedgerEntry::query()->whereBelongsTo($user)
            ->whereIn('reversal_of_operation_id', $refundOperations)
            ->pluck('reversal_of_operation_id')
            ->all();

        return $entries->map(fn (LedgerEntry $entry): array => [
            'entry' => $entry,
            'amount' => $this->netExpense($entry, $start, $end, $reversedRefundOperations),
        ]);
    }

    /** @return Collection<int, array{date:string,amount:string,category_id:?int}> */
    private function cardConsumptionRows(User $user, CarbonImmutable $start, CarbonImmutable $end, ?int $categoryId): Collection
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
            ->get(['category_id', 'gross_amount', 'purchased_on'])
            ->map(fn (CardPurchase $purchase): array => [
                'date' => $purchase->purchased_on->toDateString(),
                'amount' => $purchase->gross_amount,
                'category_id' => $purchase->category_id,
            ]);
        $charges = CardCharge::query()
            ->whereBelongsTo($user)
            ->whereBetween('charged_on', [$startDate, $endDate])
            ->where('status', '!=', CardInstallmentStatus::Reversed)
            ->when($categoryId !== null, fn (Builder $query) => $query->where('category_id', $categoryId))
            ->get(['category_id', 'amount', 'charged_on'])
            ->map(fn (CardCharge $charge): array => [
                'date' => $charge->charged_on->toDateString(),
                'amount' => $charge->amount,
                'category_id' => $charge->category_id,
            ]);

        return $purchases->concat($charges)->values();
    }

    /** @param iterable<mixed, string> $amounts */
    private function sumAmounts(iterable $amounts): BigDecimal
    {
        $total = BigDecimal::zero();
        foreach ($amounts as $amount) {
            $total = $total->plus($amount);
        }

        return $total;
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

    private function monthlyIncome(User $user): string
    {
        $start = CarbonImmutable::now('America/Sao_Paulo')->startOfMonth();
        $end = CarbonImmutable::now('America/Sao_Paulo')->endOfMonth();
        $income = LedgerEntry::query()
            ->whereBelongsTo($user)
            ->where('type', LedgerEntryType::Income)
            ->whereNull('reversal_of_operation_id')
            ->whereBetween('occurred_at', [$start, $end])
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('ledger_entries as reversals')
                ->whereColumn('reversals.reversal_of_operation_id', 'ledger_entries.operation_id')
                ->where('reversals.user_id', $user->id)
                ->whereNull('reversals.deleted_at'))
            ->pluck('amount');

        return (string) $this->sumAmounts($income)->toScale(2, RoundingMode::Unnecessary);
    }

    private function monthlyExpense(User $user): string
    {
        $start = CarbonImmutable::now('America/Sao_Paulo')->startOfMonth();
        $end = CarbonImmutable::now('America/Sao_Paulo')->endOfMonth();
        $ledger = $this->sumAmounts($this->ledgerExpenseConsumption($user, $start, $end, null)->pluck('amount'));
        $card = $this->cardConsumption($user, $start, $end, null)['total'];

        return (string) $ledger->plus($card)->toScale(2, RoundingMode::Unnecessary);
    }

}
