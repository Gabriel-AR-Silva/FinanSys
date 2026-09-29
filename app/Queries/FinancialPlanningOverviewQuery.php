<?php

namespace App\Queries;

use App\Actions\RecalculateReceiptForecast;
use App\Enums\CardInstallmentStatus;
use App\Enums\ExpensePlanningType;
use App\Enums\LedgerEntryType;
use App\Enums\ReceiptForecastStatus;
use App\Models\CardAdvanceAllocation;
use App\Models\CardCharge;
use App\Models\CardPurchase;
use App\Models\CardPurchaseReversal;
use App\Models\CardInstallment;
use App\Models\EssentialBudget;
use App\Models\ExpenseCommitment;
use App\Models\LedgerEntry;
use App\Models\MonthlyFinancialSetting;
use App\Models\ReceiptForecast;
use App\Models\User;
use App\Support\FinancialPlanningMath;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class FinancialPlanningOverviewQuery
{
    public function __construct(
        private FinancialPlanningMath $math,
        private RecalculateReceiptForecast $recalculateForecast,
    ) {}

    /** @return array<string, mixed> */
    public function forUser(User $user, ?CarbonImmutable $evaluatedAt = null): array
    {
        $now = ($evaluatedAt ?? CarbonImmutable::now('America/Sao_Paulo'))->setTimezone('America/Sao_Paulo');
        $month = $now->format('Y-m');
        $exposure = $this->financialExposure($user, $now);
        $settings = MonthlyFinancialSetting::query()
            ->whereBelongsTo($user)
            ->where('month', $month)
            ->with('essentials')
            ->first();

        if ($settings === null) {
            return [
                'configured' => false,
                'month' => $month,
                'evaluated_at' => $now->toIso8601String(),
                'reasons' => ['Configure a proteção e os gastos essenciais deste mês.'],
                'indicators' => [
                    'realized' => $exposure['realized'],
                    'committed' => $exposure['committed'],
                    'consolidated' => $exposure['consolidated'],
                    'available_now' => null,
                    'realized_daily_pace' => $exposure['realized_daily_pace'],
                    'sustainable_daily_pace' => null,
                    'pace_difference' => null,
                ],
            ];
        }

        $start = $now->startOfMonth();
        $end = $now->endOfMonth();
        $entries = LedgerEntry::query()
            ->whereBelongsTo($user)
            ->whereNull('reversal_of_operation_id')
            ->whereIn('type', [LedgerEntryType::Income, LedgerEntryType::Expense])
            ->whereBetween('occurred_at', [$start, $end])
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('ledger_entries as reversals')
                ->whereColumn('reversals.reversal_of_operation_id', 'ledger_entries.operation_id')
                ->where('reversals.user_id', $user->id)
                ->whereNull('reversals.deleted_at'))
            ->with('expenseRefunds.refundEntry')
            ->get();
        $refundOperations = $entries->pluck('expenseRefunds')->flatten()->pluck('refundEntry')->filter()->pluck('operation_id');
        $reversedRefundOperations = LedgerEntry::query()->whereBelongsTo($user)
            ->whereIn('reversal_of_operation_id', $refundOperations)->pluck('reversal_of_operation_id')->all();
        $expenses = $entries->where('type', LedgerEntryType::Expense)
            ->map(fn (LedgerEntry $entry): array => ['entry' => $entry, 'amount' => $this->netExpense($entry, $start, $end, $reversedRefundOperations)]);
        $income = $this->sum($entries->where('type', LedgerEntryType::Income)->pluck('amount'));
        $pendingIncome = $this->pendingForecastIncome($user, $now->startOfMonth()->toDateString(), $now->startOfMonth()->addMonth()->toDateString());
        $projectedIncome = $income->plus($pendingIncome);
        $currentProtection = $this->math->protection([(string) $income], $settings->protection_type, $settings->protection_value);
        $projectedProtection = $this->math->protection([(string) $projectedIncome], $settings->protection_type, $settings->protection_value);
        $fixed = $this->sum($expenses->filter(fn (array $item): bool => $item['entry']->planning_type === ExpensePlanningType::Fixed)->pluck('amount'));
        $ordinary = $expenses->filter(fn (array $item): bool => $item['entry']->planning_type === ExpensePlanningType::Ordinary);
        $extraordinary = $expenses->filter(fn (array $item): bool => $item['entry']->planning_type === ExpensePlanningType::Extraordinary);
        $unclassified = $expenses->filter(fn (array $item): bool => $item['entry']->planning_type === null);
        $cardCommitments = CardInstallment::query()
            ->whereBelongsTo($user)
            ->where('status', '!=', CardInstallmentStatus::Advanced)
            ->whereBetween('due_on', [$now->startOfMonth()->toDateString(), $now->endOfMonth()->toDateString()])
            ->whereHas('purchase')
            ->with('purchase')
            ->get()
            ->map(fn (CardInstallment $installment): array => [
                'category_id' => $installment->purchase->category_id,
                'planning_type' => $installment->purchase->planning_type,
                'gross' => $installment->gross_amount,
                'paid' => $installment->paid_amount,
                'pending' => (string) BigDecimal::of($installment->gross_amount)->minus($installment->paid_amount),
            ])
            ->concat(
                CardCharge::query()
                    ->whereBelongsTo($user)
                    ->whereBetween('due_on', [$now->startOfMonth()->toDateString(), $now->endOfMonth()->toDateString()])
                    ->get()
                    ->map(fn (CardCharge $charge): array => [
                        'category_id' => $charge->category_id,
                        'planning_type' => $charge->planning_type,
                        'gross' => $charge->amount,
                        'paid' => $charge->paid_amount,
                        'pending' => (string) BigDecimal::of($charge->amount)->minus($charge->paid_amount),
                    ])
            )
            ->concat(
                CardAdvanceAllocation::query()
                    ->whereBelongsTo($user)
                    ->whereHas('advance', fn ($query) => $query
                        ->whereDate('advanced_on', '>=', $now->startOfMonth()->toDateString())
                        ->whereDate('advanced_on', '<=', $now->toDateString()))
                    ->whereHas('installment.purchase')
                    ->with('installment.purchase')
                    ->get()
                    ->map(fn (CardAdvanceAllocation $allocation): array => [
                        'category_id' => $allocation->installment->purchase->category_id,
                        'planning_type' => $allocation->installment->purchase->planning_type,
                        'gross' => $allocation->net_amount,
                        'paid' => $allocation->net_amount,
                        'pending' => '0.00',
                    ])
            );
        $previousCommitments = $this->previousCardCommitments($user, $now);
        $essentialCategoryIds = $settings->essentials->pluck('category_id');
        $essentialProjections = $settings->essentials->map(function (EssentialBudget $budget) use ($ordinary, $extraordinary, $cardCommitments, $now): array {
            $ordinaryForCategory = $ordinary->filter(fn (array $item): bool => $item['entry']->category_id === $budget->category_id);
            $extraordinaryForCategory = $extraordinary->filter(fn (array $item): bool => $item['entry']->category_id === $budget->category_id);
            $cardForCategory = $cardCommitments->where('category_id', $budget->category_id);
            $ordinaryProjection = $this->ordinaryProjection($ordinaryForCategory, $now);
            $extraordinaryTotal = $this->sum($extraordinaryForCategory->pluck('amount'));
            $cardPaid = $this->sum($cardForCategory->pluck('paid'));
            $cardPending = $this->sum($cardForCategory->pluck('pending'));
            $realized = $this->sum($ordinaryForCategory->pluck('amount'))->plus($extraordinaryTotal)->plus($cardPaid);
            $projection = $this->math->essentialProjection($budget->amount, $ordinaryProjection['projected_total'], (string) $extraordinaryTotal->plus($cardPaid), (string) $cardPending);

            return [
                'category_id' => $budget->category_id,
                'budget' => $budget->amount,
                'realized' => (string) $realized,
                'remaining' => $this->math->essentialRemaining($budget->amount, (string) $realized),
                'projected' => $projection['projected_total'],
                'daily_rate' => $ordinaryProjection['daily_rate'],
            ];
        })->values();
        $nonEssentialOrdinary = $ordinary->filter(fn (array $item): bool => ! $essentialCategoryIds->contains($item['entry']->category_id));
        $nonEssentialExtraordinary = $extraordinary->filter(fn (array $item): bool => ! $essentialCategoryIds->contains($item['entry']->category_id));
        $nonEssentialCard = $cardCommitments->filter(fn (array $item): bool => ! $essentialCategoryIds->contains($item['category_id']));
        $nonEssentialProjection = $this->ordinaryProjection($nonEssentialOrdinary, $now);
        $unclassifiedTotal = $this->sum($unclassified->pluck('amount'));
        $cardPaid = $this->sum($cardCommitments->pluck('paid'));
        $variableActual = $this->sum($ordinary->pluck('amount'))->plus($this->sum($extraordinary->pluck('amount')))->plus($unclassifiedTotal)->plus($cardPaid);
        $variableProjected = $this->sum($essentialProjections->pluck('projected'))
            ->plus($nonEssentialProjection['projected_total'])
            ->plus($this->sum($nonEssentialExtraordinary->pluck('amount')))
            ->plus($this->sum($nonEssentialCard->pluck('gross')))
            ->plus($unclassifiedTotal);
        $currentBase = $this->math->variableBase((string) $income, $currentProtection, (string) $fixed, $previousCommitments['total']);
        $projectedBase = $this->math->variableBase((string) $projectedIncome, $projectedProtection, (string) $fixed, $previousCommitments['total']);
        $currentSituation = $this->math->financialSituation($currentBase, (string) $variableActual);
        $projectedSituation = $this->math->financialSituation($projectedBase, (string) $variableProjected);
        $currentDeficit = BigDecimal::of($variableActual)->minus($currentBase);
        $projectedDeficit = BigDecimal::of($variableProjected)->minus($projectedBase);
        $variableRemaining = BigDecimal::of($currentBase)->minus($variableActual);
        $freeMargin = $this->math->freeMargin((string) $variableRemaining, $essentialProjections->pluck('remaining')->all());
        $dailyAvailable = BigDecimal::of($freeMargin)->isNegative() ? '0.00' : $freeMargin;
        $remainingDays = $this->math->remainingDaysInCurrentMonth($now);
        $daily = $this->math->dailyAllocation($dailyAvailable, $remainingDays);

        $realizedDailyPace = $exposure['realized_daily_pace'] === null
            ? null
            : BigDecimal::of($exposure['realized_daily_pace']);
        $sustainableDailyPace = BigDecimal::of($daily['daily_amount']);
        $paceDifference = $realizedDailyPace?->minus($sustainableDailyPace);

        $reasons = [];
        if ($unclassified->isNotEmpty()) {
            $reasons[] = $unclassified->count().' despesa(s) antiga(s) ainda não têm classificação de planejamento.';
        }
        if ($now->day <= 2) {
            $reasons[] = 'O ritmo mensal começa a ser avaliado após dois dias completos.';
        }

        return [
            'configured' => true,
            'complete' => $unclassified->isEmpty(),
            'month' => $month,
            'evaluated_at' => $now->toIso8601String(),
            'reasons' => $reasons,
            'income' => ['received' => (string) $income, 'pending' => (string) $pendingIncome, 'projected' => (string) $projectedIncome],
            'fixed' => (string) $fixed,
            'previous_commitments' => $previousCommitments,
            'variable' => ['realized' => (string) $variableActual, 'projected' => (string) $variableProjected],
            'protection' => ['current' => $currentProtection, 'projected' => $projectedProtection],
            'current' => ['base' => $currentBase, 'deficit' => $currentDeficit->isPositive() ? (string) $currentDeficit : null, 'percentage' => $currentSituation['percentage'], 'situation' => $currentSituation['situation']->value, 'diagnostic_available' => $now->day > 2],
            'projected' => ['base' => $projectedBase, 'deficit' => $projectedDeficit->isPositive() ? (string) $projectedDeficit : null, 'percentage' => $projectedSituation['percentage'], 'situation' => $projectedSituation['situation']->value, 'diagnostic_available' => $now->day > 2],
            'essential_categories' => $essentialProjections->all(),
            'free_margin' => $freeMargin,
            'indicators' => [
                'realized' => $exposure['realized'],
                'committed' => $exposure['committed'],
                'consolidated' => $exposure['consolidated'],
                'available_now' => null,
                'realized_daily_pace' => $realizedDailyPace === null ? null : (string) $realizedDailyPace,
                'sustainable_daily_pace' => (string) $sustainableDailyPace,
                'pace_difference' => $paceDifference === null ? null : (string) $paceDifference,
            ],
            'daily' => [
                'available' => $freeMargin,
                'amount' => $daily['daily_amount'],
                'remainder' => $daily['remainder'],
                'remaining_days' => $remainingDays,
            ],
        ];
    }

    /** @return array{realized:string,committed:string,consolidated:string,realized_daily_pace:?string} */
    private function financialExposure(User $user, CarbonImmutable $now): array
    {
        $start = $now->startOfMonth();
        $end = $now;
        $expenseEntries = LedgerEntry::query()
            ->whereBelongsTo($user)
            ->where('type', LedgerEntryType::Expense)
            ->whereNull('reversal_of_operation_id')
            ->whereBetween('occurred_at', [$start, $end])
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('ledger_entries as reversals')
                ->whereColumn('reversals.reversal_of_operation_id', 'ledger_entries.operation_id')
                ->where('reversals.user_id', $user->id)
                ->whereNull('reversals.deleted_at'))
            ->with('expenseRefunds.refundEntry')
            ->get();
        $refundOperations = $expenseEntries->pluck('expenseRefunds')->flatten()->pluck('refundEntry')->filter()->pluck('operation_id');
        $reversedRefundOperations = LedgerEntry::query()->whereBelongsTo($user)
            ->whereIn('reversal_of_operation_id', $refundOperations)
            ->pluck('reversal_of_operation_id')
            ->all();
        $ledgerConsumption = $expenseEntries->map(fn (LedgerEntry $entry): array => [
            'entry' => $entry,
            'amount' => $this->netExpense($entry, $start, $end, $reversedRefundOperations),
        ]);

        $reversedPurchaseIds = CardPurchaseReversal::query()
            ->whereBelongsTo($user)
            ->whereDate('reversed_on', '<=', $now->toDateString())
            ->pluck('card_purchase_id');
        $purchases = CardPurchase::query()
            ->whereBelongsTo($user)
            ->whereBetween('purchased_on', [$start->toDateString(), $now->toDateString()])
            ->whereNotIn('id', $reversedPurchaseIds)
            ->get(['id', 'gross_amount', 'planning_type', 'purchased_on']);
        $charges = CardCharge::query()
            ->whereBelongsTo($user)
            ->whereBetween('charged_on', [$start->toDateString(), $now->toDateString()])
            ->where('status', '!=', CardInstallmentStatus::Reversed)
            ->get(['id', 'amount', 'planning_type', 'charged_on']);

        $realized = $this->sum($ledgerConsumption->pluck('amount'))
            ->plus($this->sum($purchases->pluck('gross_amount')))
            ->plus($this->sum($charges->pluck('amount')));

        $openInstallments = CardInstallment::query()
            ->whereBelongsTo($user)
            ->where('status', CardInstallmentStatus::Pending)
            ->whereHas('purchase')
            ->with('purchase:id,purchased_on')
            ->get();
        $openCharges = CardCharge::query()
            ->whereBelongsTo($user)
            ->where('status', CardInstallmentStatus::Pending)
            ->get();
        $openExpenseCommitments = ExpenseCommitment::query()
            ->whereBelongsTo($user)
            ->where('status', 'pending')
            ->get(['amount', 'paid_amount']);

        $cardCommitted = $openInstallments->reduce(
            fn (BigDecimal $total, CardInstallment $installment): BigDecimal => $total->plus(BigDecimal::of($installment->gross_amount)->minus($installment->paid_amount)),
            BigDecimal::zero(),
        )->plus($openCharges->reduce(
            fn (BigDecimal $total, CardCharge $charge): BigDecimal => $total->plus(BigDecimal::of($charge->amount)->minus($charge->paid_amount)),
            BigDecimal::zero(),
        ));
        $expenseCommitted = $openExpenseCommitments->reduce(
            fn (BigDecimal $total, ExpenseCommitment $commitment): BigDecimal => $total->plus(BigDecimal::of($commitment->amount)->minus($commitment->paid_amount)),
            BigDecimal::zero(),
        );
        $committed = $cardCommitted->plus($expenseCommitted);

        $cardOverlap = $openInstallments
            ->filter(fn (CardInstallment $installment): bool => $installment->purchase->purchased_on->betweenIncluded($start, $now))
            ->reduce(
                fn (BigDecimal $total, CardInstallment $installment): BigDecimal => $total->plus(BigDecimal::of($installment->gross_amount)->minus($installment->paid_amount)),
                BigDecimal::zero(),
            )
            ->plus($openCharges
                ->filter(fn (CardCharge $charge): bool => $charge->charged_on->betweenIncluded($start, $now))
                ->reduce(
                    fn (BigDecimal $total, CardCharge $charge): BigDecimal => $total->plus(BigDecimal::of($charge->amount)->minus($charge->paid_amount)),
                    BigDecimal::zero(),
                ));
        $incrementalCommitment = $committed->minus($cardOverlap);
        if ($incrementalCommitment->isNegative()) {
            $incrementalCommitment = BigDecimal::zero();
        }
        $consolidated = $realized->plus($incrementalCommitment);

        $completedDays = max(0, $now->day - 1);
        $eligibleCompleted = $this->sum($ledgerConsumption
            ->filter(fn (array $item): bool => $item['entry']->planning_type === ExpensePlanningType::Ordinary
                && $item['entry']->occurred_at->setTimezone('America/Sao_Paulo')->isBefore($now->startOfDay()))
            ->pluck('amount'))
            ->plus($this->sum($purchases
                ->filter(fn (CardPurchase $purchase): bool => $purchase->planning_type === ExpensePlanningType::Ordinary
                    && $purchase->purchased_on->isBefore($now->startOfDay()))
                ->pluck('gross_amount')))
            ->plus($this->sum($charges
                ->filter(fn (CardCharge $charge): bool => $charge->planning_type === ExpensePlanningType::Ordinary
                    && $charge->charged_on->isBefore($now->startOfDay()))
                ->pluck('amount')));
        $realizedDailyPace = $completedDays === 0
            ? null
            : (string) $eligibleCompleted->dividedBy($completedDays, 2, RoundingMode::HalfUp);

        return [
            'realized' => (string) $realized,
            'committed' => (string) $committed,
            'consolidated' => (string) $consolidated,
            'realized_daily_pace' => $realizedDailyPace,
        ];
    }

    /** @param Collection<int, array{entry:LedgerEntry,amount:string}> $expenses */
    private function ordinaryProjection(Collection $expenses, CarbonImmutable $now): array
    {
        $completed = $this->sum($expenses->filter(fn (array $item): bool => $item['entry']->occurred_at->setTimezone('America/Sao_Paulo')->isBefore($now->startOfDay()))->pluck('amount'));
        $today = $this->sum($expenses->filter(fn (array $item): bool => $item['entry']->occurred_at->setTimezone('America/Sao_Paulo')->isSameDay($now))->pluck('amount'));

        return $this->math->ordinaryProjection((string) $completed, $now->day - 1, (string) $today, $now->daysInMonth - $now->day);
    }

    private function pendingForecastIncome(User $user, string $start, string $endExclusive): BigDecimal
    {
        return ReceiptForecast::query()->whereBelongsTo($user)
            ->where('status', '!=', ReceiptForecastStatus::Cancelled)
            ->where('expected_on', '>=', $start)
            ->where('expected_on', '<', $endExclusive)
            ->get()
            ->reduce(fn (BigDecimal $total, ReceiptForecast $forecast): BigDecimal => $total->plus($this->recalculateForecast->calculate($user, $forecast)['pending']), BigDecimal::zero());
    }

    /** @return array{total:string,paid_this_month:string,pending:string} */
    private function previousCardCommitments(User $user, CarbonImmutable $now): array
    {
        $start = $now->startOfMonth()->toDateString();
        $end = $now->endOfMonth()->toDateString();
        $installments = CardInstallment::query()
            ->whereBelongsTo($user)
            ->where('status', '!=', CardInstallmentStatus::Advanced)
            ->whereDate('due_on', '<', $start)
            ->whereHas('purchase')
            ->with(['allocations' => fn ($query) => $query->whereHas('payment', fn ($payment) => $payment->whereBetween('paid_on', [$start, $end]))])
            ->get();
        $charges = CardCharge::query()
            ->whereBelongsTo($user)
            ->whereDate('due_on', '<', $start)
            ->with(['allocations' => fn ($query) => $query->whereHas('payment', fn ($payment) => $payment->whereBetween('paid_on', [$start, $end]))])
            ->get();
        $paidThisMonth = $installments->reduce(
            fn (BigDecimal $total, CardInstallment $installment): BigDecimal => $total->plus($this->sum($installment->allocations->pluck('amount'))),
            BigDecimal::zero(),
        );
        $pending = $installments->reduce(
            fn (BigDecimal $total, CardInstallment $installment): BigDecimal => $total->plus(BigDecimal::of($installment->gross_amount)->minus($installment->paid_amount)),
            BigDecimal::zero(),
        );
        $paidThisMonth = $charges->reduce(
            fn (BigDecimal $total, CardCharge $charge): BigDecimal => $total->plus($this->sum($charge->allocations->pluck('amount'))),
            $paidThisMonth,
        );
        $pending = $charges->reduce(
            fn (BigDecimal $total, CardCharge $charge): BigDecimal => $total->plus(BigDecimal::of($charge->amount)->minus($charge->paid_amount)),
            $pending,
        );

        return [
            'total' => (string) $paidThisMonth->plus($pending),
            'paid_this_month' => (string) $paidThisMonth,
            'pending' => (string) $pending,
        ];
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

    /** @param iterable<mixed, string> $amounts */
    private function sum(iterable $amounts): BigDecimal
    {
        $total = BigDecimal::zero();
        foreach ($amounts as $amount) {
            $total = $total->plus($amount);
        }

        return $total;
    }
}
