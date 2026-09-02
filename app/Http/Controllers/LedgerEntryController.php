<?php

namespace App\Http\Controllers;

use App\Actions\CreateManualLedgerEntry;
use App\Actions\DeleteManualLedgerEntry;
use App\Enums\LedgerEntryType;
use App\Enums\RecordStatus;
use App\Http\Requests\IndexLedgerEntryRequest;
use App\Http\Requests\StoreLedgerEntryRequest;
use App\Models\Account;
use App\Models\Category;
use App\Models\Pocket;
use App\Queries\AccountBalanceQuery;
use App\Queries\LedgerEntryIndexQuery;
use App\Queries\PocketBalanceQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LedgerEntryController extends Controller
{
    public function index(IndexLedgerEntryRequest $request, LedgerEntryIndexQuery $entries, AccountBalanceQuery $accountBalances, PocketBalanceQuery $pocketBalances): Response
    {
        $filters = [
            'type' => $request->validated('type', 'all'),
            'account_id' => $request->validated('account_id'),
            'category_id' => $request->validated('category_id') === null ? null : (int) $request->validated('category_id'),
            'period' => $request->validated('period', 'all'),
        ];
        $accounts = Account::query()->whereBelongsTo($request->user())->where('status', RecordStatus::Active)
            ->orderBy('name')->orderBy('id')->get(['id', 'name']);
        $categories = Category::query()->whereBelongsTo($request->user())
            ->orderBy('type')->orderBy('name')->orderBy('id')->get(['id', 'name', 'type', 'status']);
        $activeAccountIds = $accounts->pluck('id');
        $activePocketIds = Pocket::query()
            ->whereBelongsTo($request->user())
            ->where('status', RecordStatus::Active)
            ->whereIn('account_id', $activeAccountIds)
            ->pluck('id');
        $transferReferences = $accountBalances->forUser($request->user())
            ->whereIn('id', $activeAccountIds)
            ->map(fn (Account $account): array => [
                'key' => 'account:'.$account->id,
                'type' => 'account',
                'id' => $account->id,
                'name' => $account->name,
                'context' => 'Conta',
                'balance' => $account->balance,
            ])
            ->concat($pocketBalances->forUser($request->user())
                ->whereIn('id', $activePocketIds)
                ->map(fn (Pocket $pocket): array => [
                    'key' => 'pocket:'.$pocket->id,
                    'type' => 'pocket',
                    'id' => $pocket->id,
                    'name' => $pocket->name,
                    'context' => 'Caixinha · '.$pocket->account->name,
                    'balance' => $pocket->balance,
                ]))
            ->values();

        return Inertia::render('LedgerEntries/Index', [
            'entries' => $entries->paginateForUser($request->user(), $filters),
            'deletedEntries' => $entries->restorableForUser($request->user()),
            'accounts' => $accounts,
            'categories' => $categories,
            'transferReferences' => $transferReferences,
            'filters' => $filters,
        ]);
    }

    public function store(StoreLedgerEntryRequest $request, CreateManualLedgerEntry $createEntry): RedirectResponse
    {
        $data = $request->validated();
        $createEntry->handle($request->user(), (int) $data['account_id'], (int) $data['category_id'], LedgerEntryType::from($data['type']), $data['amount'], $data['occurred_at'], $data['description'] ?? null, $data['operation_id']);

        return to_route('ledger-entries.index')->with('success', 'Lançamento registrado com sucesso.');
    }

    public function destroy(Request $request, int $ledgerEntry, DeleteManualLedgerEntry $deleteEntry): RedirectResponse
    {
        $deleteEntry->handle($request->user(), $ledgerEntry);

        return to_route('ledger-entries.index')->with('success', 'Lançamento excluído. Você pode restaurá-lo por 30 dias.');
    }
}
