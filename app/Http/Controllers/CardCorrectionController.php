<?php

namespace App\Http\Controllers;

use App\Enums\CardInstallmentStatus;
use App\Enums\RecordStatus;
use App\Models\CardCharge;
use App\Models\CardCredit;
use App\Models\CardInstallment;
use App\Models\CardPurchase;
use App\Models\CreditCard;
use Brick\Math\BigDecimal;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CardCorrectionController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $cards = CreditCard::query()
            ->whereBelongsTo($user)
            ->where('status', RecordStatus::Active)
            ->orderBy('name')
            ->get(['id', 'name']);

        $purchases = CardPurchase::query()
            ->whereBelongsTo($user)
            ->with('creditCard:id,name')
            ->latest('purchased_on')
            ->latest('id')
            ->get()
            ->map(fn (CardPurchase $purchase): array => [
                'id' => $purchase->id,
                'credit_card_id' => $purchase->credit_card_id,
                'card_name' => $purchase->creditCard->name,
                'description' => $purchase->description,
                'gross_amount' => $purchase->gross_amount,
                'purchased_on' => $purchase->purchased_on->toDateString(),
            ]);

        $credits = CardCredit::query()
            ->whereBelongsTo($user)
            ->with('creditCard:id,name')
            ->orderByDesc('credited_on')
            ->orderByDesc('id')
            ->get()
            ->map(fn (CardCredit $credit): array => [
                'id' => $credit->id,
                'credit_card_id' => $credit->credit_card_id,
                'card_name' => $credit->creditCard->name,
                'amount' => $credit->amount,
                'applied_amount' => $credit->applied_amount,
                'remaining_amount' => (string) BigDecimal::of($credit->amount)->minus($credit->applied_amount),
                'credited_on' => $credit->credited_on->toDateString(),
            ])
            ->filter(fn (array $credit): bool => BigDecimal::of($credit['remaining_amount'])->isPositive())
            ->values();

        $installments = CardInstallment::query()
            ->whereBelongsTo($user)
            ->where('status', CardInstallmentStatus::Pending)
            ->whereHas('purchase')
            ->with('purchase:id,credit_card_id,description')
            ->orderBy('due_on')
            ->orderBy('id')
            ->get()
            ->map(fn (CardInstallment $installment): array => [
                'id' => $installment->id,
                'credit_card_id' => $installment->purchase->credit_card_id,
                'type' => 'installment',
                'label' => $installment->purchase->description.' · parcela '.$installment->installment_number,
                'remaining_amount' => (string) BigDecimal::of($installment->gross_amount)->minus($installment->paid_amount),
                'due_on' => $installment->due_on->toDateString(),
            ]);

        $charges = CardCharge::query()
            ->whereBelongsTo($user)
            ->where('status', CardInstallmentStatus::Pending)
            ->orderBy('due_on')
            ->orderBy('id')
            ->get()
            ->map(fn (CardCharge $charge): array => [
                'id' => $charge->id,
                'credit_card_id' => $charge->credit_card_id,
                'type' => 'charge',
                'label' => $charge->description,
                'remaining_amount' => (string) BigDecimal::of($charge->amount)->minus($charge->paid_amount),
                'due_on' => $charge->due_on->toDateString(),
            ]);

        return Inertia::render('CardCorrections/Index', [
            'cards' => $cards,
            'purchases' => $purchases,
            'credits' => $credits,
            'obligations' => $installments->concat($charges)->values(),
            'today' => now('America/Sao_Paulo')->toDateString(),
        ]);
    }
}
