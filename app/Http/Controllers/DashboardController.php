<?php

namespace App\Http\Controllers;

use App\Actions\RecalculateReceiptForecast;
use App\Enums\CardInstallmentStatus;
use App\Enums\ReceiptForecastStatus;
use App\Enums\RecordStatus;
use App\Http\Requests\IndexDashboardRequest;
use App\Models\CardCharge;
use App\Models\CardInstallment;
use App\Models\Category;
use App\Models\CreditCard;
use App\Models\ReceiptForecast;
use App\Queries\FinancialOverviewQuery;
use App\Queries\FinancialPlanningOverviewQuery;
use App\Queries\MonthlyDailyPlanningDashboardQuery;
use App\Queries\PatrimonyOverviewQuery;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(IndexDashboardRequest $request, FinancialOverviewQuery $overview, FinancialPlanningOverviewQuery $planning, RecalculateReceiptForecast $receiptProgress, MonthlyDailyPlanningDashboardQuery $dailyPlanning, PatrimonyOverviewQuery $patrimony): Response
    {
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
        $invoiceStart = $start->addMonth()->startOfMonth();
        $invoiceEnd = $invoiceStart->endOfMonth();
        $currentInvoiceStart = $start->startOfMonth();
        $currentInvoiceEnd = $start->endOfMonth();
        $currentCardCommitment = CardInstallment::query()
            ->whereBelongsTo($request->user())
            ->where('status', CardInstallmentStatus::Pending)
            ->whereBetween('due_on', [$currentInvoiceStart->toDateString(), $currentInvoiceEnd->toDateString()])
            ->get()
            ->reduce(fn (BigDecimal $total, CardInstallment $installment): BigDecimal => $total->plus(BigDecimal::of($installment->gross_amount)->minus($installment->paid_amount)), BigDecimal::zero())
            ->plus(CardCharge::query()
                ->whereBelongsTo($request->user())
                ->whereBetween('due_on', [$currentInvoiceStart->toDateString(), $currentInvoiceEnd->toDateString()])
                ->get()
                ->reduce(fn (BigDecimal $total, CardCharge $charge): BigDecimal => $total->plus(BigDecimal::of($charge->amount)->minus($charge->paid_amount)), BigDecimal::zero()));

        $cardInvoicePending = CardInstallment::query()
            ->whereBelongsTo($request->user())
            ->where('status', CardInstallmentStatus::Pending)
            ->whereBetween('due_on', [$invoiceStart->toDateString(), $invoiceEnd->toDateString()])
            ->get()
            ->reduce(fn (BigDecimal $total, CardInstallment $installment): BigDecimal => $total->plus(BigDecimal::of($installment->gross_amount)->minus($installment->paid_amount)), BigDecimal::zero())
            ->plus(CardCharge::query()
                ->whereBelongsTo($request->user())
                ->whereBetween('due_on', [$invoiceStart->toDateString(), $invoiceEnd->toDateString()])
                ->get()
                ->reduce(fn (BigDecimal $total, CardCharge $charge): BigDecimal => $total->plus(BigDecimal::of($charge->amount)->minus($charge->paid_amount)), BigDecimal::zero()));

        $openInvoicePending = BigDecimal::zero();
        $nextOpenInvoiceDueOn = null;
        $nextOpenInvoicePending = BigDecimal::zero();
        $today = CarbonImmutable::now('America/Sao_Paulo')->startOfDay();
        $cards = CreditCard::query()
            ->whereBelongsTo($request->user())
            ->where('status', RecordStatus::Active->value)
            ->get(['id', 'closing_day', 'due_day']);

        foreach ($cards as $card) {
            $dueOn = $this->openInvoiceDueOn($today, $card->closing_day, $card->due_day);
            $dueStart = $dueOn->startOfMonth();
            $dueEnd = $dueOn->endOfMonth();

            $cardPending = CardInstallment::query()
                ->whereBelongsTo($request->user())
                ->whereHas('purchase', fn ($query) => $query->where('credit_card_id', $card->id))
                ->where('status', CardInstallmentStatus::Pending)
                ->whereBetween('due_on', [$dueStart->toDateString(), $dueEnd->toDateString()])
                ->get()
                ->reduce(fn (BigDecimal $total, CardInstallment $installment): BigDecimal => $total->plus(BigDecimal::of($installment->gross_amount)->minus($installment->paid_amount)), BigDecimal::zero())
                ->plus(CardCharge::query()
                    ->whereBelongsTo($request->user())
                    ->where('credit_card_id', $card->id)
                    ->where('status', CardInstallmentStatus::Pending)
                    ->whereBetween('due_on', [$dueStart->toDateString(), $dueEnd->toDateString()])
                    ->get()
                    ->reduce(fn (BigDecimal $total, CardCharge $charge): BigDecimal => $total->plus(BigDecimal::of($charge->amount)->minus($charge->paid_amount)), BigDecimal::zero()));

            $openInvoicePending = $openInvoicePending->plus($cardPending);

            if ($cardPending->isPositive()) {
                $dueDate = $dueOn->toDateString();
                if ($nextOpenInvoiceDueOn === null || $dueDate < $nextOpenInvoiceDueOn) {
                    $nextOpenInvoiceDueOn = $dueDate;
                    $nextOpenInvoicePending = $cardPending;
                } elseif ($dueDate === $nextOpenInvoiceDueOn) {
                    $nextOpenInvoicePending = $nextOpenInvoicePending->plus($cardPending);
                }
            }
        }

        $forecastBeforeNextInvoice = BigDecimal::zero();
        if ($nextOpenInvoiceDueOn !== null) {
            $invoiceForecasts = ReceiptForecast::query()
                ->whereBelongsTo($request->user())
                ->whereBetween('expected_on', [$today->toDateString(), $nextOpenInvoiceDueOn])
                ->where('status', '!=', ReceiptForecastStatus::Cancelled->value)
                ->orderBy('expected_on')
                ->get();

            foreach ($invoiceForecasts as $forecast) {
                $forecastBeforeNextInvoice = $forecastBeforeNextInvoice
                    ->plus($receiptProgress->calculate($request->user(), $forecast)['pending']);
            }
        }

        $forecastInvoiceDifference = $nextOpenInvoiceDueOn === null
            ? null
            : (string) $forecastBeforeNextInvoice->minus($nextOpenInvoicePending);

        $planningView = $planning->forUser($request->user());
        if (isset($planningView['indicators'])) {
            $availableNow = BigDecimal::of($overviewView['general_balance'])
                ->minus($planningView['indicators']['committed']);
            $planningView['indicators']['available_now'] = (string) $availableNow;

            if (($planningView['configured'] ?? false) === true && $planningView['indicators']['sustainable_daily_pace'] !== null) {
                $remainingDays = max(1, (int) $planningView['daily']['remaining_days']);
                $cashDailyCapacity = $availableNow->isPositive()
                    ? $availableNow->dividedBy($remainingDays, 2, RoundingMode::Down)
                    : BigDecimal::zero();
                $plannedDailyCapacity = BigDecimal::of($planningView['indicators']['sustainable_daily_pace']);
                $sustainableDailyPace = $plannedDailyCapacity->compareTo($cashDailyCapacity) <= 0
                    ? $plannedDailyCapacity
                    : $cashDailyCapacity;
                $planningView['indicators']['sustainable_daily_pace'] = (string) $sustainableDailyPace;
                $planningView['indicators']['pace_difference'] = $planningView['indicators']['realized_daily_pace'] === null
                    ? null
                    : (string) BigDecimal::of($planningView['indicators']['realized_daily_pace'])->minus($sustainableDailyPace);
            }
        }

        return Inertia::render('Dashboard', [
            'overview' => $overviewView,
            'patrimony' => $patrimony->forUser($request->user(), $overviewView['general_balance'])['summary'],
            'planning' => $planningView,
            'cardInvoice' => [
                'current_month' => $currentInvoiceStart->format('Y-m'),
                'current_pending' => (string) $currentCardCommitment,
                'open_pending' => (string) $openInvoicePending,
                'next_due_on' => $nextOpenInvoiceDueOn,
                'next_due_pending' => (string) $nextOpenInvoicePending,
                'forecast_before_next_due' => (string) $forecastBeforeNextInvoice,
                'forecast_difference' => $forecastInvoiceDifference,
                'month' => $invoiceStart->format('Y-m'),
                'pending' => (string) $cardInvoicePending,
            ],
            'receivables' => ['month' => $month, 'pending' => (string) $pending, 'next_due_on' => $nextDueOn],
            'dailyCheckIns' => $dailyPlanningView['check_ins'],
            'dailyPlanning' => $dailyPlanningView,
            'categories' => Category::query()->whereBelongsTo($request->user())
                ->orderBy('type')->orderBy('name')->get(['id', 'name', 'type', 'status']),
            'filters' => ['period' => $period, 'category_id' => $categoryId],
        ]);
    }

    private function openInvoiceDueOn(CarbonImmutable $today, int $closingDay, int $dueDay): CarbonImmutable
    {
        $closingDayThisMonth = min($closingDay, $today->daysInMonth);
        $closingMonth = $today->day >= $closingDayThisMonth
            ? $today->startOfMonth()->addMonth()
            : $today->startOfMonth();

        $closingDate = $closingMonth->day(min($closingDay, $closingMonth->daysInMonth));
        $dueMonth = $closingMonth;
        $dueDate = $dueMonth->day(min($dueDay, $dueMonth->daysInMonth));

        if ($dueDate->lessThanOrEqualTo($closingDate)) {
            $dueMonth = $dueMonth->addMonth()->startOfMonth();
            $dueDate = $dueMonth->day(min($dueDay, $dueMonth->daysInMonth));
        }

        return $dueDate;
    }
}
