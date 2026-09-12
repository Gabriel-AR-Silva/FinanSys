<?php

namespace App\Http\Controllers;

use App\Actions\CreateCardPurchase;
use App\Http\Requests\StoreCardPurchaseRequest;
use Illuminate\Http\RedirectResponse;

class CardPurchaseController extends Controller
{
    public function store(StoreCardPurchaseRequest $request, CreateCardPurchase $create): RedirectResponse
    {
        $create->handle($request->user(), $request->validated());

        return to_route('credit-cards.index')->with('success', '🧾 Compra parcelada registrada. O futuro já foi avisado 😅');
    }
}
