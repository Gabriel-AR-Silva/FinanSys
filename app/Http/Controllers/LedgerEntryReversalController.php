<?php

namespace App\Http\Controllers;

use App\Actions\ReverseLedgerOperation;
use App\Http\Requests\StoreLedgerEntryReversalRequest;
use App\Models\ExpenseCommitmentPayment;
use App\Models\LedgerEntry;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LedgerEntryReversalController extends Controller
{
    public function __invoke(StoreLedgerEntryReversalRequest $request, int $ledgerEntry, ReverseLedgerOperation $reverseOperation): RedirectResponse
    {
        DB::transaction(function () use ($request, $ledgerEntry, $reverseOperation): void {
            User::query()->whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $entry = LedgerEntry::query()->whereBelongsTo($request->user())->findOrFail($ledgerEntry);
            if (ExpenseCommitmentPayment::query()->where('user_id', $request->user()->id)->where('ledger_entry_id', $entry->id)->exists()) {
                throw ValidationException::withMessages(['ledger_entry' => 'Este pagamento pertence a um compromisso. Corrija o compromisso pelo fluxo específico.']);
            }
            $reverseOperation->handle($request->user(), $ledgerEntry, $request->validated('operation_id'));
        }, 3);

        return to_route('ledger-entries.index')->with('success', 'Lançamento estornado com sucesso.');
    }
}
