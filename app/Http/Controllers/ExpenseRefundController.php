<?php

namespace App\Http\Controllers;

use App\Actions\CreateExpenseRefund;
use App\Http\Requests\StoreExpenseRefundRequest;
use Illuminate\Http\RedirectResponse;

class ExpenseRefundController extends Controller
{
    public function store(StoreExpenseRefundRequest $request, CreateExpenseRefund $createRefund): RedirectResponse
    {
        $createRefund->handle($request->user(), $request->validated());

        return to_route('ledger-entries.index')->with('success', '💸 Reembolso registrado sem virar renda nova.');
    }
}
