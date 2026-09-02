<?php

namespace App\Http\Controllers;

use App\Http\Requests\IndexDashboardRequest;
use App\Models\Category;
use App\Queries\FinancialOverviewQuery;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(IndexDashboardRequest $request, FinancialOverviewQuery $overview): Response
    {
        $period = (int) $request->validated('period', 30);
        $period = in_array($period, [7, 15, 30, 60, 365], true) ? $period : 30;
        $categoryId = $request->validated('category_id');
        $categoryId = $categoryId === null ? null : (int) $categoryId;

        return Inertia::render('Dashboard', [
            'overview' => $overview->forUser($request->user(), $period, $categoryId),
            'categories' => Category::query()->whereBelongsTo($request->user())
                ->orderBy('type')->orderBy('name')->get(['id', 'name', 'type', 'status']),
            'filters' => ['period' => $period, 'category_id' => $categoryId],
        ]);
    }
}
