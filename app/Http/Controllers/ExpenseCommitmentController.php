<?php

namespace App\Http\Controllers;

use App\Actions\ManageExpenseCommitment;
use App\Actions\ReviseExpenseCommitment;
use App\Enums\ExpensePlanningType;
use App\Enums\RecordStatus;
use App\Models\Account;
use App\Models\Category;
use App\Models\ExpenseCommitment;
use App\Queries\AccountBalanceQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ExpenseCommitmentController extends Controller
{
    public function index(Request $request, AccountBalanceQuery $balances): Response
    {
        $user = $request->user();
        $accounts = $balances->forUser($user)->filter(fn (Account $account): bool => $account->status === RecordStatus::Active)
            ->map(fn (Account $account): array => ['id' => $account->id, 'name' => $account->name, 'balance' => $account->balance])->values();

        return Inertia::render('ExpenseCommitments/Index', [
            'accounts' => $accounts,
            'categories' => Category::query()->whereBelongsTo($user)->where('type', 'expense')->where('status', RecordStatus::Active)->orderBy('name')->get(['id', 'name']),
            'commitments' => ExpenseCommitment::query()->whereBelongsTo($user)->with('payments:id,expense_commitment_id,amount,paid_on')
                ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
                ->orderBy('due_on')->orderBy('id')->get(),
        ]);
    }

    public function store(Request $request, ManageExpenseCommitment $manage): RedirectResponse
    {
        $data = $request->validate([
            'account_id' => ['required', 'integer'],
            'category_id' => ['required', 'integer'],
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'decimal:0,2', 'gt:0', 'regex:/^\d{1,17}(?:\.\d{1,2})?$/'],
            'due_on' => ['required', 'date_format:Y-m-d'],
            'planning_type' => ['required', Rule::enum(ExpensePlanningType::class)],
            'operation_id' => ['required', 'uuid'],
        ]);
        $manage->schedule($request->user(), $data);

        return to_route('expense-commitments.index')->with('success', 'Compromisso cadastrado sem alterar o saldo.');
    }

    public function update(Request $request, int $commitment, ReviseExpenseCommitment $revise): RedirectResponse
    {
        $data = $request->validate([
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'decimal:0,2', 'gt:0', 'regex:/^\d{1,17}(?:\.\d{1,2})?$/'],
            'due_on' => ['required', 'date_format:Y-m-d'],
            'version' => ['required', 'integer', 'min:1'],
        ]);
        $revise->handle($request->user(), $commitment, $data);

        return to_route('expense-commitments.index')->with('success', 'Compromisso corrigido sem alterar os pagamentos já feitos.');
    }

    public function pay(Request $request, int $commitment, ManageExpenseCommitment $manage): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'decimal:0,2', 'gt:0', 'regex:/^\d{1,17}(?:\.\d{1,2})?$/'],
            'paid_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'operation_id' => ['required', 'uuid'],
        ]);
        $manage->pay($request->user(), $commitment, $data);

        return to_route('expense-commitments.index')->with('success', 'Pagamento registrado uma vez no saldo.');
    }

    public function cancel(Request $request, int $commitment, ManageExpenseCommitment $manage): RedirectResponse
    {
        $manage->cancel($request->user(), $commitment);

        return to_route('expense-commitments.index')->with('success', 'Compromisso cancelado; pagamentos anteriores preservados.');
    }
}
