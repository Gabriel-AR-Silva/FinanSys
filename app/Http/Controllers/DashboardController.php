<?php

namespace App\Http\Controllers;

use App\Actions\RecalculateReceiptForecast;
use App\Enums\CardInstallmentStatus;
use App\Enums\ReceiptForecastStatus;
use App\Http\Requests\IndexDashboardRequest;
use App\Models\CardCharge;
use App\Models\CardInstallment;
use App\Models\Category;
use App\Models\ExpenseCommitment;
use App\Models\ReceiptForecast;
use App\Queries\ConsumptionOverviewQuery;
use App\Queries\FinancialOverviewQuery;
use App\Queries\FinancialPlanningOverviewQuery;
use App\Queries\MonthlyDailyPlanningDashboardQuery;
use App\Queries\PatrimonyOverviewQuery;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(
        IndexDashboardRequest $request,
        FinancialOverviewQuery $overview,
        ConsumptionOverviewQuery $consumption,
        FinancialPlanningOverviewQuery $planning,
        RecalculateReceiptForecast $receiptProgress,
        MonthlyDailyPlanningDashboardQuery $dailyPlanning,
        PatrimonyOverviewQuery $patrimony,
    ): Response {

        $period = (int) $request->validated('period', 30);
        $period = in_array($period, [7, 15, 30, 60, 365], true) ? $period : 30;
        $categoryId = $request->validated('category_id');
        $categoryId = $categoryId === null ? null : (int) $categoryId;

        $month = now('America/Sao_Paulo')->format('Y-m');
        $start = CarbonImmutable::createFromFormat('!Y-m', $month, 'America/Sao_Paulo');
        $pending = BigDecimal::of('0.00');
        $nextDueOn = null;
        $forecasts = ReceiptForecast::query()->whereBelongsTo($request->user())
            ->whereBetween('expected_on', [$start->toDateString(), $start->endOfMonth()->toDateString()])
            ->where('status', '!=', ReceiptForecastStatus::Cancelled->value)
            ->orderBy('expected_on')->orderBy('id')->get();

        foreach ($forecasts as $forecast) {
            $remaining = BigDecimal::of($receiptProgress->calculate($request->user(), $forecast)['pending']);
            if ($remaining->compareTo('0') <= 0) {
                continue;
            }
            $pending = $pending->plus($remaining);
            $nextDueOn ??= $forecast->expected_on->toDateString();
        }

        $dailyPlanningView = $dailyPlanning->forUser($request->user());
        $overviewView = $overview->forUser($request->user(), $period, $categoryId);
        $consumptionView = $consumption->forUser($request->user(), $period, $categoryId);
        $today = CarbonImmutable::now('America/Sao_Paulo')->startOfDay();
        $nextInstallmentDue = CardInstallment::query()->whereBelongsTo($request->user())
            ->where('status', CardInstallmentStatus::Pending)
            ->whereDate('due_on', '>=', $today->toDateString())
            ->min('due_on');
        $nextChargeDue = CardCharge::query()->whereBelongsTo($request->user())
            ->where('status', CardInstallmentStatus::Pending)
            ->whereDate('due_on', '>=', $today->toDateString())
            ->min('due_on');
        $nextDue = collect([$nextInstallmentDue, $nextChargeDue])->filter()->sort()->first();
        $invoiceStart = $nextDue === null
            ? null
            : CarbonImmutable::parse($nextDue, 'America/Sao_Paulo')->startOfMonth();
        $invoiceEnd = $invoiceStart?->endOfMonth();
        $cardInvoicePending = BigDecimal::zero();

        if ($invoiceStart !== null && $invoiceEnd !== null) {
            $cardInvoicePending = CardInstallment::query()->whereBelongsTo($request->user())
                ->where('status', CardInstallmentStatus::Pending)
                ->whereBetween('due_on', [$invoiceStart->toDateString(), $invoiceEnd->toDateString()])
                ->get(['gross_amount', 'paid_amount'])
                ->reduce(
                    fn (BigDecimal $total, CardInstallment $installment): BigDecimal => $total->plus(
                        BigDecimal::of($installment->gross_amount)->minus($installment->paid_amount)
                    ),
                    BigDecimal::zero(),
                )
                ->plus(
                    CardCharge::query()->whereBelongsTo($request->user())
                        ->where('status', CardInstallmentStatus::Pending)
                        ->whereBetween('due_on', [$invoiceStart->toDateString(), $invoiceEnd->toDateString()])
                        ->get(['amount', 'paid_amount'])
                        ->reduce(
                            fn (BigDecimal $total, CardCharge $charge): BigDecimal => $total->plus(
                                BigDecimal::of($charge->amount)->minus($charge->paid_amount)
                            ),
                            BigDecimal::zero(),
                        )
                );
        }

        $commitmentHorizon = $invoiceEnd ?? $today->endOfMonth();
        $otherCommitmentsPending = ExpenseCommitment::query()->whereBelongsTo($request->user())
            ->where('status', 'pending')
            ->whereBetween('due_on', [$today->toDateString(), $commitmentHorizon->toDateString()])
            ->get(['amount', 'paid_amount'])
            ->reduce(
                fn (BigDecimal $total, ExpenseCommitment $commitment): BigDecimal => $total->plus(
                    BigDecimal::of($commitment->amount)->minus($commitment->paid_amount)
                ),
                BigDecimal::zero(),
            );
        $knownCommitments = $cardInvoicePending->plus($otherCommitmentsPending);
        $availableAfterInvoice = BigDecimal::of($overviewView['general_balance'])->minus($cardInvoicePending);
        $availableAfterKnownCommitments = BigDecimal::of($overviewView['general_balance'])->minus($knownCommitments);

        return Inertia::render('Dashboard', [
            'overview' => $overviewView,
            'consumption' => $consumptionView,
            'patrimony' => $patrimony->forUser($request->user(), $overviewView['general_balance'])['summary'],
            'planning' => $planning->forUser($request->user()),
            'cardInvoice' => [
                'month' => $invoiceStart?->format('Y-m'),
                'pending' => (string) $cardInvoicePending,
                'available_after_invoice' => (string) $availableAfterInvoice,
            ],
            'knownCommitments' => [
                'through' => $commitmentHorizon->toDateString(),
                'card' => (string) $cardInvoicePending,
                'other' => (string) $otherCommitmentsPending,
                'total' => (string) $knownCommitments,
                'available_after' => (string) $availableAfterKnownCommitments,
            ],
            'receivables' => ['month' => $month, 'pending' => (string) $pending, 'next_due_on' => $nextDueOn],
            'dailyCheckIns' => $dailyPlanningView['check_ins'],
            'dailyPlanning' => $dailyPlanningView,
            'categories' => Category::query()->whereBelongsTo($request->user())
                ->orderBy('type')->orderBy('name')->get(['id', 'name', 'type', 'status']),
            'filters' => ['period' => $period, 'category_id' => $categoryId],
        ]);
    }
}
