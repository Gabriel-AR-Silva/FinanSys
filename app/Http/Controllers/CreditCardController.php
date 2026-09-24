<?php

namespace App\Http\Controllers;

use App\Actions\CreateCreditCard;
use App\Actions\DeleteCreditCard;
use App\Actions\UpdateCreditCardLimit;
use App\Enums\CardInstallmentStatus;
use App\Enums\CategoryType;
use App\Enums\RecordStatus;
use App\Http\Requests\StoreCreditCardRequest;
use App\Http\Requests\UpdateCreditCardLimitRequest;
use App\Models\Category;
use App\Models\CreditCard;
use App\Queries\AccountBalanceQuery;
use App\Queries\CreditCardLimitQuery;
use Brick\Math\BigDecimal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CreditCardController extends Controller
{
    public function index(Request $request, AccountBalanceQuery $accountBalances, CreditCardLimitQuery $cardLimits): Response
    {
        if ($request->query('purchase') !== null) {
            $purchaseId = $request->query('purchase');
            abort_unless(is_string($purchaseId) && ctype_digit($purchaseId) && (int) $purchaseId > 0, 404);

            $card = CreditCard::query()->whereBelongsTo($request->user())
                ->whereHas('purchases', fn ($query) => $query->whereKey($purchaseId))
                ->with(['purchases' => fn ($query) => $query->whereKey($purchaseId)
                    ->with(['category:id,name', 'installments.advanceAllocations.advance'])])
                ->firstOrFail();
            $purchase = $card->purchases->firstOrFail();

            return Inertia::render('CreditCards/Purchase', [
                'card' => ['id' => $card->id, 'name' => $card->name],
                'purchase' => [
                    'id' => $purchase->id,
                    'description' => $purchase->description,
                    'category_name' => $purchase->category->name,
                    'gross_amount' => $purchase->gross_amount,
                    'purchased_on' => $purchase->purchased_on->toDateString(),
                    'installments_count' => $purchase->installments_count,
                    'installments' => $purchase->installments->sortBy('installment_number')->values()->map(fn ($installment): array => [
                        'number' => $installment->installment_number,
                        'gross_amount' => $installment->gross_amount,
                        'paid_amount' => $installment->paid_amount,
                        'due_on' => $installment->due_on->toDateString(),
                        'status' => $installment->status->value,
                    ]),
                ],
            ]);
        }

        $cards = CreditCard::query()->whereBelongsTo($request->user())
            ->with([
                'purchases' => fn ($query) => $query->with(['category:id,name', 'installments.advanceAllocations.advance'])->latest('purchased_on')->latest('id'),
                'charges' => fn ($query) => $query->with('category:id,name')->latest('charged_on')->latest('id'),
            ])
            ->orderBy('name')->orderBy('id')->get()
            ->map(function (CreditCard $card) use ($cardLimits): array {
                $installments = $card->purchases->pluck('installments')->flatten();
                $pending = $installments->where('status', CardInstallmentStatus::Pending)->reduce(
                    fn (BigDecimal $total, $installment): BigDecimal => $total->plus(BigDecimal::of($installment->gross_amount)->minus($installment->paid_amount)),
                    BigDecimal::zero(),
                )->plus($card->charges->reduce(
                    fn (BigDecimal $total, $charge): BigDecimal => $total->plus(BigDecimal::of($charge->amount)->minus($charge->paid_amount)),
                    BigDecimal::zero(),
                ));

                return [
                    'id' => $card->id, 'name' => $card->name, 'closing_day' => $card->closing_day, 'due_day' => $card->due_day,
                    'pending' => (string) $pending,
                    'limit' => $cardLimits->forCard($card),
                    'charges' => $card->charges->map(fn ($charge): array => [
                        'id' => $charge->id, 'type' => $charge->type->value, 'description' => $charge->description,
                        'category_name' => $charge->category->name, 'planning_type' => $charge->planning_type->value,
                        'amount' => $charge->amount, 'paid_amount' => $charge->paid_amount,
                        'pending_amount' => (string) BigDecimal::of($charge->amount)->minus($charge->paid_amount),
                        'charged_on' => $charge->charged_on->toDateString(), 'due_on' => $charge->due_on->toDateString(),
                        'status' => $charge->status->value,
                    ])->values(),
                    'purchases' => $card->purchases->map(fn ($purchase): array => [
                        'id' => $purchase->id, 'description' => $purchase->description, 'category_name' => $purchase->category->name,
                        'gross_amount' => $purchase->gross_amount, 'purchased_on' => $purchase->purchased_on->toDateString(),
                        'installments_count' => $purchase->installments_count, 'planning_type' => $purchase->planning_type->value,
                        'installments' => $purchase->installments->sortBy('installment_number')->values()->map(fn ($installment): array => [
                            'id' => $installment->id, 'number' => $installment->installment_number, 'gross_amount' => $installment->gross_amount,
                            'paid_amount' => $installment->paid_amount, 'due_on' => $installment->due_on->toDateString(), 'status' => $installment->status->value,
                            'purchased_on' => $purchase->purchased_on->toDateString(),
                            'advance' => ($advanceAllocation = $installment->advanceAllocations->first()) ? [
                                'gross_amount' => $advanceAllocation->gross_amount,
                                'discount_amount' => $advanceAllocation->discount_amount,
                                'net_amount' => $advanceAllocation->net_amount,
                                'advanced_on' => $advanceAllocation->advance->advanced_on->toDateString(),
                                'original_due_on' => $advanceAllocation->original_due_on->toDateString(),
                            ] : null,
                        ]),
                    ])->values(),
                    'may_have_unconfirmed_charges' => $installments->contains(fn ($installment): bool => $installment->status->value === 'pending' && $installment->due_on->isBefore(now('America/Sao_Paulo')->startOfDay())),
                ];
            });

        return Inertia::render('CreditCards/Index', [
            'cards' => $cards,
            'categories' => Category::query()->whereBelongsTo($request->user())->where('type', CategoryType::Expense)
                ->where('status', RecordStatus::Active)->orderBy('name')->get(['id', 'name']),
            'accounts' => $accountBalances->forUser($request->user())->map(fn ($account): array => [
                'id' => $account->id, 'name' => $account->name, 'balance' => $account->balance,
            ]),
            'today' => now('America/Sao_Paulo')->toDateString(),
        ]);
    }

    public function store(StoreCreditCardRequest $request, CreateCreditCard $create): RedirectResponse
    {
        $create->handle($request->user(), $request->validated());

        return to_route('credit-cards.index')->with('success', '💳 Cartão cadastrado. Bora usar sem fazer merda, hein? 😅');
    }

    public function destroy(Request $request, string $card, DeleteCreditCard $delete): RedirectResponse
    {
        $error = $delete->handle($request->user(), (int) $card);

        if ($error !== null) {
            return to_route('credit-cards.index')->with('error', $error);
        }

        return to_route('credit-cards.index')->with('success', 'Cartão removido com sucesso.');
    }

    public function updateLimit(UpdateCreditCardLimitRequest $request, string $card, UpdateCreditCardLimit $update): RedirectResponse
    {
        $ownedCard = CreditCard::query()
            ->whereBelongsTo($request->user())
            ->whereKey($card)
            ->firstOrFail();

        $update->handle($request->user(), $ownedCard, $request->validated('credit_limit'));

        return to_route('credit-cards.index')->with('success', 'Limite do cartão atualizado.');
    }
}
