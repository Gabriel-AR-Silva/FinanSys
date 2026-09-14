<?php

namespace App\Http\Controllers;

use App\Actions\ConfirmOfxImportItems;
use App\Models\BankStatementImport;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class OfxImportConfirmationController extends Controller
{
    public function store(Request $request, BankStatementImport $import, ConfirmOfxImportItems $confirm): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ((int) $import->user_id !== (int) $user->getKey()) {
            abort(404);
        }

        $validated = $request->validate([
            'item_ids' => ['required', 'array', 'min:1'],
            'item_ids.*' => ['required', 'integer', 'distinct'],
        ]);

        $confirm->handle($user, $import, $validated['item_ids']);

        return back()->with('success', 'Itens confirmados e lançados no financeiro.');
    }
}
