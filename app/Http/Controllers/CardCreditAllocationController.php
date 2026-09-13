<?php

namespace App\Http\Controllers;

use App\Actions\ApplyCardCredit;
use App\Http\Requests\StoreCardCreditAllocationRequest;
use Illuminate\Http\RedirectResponse;

class CardCreditAllocationController extends Controller
{
    public function store(StoreCardCreditAllocationRequest $request, ApplyCardCredit $apply): RedirectResponse
    {
        $apply->handle($request->user(), $request->validated());

        return to_route('credit-cards.index')->with('success', '💳 Crédito aplicado à obrigação selecionada sem criar nova saída de caixa.');
    }
}
