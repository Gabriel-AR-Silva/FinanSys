<?php

namespace App\Http\Controllers;

use App\Actions\RestoreManualLedgerEntry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RestoredLedgerEntryController extends Controller
{
    public function store(Request $request, int $ledgerEntry, RestoreManualLedgerEntry $restoreEntry): RedirectResponse
    {
        $restoreEntry->handle($request->user(), $ledgerEntry);

        return to_route('ledger-entries.index')->with('success', 'Lançamento restaurado com sucesso.');
    }
}
