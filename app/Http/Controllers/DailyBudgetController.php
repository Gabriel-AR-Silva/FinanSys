<?php

namespace App\Http\Controllers;

use App\Actions\SetDailyBudget;
use App\Queries\DailyBudgetSnapshotQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DailyBudgetController extends Controller
{
    public function edit(Request $request, DailyBudgetSnapshotQuery $budgets): Response
    {
        $now = now('America/Sao_Paulo');

        return Inertia::render('DailyBudgets/Edit', [
            'localDate' => $now->toDateString(),
            'currentBudget' => $budgets->forOpenDay($request->user(), $now->toDateString(), $now->format('Y-m-d\TH:i:sP')),
        ]);
    }

    public function store(Request $request, SetDailyBudget $setDailyBudget): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'string', 'regex:/^(0|[1-9]\\d{0,16})(?:\\.\\d{1,2})?$/D'],
            'operation_id' => ['required', 'uuid'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $setDailyBudget->handle(
            $request->user(),
            $data['amount'],
            $data['operation_id'],
            $data['reason'] ?? null,
        );

        return to_route('daily-budgets.edit')->with('success', 'Orçamento diário atualizado.');
    }
}
