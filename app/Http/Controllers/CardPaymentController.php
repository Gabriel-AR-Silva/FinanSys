<?php

namespace App\Http\Controllers;

use App\Actions\PayCreditCard;
use App\Actions\ReverseCardPayment;
use App\Models\CardPayment;
use Illuminate\Http\Request;
use App\Http\Requests\StoreCardPaymentRequest;
use Illuminate\Http\RedirectResponse;

class CardPaymentController extends Controller
{
    public function store(StoreCardPaymentRequest $request, PayCreditCard $pay): RedirectResponse
    {
        $pay->handle($request->user(), $request->validated());

        return to_route('credit-cards.index')->with('success', '✅ Fatura paga e parcelas baixadas. Menos um boleto enchendo o saco.');
    }

    public function destroy(Request $request, string $payment, ReverseCardPayment $reverse): RedirectResponse
    {
        $ownedPayment = CardPayment::query()
            ->whereBelongsTo($request->user())
            ->whereKey($payment)
            ->firstOrFail();

        $reverse->handle($request->user(), $ownedPayment);

        return to_route('credit-cards.index')->with('success', 'Pagamento da fatura removido e baixas revertidas com segurança.');
    }
}
