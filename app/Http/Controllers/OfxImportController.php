<?php

namespace App\Http\Controllers;

use App\Actions\PrepareOfxImport;
use App\Enums\RecordStatus;
use App\Models\Account;
use App\Models\BankStatementImport;
use App\Models\CardPurchase;
use App\Models\Category;
use App\Models\CreditCard;
use App\Models\User;
use App\Support\OfxClassifier;
use App\Support\OfxParser;
use App\Support\OfxRelationshipDetector;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class OfxImportController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $reviewId = $request->integer('review');

        $imports = BankStatementImport::query()
            ->whereBelongsTo($user)
            ->latest()
            ->limit(20)
            ->get();

        $review = $reviewId > 0
            ? BankStatementImport::query()->whereBelongsTo($user)->with('items')->find($reviewId)
            : null;

        $pixPairs = $review?->items
            ->filter(fn ($item) => $item->classification->value === 'card_credit_pix_candidate' && $item->relationship_key !== null)
            ->groupBy('relationship_key')
            ->filter(function ($items): bool {
                if ($items->count() !== 2) {
                    return false;
                }

                $credit = $items->firstWhere('direction', 'credit');
                $debit = $items->firstWhere('direction', 'debit');

                return $credit !== null && $debit !== null
                    && (string) $credit->amount === (string) $debit->amount
                    && $credit->occurred_at->setTimezone('America/Sao_Paulo')->toDateString()
                    === $debit->occurred_at->setTimezone('America/Sao_Paulo')->toDateString();
            })
            ->map(function ($items) use ($user): array {
                $debit = $items->firstWhere('direction', 'debit');
                $purchaseId = $items->every(fn ($item) => $item->review_status->value === 'confirmed' && $item->domain_type === CardPurchase::class && $item->domain_id === $debit->domain_id)
                    ? $debit->domain_id : null;

                return [
                    'credit_item_id' => $items->firstWhere('direction', 'credit')->getKey(),
                    'debit_item_id' => $debit->getKey(),
                    'amount' => $debit->amount,
                    'purchase_id' => $purchaseId === null ? null : CardPurchase::query()->whereBelongsTo($user)->whereKey($purchaseId)->value('id'),
                    'bank_effect' => '0.00',
                ];
            })->values()->all() ?? [];

        return Inertia::render('OfxImports/Index', [
            'accounts' => Account::query()->whereBelongsTo($user)->where('status', RecordStatus::Active)->orderBy('name')->get(['id', 'name']),
            'cards' => CreditCard::query()->whereBelongsTo($user)->where('status', RecordStatus::Active)->orderBy('name')->get(['id', 'name', 'closing_day', 'due_day']),
            'categories' => Category::query()->whereBelongsTo($user)->where('status', RecordStatus::Active)->orderBy('name')->get(['id', 'name', 'type']),
            'imports' => $imports,
            'review' => $review,
            'pixPairs' => $pixPairs,
        ]);
    }

    public function store(Request $request, OfxParser $parser, OfxRelationshipDetector $relationships, OfxClassifier $classifier, PrepareOfxImport $prepare): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $validated = $request->validate([
            'account_id' => ['required', 'integer'],
            'file' => ['required', 'file', 'max:2048'],
        ]);

        $account = Account::query()
            ->whereBelongsTo($user)
            ->where('status', RecordStatus::Active)
            ->findOrFail($validated['account_id']);

        try {
            $contents = file_get_contents($validated['file']->getPathname());
            if ($contents === false) {
                throw new InvalidArgumentException('Não foi possível ler o arquivo OFX enviado.');
            }

            $statement = $parser->parse($contents);
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['file' => $exception->getMessage()]);
        }

        if (strtoupper($statement->currency) !== 'BRL') {
            return back()->withErrors([
                'file' => 'Esta versão aceita somente extratos em BRL. Nenhum lançamento foi criado.',
            ]);
        }

        $groups = $relationships->fitIdGroups($statement->transactions);
        $suggestions = $classifier->classify($statement->transactions, $groups);
        $import = $prepare->handle($user, $account, $statement, $suggestions);

        return redirect()->route('ofx-imports.index', ['review' => $import->getKey()])
            ->with('success', 'Extrato analisado. Revise os itens antes de confirmar qualquer lançamento.');
    }
}
