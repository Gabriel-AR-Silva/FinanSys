<?php

namespace App\Http\Controllers;

use App\Actions\CreateAccount;
use App\Actions\DeleteAccount;
use App\Actions\RenameAccount;
use App\Http\Requests\StoreAccountRequest;
use App\Http\Requests\UpdateAccountRequest;
use App\Models\Account;
use App\Queries\AccountBalanceQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AccountController extends Controller
{
    public function index(Request $request, AccountBalanceQuery $balances): Response
    {
        $currentTime = now();
        $restorationThreshold = $currentTime->copy()
            ->subDays((int) config('finansys.soft_delete_retention_days'));
        $accounts = $balances->forUser($request->user())->map(fn (Account $account): array => [
            'id' => $account->id,
            'name' => $account->name,
            'available_balance' => $account->balance,
            'pockets_total' => $account->pockets_total,
        ]);
        $deletedAccounts = Account::onlyTrashed()
            ->whereBelongsTo($request->user())
            ->where('deleted_at', '>=', $restorationThreshold->format('Y-m-d H:i:s.u'))
            ->latest('deleted_at')
            ->get(['id', 'name', 'deleted_at'])
            ->map(fn (Account $account): array => [
                'id' => $account->id,
                'name' => $account->name,
                'deleted_at' => $account->deleted_at,
                'restorable_until' => $account->deleted_at->copy()->addDays((int) config('finansys.soft_delete_retention_days')),
            ]);

        return Inertia::render('Accounts/Index', [
            'accounts' => $accounts,
            'deletedAccounts' => $deletedAccounts,
        ]);
    }

    public function store(StoreAccountRequest $request, CreateAccount $createAccount): RedirectResponse
    {
        $data = $request->validated();
        $createAccount->handle(
            $request->user(),
            $data['name'],
            $data['opening_balance'],
            $data['operation_id'] ?? null,
        );

        return to_route('accounts.index')->with('success', 'Conta criada com sucesso.');
    }

    public function update(UpdateAccountRequest $request, int $account, RenameAccount $renameAccount): RedirectResponse
    {
        $renameAccount->handle($request->user(), $account, $request->validated('name'));

        return to_route('accounts.index')->with('success', 'Conta atualizada com sucesso.');
    }

    public function destroy(Request $request, int $account, DeleteAccount $deleteAccount): RedirectResponse
    {
        $deleteAccount->handle($request->user(), $account);

        return to_route('accounts.index')->with('success', 'Conta excluída. Você pode restaurá-la por 30 dias.');
    }
}
