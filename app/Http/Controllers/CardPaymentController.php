<?php

namespace App\Http\Controllers;

use App\Actions\PayCreditCard;
use App\Http\Requests\StoreCardPaymentRequest;
use Illuminate\Http\RedirectResponse;

class CardPaymentController extends Controller
{
    public function store(StoreCardPaymentRequest $request, PayCreditCard $pay): RedirectResponse
    {
        $pay->handle($request->user(), $request->validated());

        return to_route('credit-cards.index')->with('success', '✅ Fatura paga e parcelas baixadas. Menos um boleto enchendo o saco.');
    }
}
