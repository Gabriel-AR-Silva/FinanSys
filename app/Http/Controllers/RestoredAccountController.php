<?php

namespace App\Http\Controllers;

use App\Actions\RestoreAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RestoredAccountController extends Controller
{
    public function store(Request $request, int $account, RestoreAccount $restoreAccount): RedirectResponse
    {
        $restoreAccount->handle($request->user(), $account);

        return to_route('accounts.index')->with('success', 'Conta restaurada com sucesso.');
    }
}
