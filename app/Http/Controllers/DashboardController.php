<?php

namespace App\Http\Controllers;

use App\Queries\FinancialOverviewQuery;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, FinancialOverviewQuery $overview): Response
    {
        $period = $request->integer('period', 30);
        if (! in_array($period, [7, 15, 30, 60, 365], true)) {
            $period = 30;
        }

        return Inertia::render('Dashboard', [
            'overview' => $overview->forUser($request->user(), $period),
        ]);
    }
}
