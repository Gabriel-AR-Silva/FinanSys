<?php

namespace App\Actions;

use App\Enums\AuditAction;
use App\Models\ExpenseCommitment;
use App\Models\User;
use App\Support\AuditRecorder;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReviseExpenseCommitment
{
    public function __construct(private AuditRecorder $auditRecorder) {}

    /** @param array{description:string,amount:string,due_on:string,version:int} $data */
    public function handle(User $user, int $commitmentId, array $data): ExpenseCommitment
    {
        return DB::transaction(function () use ($user, $commitmentId, $data): ExpenseCommitment {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $commitment = ExpenseCommitment::query()->whereBelongsTo($user)->lockForUpdate()->findOrFail($commitmentId);
            if ($commitment->status !== 'pending') {
                throw ValidationException::withMessages(['expense_commitment' => 'Somente compromissos pendentes podem ser corrigidos.']);
            }
            if ($commitment->version !== (int) $data['version']) {
                throw ValidationException::withMessages(['version' => 'O compromisso foi alterado. Atualize a página antes de salvar.']);
            }
            $amount = (string) BigDecimal::of($data['amount'])->toScale(2, RoundingMode::Unnecessary);
            if (BigDecimal::of($amount)->isLessThan($commitment->paid_amount)) {
                throw ValidationException::withMessages(['amount' => 'O valor previsto não pode ser inferior ao que já foi pago.']);
            }
            $before = $commitment->attributesToArray();
            $commitment->update([
                'description' => $data['description'],
                'amount' => $amount,
                'due_on' => $data['due_on'],
                'status' => BigDecimal::of($amount)->isEqualTo($commitment->paid_amount) ? 'paid' : 'pending',
                'version' => $commitment->version + 1,
            ]);
            $this->auditRecorder->record($user, AuditAction::Updated, $commitment, $before);

            return $commitment;
        }, 3);
    }
}
