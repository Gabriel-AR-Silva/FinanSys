<?php

namespace App\Http\Controllers;

use App\Actions\PrepareOfxImport;
use App\Enums\RecordStatus;
use App\Models\Account;
use App\Models\BankStatementImport;
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

        return Inertia::render('OfxImports/Index', [
            'accounts' => Account::query()->whereBelongsTo($user)->where('status', RecordStatus::Active)->orderBy('name')->get(['id', 'name']),
            'imports' => $imports,
            'review' => $review,
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
            $contents = $validated['file']->get();
            $statement = $parser->parse($contents);
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['file' => $exception->getMessage()]);
        }

        $groups = $relationships->fitIdGroups($statement->transactions);
        $suggestions = $classifier->classify($statement->transactions, $groups);
        $import = $prepare->handle($user, $account, $statement, $suggestions);

        return redirect()->route('ofx-imports.index', ['review' => $import->getKey()])
            ->with('success', 'Extrato analisado. Revise os itens antes de confirmar qualquer lançamento.');
    }
}
