<?php

namespace App\Http\Controllers;

use App\Actions\ConfirmOfxCardCreditPix;
use App\Models\BankStatementImport;
use App\Models\OfxImportItem;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OfxCardCreditPixConfirmationController extends Controller
{
    public function store(Request $request, BankStatementImport $import, OfxImportItem $item, ConfirmOfxCardCreditPix $confirm): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ((int) $import->user_id !== (int) $user->getKey()
            || (int) $item->user_id !== (int) $user->getKey()
            || (int) $item->bank_statement_import_id !== (int) $import->getKey()) {
            abort(404);
        }

        $validated = $request->validate([
            'credit_card_id' => ['required', 'integer'],
            'category_id' => ['required', 'integer'],
            'planning_type' => ['required', Rule::in(['ordinary', 'extraordinary'])],
            'installments_count' => ['required', 'integer', 'min:1', 'max:120'],
            'first_due_on' => ['required', 'date_format:Y-m-d'],
        ]);

        $confirm->handle($user, $import, $item, $validated);

        return back()->with('success', 'Pix no Crédito confirmado como compra no cartão. O crédito bancário não foi tratado como receita.');
    }
}
