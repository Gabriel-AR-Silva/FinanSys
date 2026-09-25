<?php

namespace App\Http\Controllers;

use App\Actions\CreateCardPurchase;
use App\Actions\UpdateCardPurchase;
use App\Http\Requests\StoreCardPurchaseRequest;
use App\Models\CardPurchase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CardPurchaseController extends Controller
{
    public function store(StoreCardPurchaseRequest $request, CreateCardPurchase $create): RedirectResponse
    {
        $create->handle($request->user(), $request->validated());

        return to_route('credit-cards.index')->with('success', '🧾 Compra parcelada registrada. O futuro já foi avisado 😅');
    }

    public function update(StoreCardPurchaseRequest $request, string $purchase, UpdateCardPurchase $update): RedirectResponse
    {
        $ownedPurchase = CardPurchase::query()
            ->whereBelongsTo($request->user())
            ->whereKey($purchase)
            ->firstOrFail();

        $update->handle($request->user(), $ownedPurchase, $request->validated());

        return to_route('credit-cards.index')->with('success', 'Compra atualizada e parcelas recalculadas.');
    }

    public function destroy(Request $request, string $purchase): RedirectResponse
    {
        $ownedPurchase = CardPurchase::query()
            ->whereBelongsTo($request->user())
            ->whereKey($purchase)
            ->with(['installments.allocations', 'installments.advanceAllocations'])
            ->firstOrFail();

        $hasFinancialHistory = $ownedPurchase->installments->contains(
            fn ($installment): bool => (string) $installment->paid_amount !== '0.00'
                || $installment->allocations->isNotEmpty()
                || $installment->advanceAllocations->isNotEmpty(),
        );

        if ($hasFinancialHistory) {
            return to_route('credit-cards.index', ['purchase' => $ownedPurchase->id])
                ->with('error', 'Essa compra já possui pagamento ou antecipação. Use o fluxo de correção/estorno para preservar o histórico financeiro.');
        }

        DB::transaction(function () use ($ownedPurchase): void {
            $ownedPurchase->installments()->delete();
            $ownedPurchase->delete();
        });

        return to_route('credit-cards.index')->with('success', 'Compra removida com sucesso.');
    }
}
