<?php

namespace App\Http\Controllers;

use App\Actions\AdvanceCardInstallments;
use App\Http\Requests\StoreCardAdvanceRequest;
use Illuminate\Http\RedirectResponse;

class CardAdvanceController extends Controller
{
    public function store(StoreCardAdvanceRequest $request, AdvanceCardInstallments $advance): RedirectResponse
    {
        $advance->handle($request->user(), $request->validated());

        return to_route('credit-cards.index')->with('success', '✅ Parcelas antecipadas e desconto preservado. Menos dívida futura enchendo o saco.');
    }
}
