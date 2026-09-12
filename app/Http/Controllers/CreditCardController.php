<?php

namespace App\Http\Controllers;

use App\Actions\CreateCreditCard;
use App\Enums\CategoryType;
use App\Enums\RecordStatus;
use App\Http\Requests\StoreCreditCardRequest;
use App\Models\Category;
use App\Models\CreditCard;
use App\Queries\AccountBalanceQuery;
use Brick\Math\BigDecimal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CreditCardController extends Controller
{
    public function index(Request $request, AccountBalanceQuery $accountBalances): Response
    {
        $cards = CreditCard::query()->whereBelongsTo($request->user())
            ->with(['purchases' => fn ($query) => $query->with(['category:id,name', 'installments'])->latest('purchased_on')->latest('id')])
            ->orderBy('name')->orderBy('id')->get()
            ->map(function (CreditCard $card): array {
                $installments = $card->purchases->pluck('installments')->flatten();
                $pending = $installments->reduce(
                    fn (BigDecimal $total, $installment): BigDecimal => $total->plus(BigDecimal::of($installment->gross_amount)->minus($installment->paid_amount)),
                    BigDecimal::zero(),
                );

                return [
                    'id' => $card->id, 'name' => $card->name, 'closing_day' => $card->closing_day, 'due_day' => $card->due_day,
                    'pending' => (string) $pending,
                    'purchases' => $card->purchases->map(fn ($purchase): array => [
                        'id' => $purchase->id, 'description' => $purchase->description, 'category_name' => $purchase->category->name,
                        'gross_amount' => $purchase->gross_amount, 'purchased_on' => $purchase->purchased_on->toDateString(),
                        'installments_count' => $purchase->installments_count, 'planning_type' => $purchase->planning_type->value,
                        'installments' => $purchase->installments->sortBy('installment_number')->values()->map(fn ($installment): array => [
                            'number' => $installment->installment_number, 'gross_amount' => $installment->gross_amount,
                            'paid_amount' => $installment->paid_amount, 'due_on' => $installment->due_on->toDateString(), 'status' => $installment->status->value,
                        ]),
                    ])->values(),
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
}
