<?php

namespace App\Http\Controllers;

use App\Actions\ReverseCardPurchase;
use App\Http\Requests\StoreCardPurchaseReversalRequest;
use Illuminate\Http\RedirectResponse;

class CardPurchaseReversalController extends Controller
{
    public function store(StoreCardPurchaseReversalRequest $request, ReverseCardPurchase $reverse): RedirectResponse
    {
        $reversal = $reverse->handle($request->user(), $request->validated());

        $message = $reversal->credited_paid_amount === '0.00'
            ? '↩️ Compra estornada. As parcelas pendentes saíram do planejamento.'
            : '↩️ Compra estornada e o valor já pago virou crédito do cartão, sem entrar como renda.';

        return to_route('credit-cards.index')->with('success', $message);
    }
}
