<?php

namespace App\Http\Controllers;

use App\Actions\RecalculateReceiptForecast;
use App\Enums\ReceiptForecastStatus;
use App\Http\Requests\IndexDashboardRequest;
use App\Models\Category;
use App\Models\ReceiptForecast;
use App\Queries\DailyCheckInCalendarQuery;
use App\Queries\FinancialOverviewQuery;
use App\Queries\FinancialPlanningOverviewQuery;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(IndexDashboardRequest $request, FinancialOverviewQuery $overview, FinancialPlanningOverviewQuery $planning, RecalculateReceiptForecast $receiptProgress, DailyCheckInCalendarQuery $checkIns): Response
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

        return Inertia::render('Dashboard', [
            'overview' => $overview->forUser($request->user(), $period, $categoryId),
            'planning' => $planning->forUser($request->user()),
            'receivables' => ['month' => $month, 'pending' => (string) $pending, 'next_due_on' => $nextDueOn],
            'dailyCheckIns' => $checkIns->forMonth($request->user(), $month),
            'categories' => Category::query()->whereBelongsTo($request->user())
                ->orderBy('type')->orderBy('name')->get(['id', 'name', 'type', 'status']),
            'filters' => ['period' => $period, 'category_id' => $categoryId],
        ]);
    }
}
