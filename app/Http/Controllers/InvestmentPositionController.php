<?php

namespace App\Http\Controllers;

use App\Actions\CreateInvestmentPosition;
use App\Http\Requests\StoreInvestmentPositionRequest;
use App\Queries\InvestmentOverviewQuery;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InvestmentPositionController extends Controller
{
    public function index(Request $request, InvestmentOverviewQuery $investments): Response
    {
        return Inertia::render('Investments/Index', [
            'investments' => $investments->forUser($request->user()),
        ]);
    }

    public function store(
        StoreInvestmentPositionRequest $request,
        CreateInvestmentPosition $create,
    ) {
        $create->handle($request->user(), $request->validated());

        return to_route('investments.index');
    }
}
