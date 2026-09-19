<?php

namespace App\Actions;

use App\Enums\AuditAction;
use App\Enums\LedgerEntryType;
use App\Enums\RecordStatus;
use App\Models\Account;
use App\Models\ExpenseCommitmentPayment;
use App\Models\ExpenseRefund;
use App\Models\LedgerEntry;
use App\Models\User;
use App\Support\AuditRecorder;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateExpenseRefund
{
    public function __construct(
        private AuditRecorder $auditRecorder,
        private RefreshCurrentInternalAlert $refreshAlert,
    ) {}

    /** @param array{expense_ledger_entry_id:int,destination_account_id:int,amount:string,occurred_at:string,operation_id:string} $data */
    public function handle(User $user, array $data): ExpenseRefund
    {
        $amount = (string) BigDecimal::of($data['amount'])->toScale(2, RoundingMode::Unnecessary);
        $operationId = strtolower($data['operation_id']);

        try {
            return DB::transaction(function () use ($user, $data, $amount, $operationId): ExpenseRefund {
                User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
                $existing = ExpenseRefund::query()->whereBelongsTo($user)->where('operation_id', $operationId)->with('refundEntry')->first();
                if ($existing) {
                    return $this->validateReplay($existing, $data, $amount);
                }

                $expense = LedgerEntry::query()->whereBelongsTo($user)->whereKey($data['expense_ledger_entry_id'])->lockForUpdate()->firstOrFail();
                $expenseWasReversed = LedgerEntry::query()->whereBelongsTo($user)->where('reversal_of_operation_id', $expense->operation_id)->exists();
                if ($expense->type !== LedgerEntryType::Expense || $expense->reversal_of_operation_id !== null || $expenseWasReversed) {
                    throw ValidationException::withMessages(['expense_ledger_entry_id' => 'Escolha uma despesa disponível para reembolso.']);
                }
                if (ExpenseCommitmentPayment::query()->where('user_id', $user->id)->where('ledger_entry_id', $expense->id)->exists()) {
                    throw ValidationException::withMessages(['expense_ledger_entry_id' => 'Este pagamento pertence a um compromisso. Use o fluxo próprio de correção.']);
                }

                $account = Account::query()->whereBelongsTo($user)->where('status', RecordStatus::Active)->whereKey($data['destination_account_id'])->lockForUpdate()->first();
                if (! $account) {
                    throw ValidationException::withMessages(['destination_account_id' => 'Escolha uma conta disponível.']);
                }

                $refunded = $this->activeRefundedAmount($user, $expense);
                if ($refunded->plus($amount)->isGreaterThan($expense->amount)) {
                    throw ValidationException::withMessages(['amount' => 'O reembolso ultrapassa o valor ainda disponível desta despesa.']);
                }

                $refundEntry = $account->ledgerEntries()->create([
                    'user_id' => $user->id,
                    'category_id' => $expense->category_id,
                    'type' => LedgerEntryType::Refund,
                    'amount' => $amount,
                    'operation_id' => $operationId,
                    'occurred_at' => $data['occurred_at'],
                    'description' => $expense->description ? 'Reembolso: '.$expense->description : 'Reembolso de despesa',
                ]);
                $refund = ExpenseRefund::query()->create([
                    'user_id' => $user->id,
                    'expense_ledger_entry_id' => $expense->id,
                    'refund_ledger_entry_id' => $refundEntry->id,
                    'operation_id' => $operationId,
                ]);
                $this->auditRecorder->record($user, AuditAction::Created, $refundEntry);
                $this->auditRecorder->record($user, AuditAction::Linked, $refund);
                $this->refreshAlert->handle($user);

                return $refund;
            }, 3);
        } catch (UniqueConstraintViolationException) {
            $existing = ExpenseRefund::query()->whereBelongsTo($user)->where('operation_id', $operationId)->with('refundEntry')->first();
            if ($existing) {
                return $this->validateReplay($existing, $data, $amount);
            }

            throw ValidationException::withMessages(['operation_id' => 'Não foi possível repetir este reembolso com segurança.']);
        }
    }

    private function activeRefundedAmount(User $user, LedgerEntry $expense): BigDecimal
    {
        $entries = ExpenseRefund::query()->whereBelongsTo($user)->whereBelongsTo($expense, 'expenseEntry')
            ->with('refundEntry')->get()->pluck('refundEntry')->filter();
        $reversed = LedgerEntry::query()->whereBelongsTo($user)
            ->whereIn('reversal_of_operation_id', $entries->pluck('operation_id'))
            ->pluck('reversal_of_operation_id')->all();

        return $entries->reject(fn (LedgerEntry $entry): bool => in_array($entry->operation_id, $reversed, true))
            ->reduce(fn (BigDecimal $total, LedgerEntry $entry): BigDecimal => $total->plus($entry->amount), BigDecimal::zero());
    }

    /** @param array{expense_ledger_entry_id:int,destination_account_id:int,amount:string,occurred_at:string,operation_id:string} $data */
    private function validateReplay(ExpenseRefund $refund, array $data, string $amount): ExpenseRefund
    {
        $entry = $refund->refundEntry;
        if ($refund->expense_ledger_entry_id !== (int) $data['expense_ledger_entry_id']
            || ! $entry
            || $entry->reference_id !== (int) $data['destination_account_id']
            || $entry->amount !== $amount
            || $entry->occurred_at->toDateString() !== $data['occurred_at']) {
            throw ValidationException::withMessages(['operation_id' => 'Esta chave de operação já foi usada com dados diferentes.']);
        }

        return $refund;
    }
}
