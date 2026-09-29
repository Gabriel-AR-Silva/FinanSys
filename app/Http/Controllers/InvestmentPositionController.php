<?php

namespace App\Http\Controllers;

use App\Actions\CreateInvestmentPosition;
use App\Actions\RecordInvestmentMovement;
use App\Actions\UpdateInvestmentValuation;
use App\Http\Requests\StoreInvestmentMovementRequest;
use App\Http\Requests\StoreInvestmentPositionRequest;
use App\Http\Requests\UpdateInvestmentValuationRequest;
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

    public function storeMovement(
        StoreInvestmentMovementRequest $request,
        int $position,
        RecordInvestmentMovement $record,
    ) {
        $record->handle($request->user(), $position, $request->validated());

        return to_route('investments.index');
    }

    public function updateValuation(
        UpdateInvestmentValuationRequest $request,
        int $position,
        UpdateInvestmentValuation $update,
    ) {
        $data = $request->validated();
        $update->handle($request->user(), $position, $data['current_value'], $data['valued_on']);

        return to_route('investments.index');
    }
}
