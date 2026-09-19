<?php

namespace App\Actions;

use App\Enums\ExpensePlanningType;
use App\Enums\LedgerEntryType;
use App\Enums\RecordStatus;
use App\Models\Account;
use App\Models\Category;
use App\Models\ExpenseCommitment;
use App\Models\ExpenseCommitmentPayment;
use App\Models\LedgerEntry;
use App\Models\User;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ManageExpenseCommitment
{
    public function __construct(private CreateManualLedgerEntry $createLedgerEntry) {}

    /** @param array{account_id:int, category_id:int, description:string, amount:string, due_on:string, planning_type:string, operation_id:string} $data */
    public function schedule(User $user, array $data): ExpenseCommitment
    {
        return DB::transaction(function () use ($user, $data): ExpenseCommitment {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $amount = (string) BigDecimal::of($data['amount'])->toScale(2, RoundingMode::Unnecessary);
            $existing = ExpenseCommitment::query()->whereBelongsTo($user)->where('operation_id', $data['operation_id'])->first();
            if ($existing !== null) {
                if ($existing->account_id !== (int) $data['account_id'] || $existing->category_id !== (int) $data['category_id']
                    || $existing->description !== $data['description'] || $existing->amount !== $amount
                    || $existing->due_on->toDateString() !== $data['due_on'] || $existing->planning_type !== $data['planning_type']) {
                    throw ValidationException::withMessages(['operation_id' => 'Esta chave já foi usada com dados diferentes.']);
                }

                return $existing;
            }
            $account = Account::query()->whereBelongsTo($user)->where('status', RecordStatus::Active)->find($data['account_id']);
            $category = Category::query()->whereBelongsTo($user)->where('type', 'expense')->where('status', RecordStatus::Active)->find($data['category_id']);
            if ($account === null || $category === null) {
                throw ValidationException::withMessages(['account_id' => 'Selecione conta e categoria de despesa ativas que pertençam a você.']);
            }

            return ExpenseCommitment::query()->create([
                'user_id' => $user->id,
                'account_id' => $account->id,
                'category_id' => $category->id,
                'description' => $data['description'],
                'amount' => $amount,
                'paid_amount' => '0.00',
                'due_on' => $data['due_on'],
                'planning_type' => $data['planning_type'],
                'status' => 'pending',
                'operation_id' => $data['operation_id'],
            ]);
        }, 3);
    }

    /** @param array{amount:string, paid_on:string, operation_id:string} $data */
    public function pay(User $user, int $commitmentId, array $data): ExpenseCommitmentPayment
    {
        return DB::transaction(function () use ($user, $commitmentId, $data): ExpenseCommitmentPayment {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $amount = (string) BigDecimal::of($data['amount'])->toScale(2, RoundingMode::Unnecessary);
            $existing = ExpenseCommitmentPayment::query()->where('user_id', $user->id)->where('operation_id', $data['operation_id'])->first();
            if ($existing !== null) {
                if ($existing->expense_commitment_id !== $commitmentId || $existing->amount !== $amount || $existing->paid_on->toDateString() !== $data['paid_on']) {
                    throw ValidationException::withMessages(['operation_id' => 'Esta chave já foi usada com dados diferentes.']);
                }

                return $existing;
            }
            $commitment = ExpenseCommitment::query()->whereBelongsTo($user)->lockForUpdate()->findOrFail($commitmentId);
            if ($commitment->status !== 'pending') {
                throw ValidationException::withMessages(['expense_commitment' => 'Este compromisso está encerrado.']);
            }
            if (LedgerEntry::withTrashed()->whereBelongsTo($user)->where('operation_id', $data['operation_id'])->exists()) {
                throw ValidationException::withMessages(['operation_id' => 'Esta chave já foi usada em outro lançamento.']);
            }
            $remaining = BigDecimal::of($commitment->amount)->minus($commitment->paid_amount);
            if (BigDecimal::of($amount)->isGreaterThan($remaining)) {
                throw ValidationException::withMessages(['amount' => 'O pagamento excede o valor pendente.']);
            }
            $entry = $this->createLedgerEntry->handle(
                $user, $commitment->account_id, $commitment->category_id, LedgerEntryType::Expense,
                $amount, $data['paid_on'], $commitment->description, $data['operation_id'],
                ExpensePlanningType::from($commitment->planning_type),
            );
            $payment = ExpenseCommitmentPayment::query()->create([
                'user_id' => $user->id,
                'expense_commitment_id' => $commitment->id,
                'ledger_entry_id' => $entry->id,
                'amount' => $amount,
                'paid_on' => $data['paid_on'],
                'operation_id' => $data['operation_id'],
            ]);
            $paid = BigDecimal::of($commitment->paid_amount)->plus($amount);
            $commitment->update([
                'paid_amount' => (string) $paid,
                'status' => $paid->isEqualTo($commitment->amount) ? 'paid' : 'pending',
                'version' => $commitment->version + 1,
            ]);

            return $payment;
        }, 3);
    }

    public function cancel(User $user, int $commitmentId): ExpenseCommitment
    {
        return DB::transaction(function () use ($user, $commitmentId): ExpenseCommitment {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $commitment = ExpenseCommitment::query()->whereBelongsTo($user)->lockForUpdate()->findOrFail($commitmentId);
            if ($commitment->status === 'paid') {
                throw ValidationException::withMessages(['expense_commitment' => 'Um compromisso quitado não pode ser cancelado.']);
            }
            if ($commitment->status === 'pending') {
                $commitment->update(['status' => 'cancelled', 'version' => $commitment->version + 1]);
            }

            return $commitment;
        }, 3);
    }
}
