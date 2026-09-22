<?php

namespace App\Http\Controllers;

use App\Actions\SetDailyBudget;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DailyBudgetController extends Controller
{
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

        return to_route('financial-settings.edit')->with('success', 'Orçamento diário atualizado.');
    }
}
