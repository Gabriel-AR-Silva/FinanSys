<?php

namespace App\Http\Controllers;

use App\Actions\CreateFinancialGoal;
use App\Actions\DeleteFinancialGoal;
use App\Actions\UpdateFinancialGoal;
use App\Http\Requests\StoreFinancialGoalRequest;
use App\Http\Requests\UpdateFinancialGoalRequest;
use App\Queries\FinancialGoalPlanningQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FinancialGoalController extends Controller
{
    public function index(Request $request, FinancialGoalPlanningQuery $goals): JsonResponse
    {
        return response()->json($goals->forUser($request->user()));
    }

    public function store(
        StoreFinancialGoalRequest $request,
        CreateFinancialGoal $create,
        FinancialGoalPlanningQuery $goals,
    ): JsonResponse {
        $data = $request->validated();

        $create->handle(
            $request->user(),
            $data['name'],
            $data['target_amount'],
            $data['target_date'],
            $data['pocket_id'] ?? null,
            $data['operation_id'],
        );

        return response()->json($goals->forUser($request->user()), 201);
    }

    public function update(
        UpdateFinancialGoalRequest $request,
        int $goal,
        UpdateFinancialGoal $update,
        FinancialGoalPlanningQuery $goals,
    ): JsonResponse {
        $data = $request->validated();

        $update->handle(
            $request->user(),
            $goal,
            $data['name'],
            $data['target_amount'],
            $data['target_date'],
            $data['pocket_id'] ?? null,
        );

        return response()->json($goals->forUser($request->user()));
    }

    public function destroy(
        Request $request,
        int $goal,
        DeleteFinancialGoal $delete,
        FinancialGoalPlanningQuery $goals,
    ): JsonResponse {
        $delete->handle($request->user(), $goal);

        return response()->json($goals->forUser($request->user()));
    }
}
