<?php

namespace App\Http\Controllers;

use App\Actions\ReverseLedgerOperation;
use App\Http\Requests\StoreLedgerEntryReversalRequest;
use Illuminate\Http\RedirectResponse;

class LedgerEntryReversalController extends Controller
{
    public function __invoke(StoreLedgerEntryReversalRequest $request, int $ledgerEntry, ReverseLedgerOperation $reverseOperation): RedirectResponse
    {
        $reverseOperation->handle($request->user(), $ledgerEntry, $request->validated('operation_id'));

        return to_route('ledger-entries.index')->with('success', 'Lançamento estornado com sucesso.');
    }
}
