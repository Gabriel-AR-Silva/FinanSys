<?php

namespace App\Http\Controllers;

use App\Actions\CreateCardCharge;
use App\Http\Requests\StoreCardChargeRequest;
use Illuminate\Http\RedirectResponse;

class CardChargeController extends Controller
{
    public function store(StoreCardChargeRequest $request, CreateCardCharge $create): RedirectResponse
    {
        $create->handle($request->user(), $request->validated());

        return to_route('credit-cards.index')->with('success', 'Encargo confirmado e incluído na dívida do cartão.');
    }
}
