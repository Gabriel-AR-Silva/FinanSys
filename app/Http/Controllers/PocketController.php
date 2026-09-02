<?php

namespace App\Http\Controllers;

use App\Actions\CreatePocket;
use App\Actions\DeletePocket;
use App\Actions\RenamePocket;
use App\Enums\RecordStatus;
use App\Http\Requests\StorePocketRequest;
use App\Http\Requests\UpdatePocketRequest;
use App\Models\Account;
use App\Models\Pocket;
use App\Queries\AccountBalanceQuery;
use App\Queries\PocketBalanceQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PocketController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request, PocketBalanceQuery $balances, AccountBalanceQuery $accountBalances): Response
    {
        $threshold = now()->subDays((int) config('finansys.soft_delete_retention_days'));
        $pockets = $balances->forUser($request->user())->map(fn (Pocket $pocket): array => [
            'id' => $pocket->id, 'name' => $pocket->name, 'balance' => $pocket->balance,
            'account' => ['id' => $pocket->account->id, 'name' => $pocket->account->name],
        ]);
        $accounts = $accountBalances->forUser($request->user())->map(fn (Account $account): array => ['id' => $account->id, 'name' => $account->name, 'available_balance' => $account->balance]);
        $deletedPockets = Pocket::onlyTrashed()->whereBelongsTo($request->user())
            ->where('deleted_at', '>=', $threshold->format('Y-m-d H:i:s.u'))
            ->whereHas('account', fn ($query) => $query->where('status', RecordStatus::Active))
            ->with('account:id,name')->latest('deleted_at')->get()
            ->map(fn (Pocket $pocket): array => ['id' => $pocket->id, 'name' => $pocket->name, 'account_name' => $pocket->account->name, 'restorable_until' => $pocket->deleted_at->copy()->addDays((int) config('finansys.soft_delete_retention_days'))]);

        return Inertia::render('Pockets/Index', compact('pockets', 'accounts', 'deletedPockets'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StorePocketRequest $request, CreatePocket $createPocket): RedirectResponse
    {
        $data = $request->validated();
        $createPocket->handle($request->user(), $data['account_id'], $data['name'], $data['operation_id'] ?? null);

        return to_route('pockets.index')->with('success', 'Caixinha criada com sucesso.');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdatePocketRequest $request, int $pocket, RenamePocket $renamePocket): RedirectResponse
    {
        $renamePocket->handle($request->user(), $pocket, $request->validated('name'));

        return to_route('pockets.index')->with('success', 'Caixinha atualizada com sucesso.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, int $pocket, DeletePocket $deletePocket): RedirectResponse
    {
        $deletePocket->handle($request->user(), $pocket);

        return to_route('pockets.index')->with('success', 'Caixinha excluída. Você pode restaurá-la por 30 dias.');
    }
}
