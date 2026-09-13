<?php

namespace App\Actions;

use App\Enums\AuditAction;
use App\Enums\CardInstallmentStatus;
use App\Models\CardCharge;
use App\Models\CardCredit;
use App\Models\CardCreditAllocation;
use App\Models\CardInstallment;
use App\Models\User;
use App\Support\AuditRecorder;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ApplyCardCredit
{
    public function __construct(
        private AuditRecorder $auditRecorder,
        private RefreshCurrentInternalAlert $refreshAlert,
    ) {}

    /** @param array{card_credit_id:int,target_type:string,target_id:int,amount:string,applied_on:string,operation_id:string} $data */
    public function handle(User $user, array $data): CardCreditAllocation
    {
        $amount = $this->money($data['amount']);
        $appliedOn = CarbonImmutable::createFromFormat('!Y-m-d', $data['applied_on'], 'America/Sao_Paulo');
        if ($appliedOn === false || $appliedOn->format('Y-m-d') !== $data['applied_on'] || $appliedOn->isAfter(today('America/Sao_Paulo'))) {
            throw ValidationException::withMessages(['applied_on' => 'Informe uma data válida que não esteja no futuro.']);
        }
        if (! in_array($data['target_type'], ['installment', 'charge'], true)) {
            throw ValidationException::withMessages(['target_type' => 'Escolha uma obrigação válida para aplicar o crédito.']);
        }
        if (! Str::isUuid($data['operation_id'])) {
            throw ValidationException::withMessages(['operation_id' => 'Informe uma chave de operação válida.']);
        }
        $operationId = strtolower($data['operation_id']);

        try {
            return DB::transaction(function () use ($user, $data, $amount, $appliedOn, $operationId): CardCreditAllocation {
                User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

                $existing = CardCreditAllocation::query()->whereBelongsTo($user)->where('operation_id', $operationId)->first();
                if ($existing) {
                    return $this->validateReplay($existing, $data, $amount, $appliedOn);
                }

                $credit = CardCredit::query()->whereBelongsTo($user)->whereKey($data['card_credit_id'])->lockForUpdate()->first();
                if (! $credit) {
                    throw ValidationException::withMessages(['card_credit_id' => 'Escolha um crédito disponível.']);
                }
                if ($appliedOn->isBefore($credit->credited_on)) {
                    throw ValidationException::withMessages(['applied_on' => 'O crédito não pode ser aplicado antes de ser reconhecido.']);
                }
                $available = BigDecimal::of($credit->amount)->minus($credit->applied_amount);
                if ($amount->isGreaterThan($available)) {
                    throw ValidationException::withMessages(['amount' => 'O valor ultrapassa o saldo disponível deste crédito.']);
                }

                $installment = null;
                $charge = null;
                if ($data['target_type'] === 'installment') {
                    $installment = CardInstallment::query()
                        ->whereBelongsTo($user)
                        ->whereKey($data['target_id'])
                        ->where('status', CardInstallmentStatus::Pending)
                        ->whereHas('purchase', fn ($query) => $query->where('credit_card_id', $credit->credit_card_id))
                        ->with('purchase')
                        ->lockForUpdate()
                        ->first();
                    if (! $installment) {
                        throw ValidationException::withMessages(['target_id' => 'Escolha uma parcela pendente do mesmo cartão.']);
                    }
                    if ($appliedOn->isBefore($installment->purchase->purchased_on)) {
                        throw ValidationException::withMessages(['applied_on' => 'O crédito não pode ser aplicado antes da compra de destino.']);
                    }
                    $remaining = BigDecimal::of($installment->gross_amount)->minus($installment->paid_amount);
                } else {
                    $charge = CardCharge::query()
                        ->whereBelongsTo($user)
                        ->where('credit_card_id', $credit->credit_card_id)
                        ->whereKey($data['target_id'])
                        ->where('status', CardInstallmentStatus::Pending)
                        ->lockForUpdate()
                        ->first();
                    if (! $charge) {
                        throw ValidationException::withMessages(['target_id' => 'Escolha um encargo pendente do mesmo cartão.']);
                    }
                    if ($appliedOn->isBefore($charge->charged_on)) {
                        throw ValidationException::withMessages(['applied_on' => 'O crédito não pode ser aplicado antes do encargo de destino.']);
                    }
                    $remaining = BigDecimal::of($charge->amount)->minus($charge->paid_amount);
                }

                if ($amount->isGreaterThan($remaining)) {
                    throw ValidationException::withMessages(['amount' => 'O crédito não pode ultrapassar o saldo da obrigação selecionada.']);
                }

                $allocation = CardCreditAllocation::query()->create([
                    'user_id' => $user->id,
                    'card_credit_id' => $credit->id,
                    'card_installment_id' => $installment?->id,
                    'card_charge_id' => $charge?->id,
                    'amount' => (string) $amount,
                    'applied_on' => $appliedOn->toDateString(),
                    'operation_id' => $operationId,
                ]);
                $this->auditRecorder->record($user, AuditAction::Created, $allocation);

                $creditBefore = $credit->attributesToArray();
                $credit->applied_amount = (string) BigDecimal::of($credit->applied_amount)->plus($amount);
                $credit->save();
                $this->auditRecorder->record($user, AuditAction::Updated, $credit, $creditBefore);

                if ($installment) {
                    $before = $installment->attributesToArray();
                    $installment->paid_amount = (string) BigDecimal::of($installment->paid_amount)->plus($amount);
                    $installment->status = BigDecimal::of($installment->paid_amount)->isEqualTo($installment->gross_amount)
                        ? CardInstallmentStatus::Paid
                        : CardInstallmentStatus::Pending;
                    $installment->save();
                    $this->auditRecorder->record($user, AuditAction::Updated, $installment, $before);
                }

                if ($charge) {
                    $before = $charge->attributesToArray();
                    $charge->paid_amount = (string) BigDecimal::of($charge->paid_amount)->plus($amount);
                    $charge->status = BigDecimal::of($charge->paid_amount)->isEqualTo($charge->amount)
                        ? CardInstallmentStatus::Paid
                        : CardInstallmentStatus::Pending;
                    $charge->save();
                    $this->auditRecorder->record($user, AuditAction::Updated, $charge, $before);
                }

                $this->refreshAlert->handle($user);

                return $allocation;
            }, 3);
        } catch (UniqueConstraintViolationException) {
            $existing = CardCreditAllocation::query()->whereBelongsTo($user)->where('operation_id', $operationId)->first();
            if ($existing) {
                return $this->validateReplay($existing, $data, $amount, $appliedOn);
            }

            throw ValidationException::withMessages(['operation_id' => 'Não foi possível repetir a aplicação do crédito com segurança.']);
        }
    }

    private function money(string $value): BigDecimal
    {
        if (! preg_match('/\A\d{1,17}(?:\.\d{1,2})?\z/', $value)) {
            throw ValidationException::withMessages(['amount' => 'Informe um valor positivo com até duas casas decimais.']);
        }
        $amount = BigDecimal::of($value)->toScale(2, RoundingMode::Unnecessary);
        if (! $amount->isPositive()) {
            throw ValidationException::withMessages(['amount' => 'O valor deve ser maior que zero.']);
        }

        return $amount;
    }

    private function validateReplay(CardCreditAllocation $allocation, array $data, BigDecimal $amount, CarbonImmutable $appliedOn): CardCreditAllocation
    {
        $targetMatches = $data['target_type'] === 'installment'
            ? $allocation->card_installment_id === (int) $data['target_id'] && $allocation->card_charge_id === null
            : $allocation->card_charge_id === (int) $data['target_id'] && $allocation->card_installment_id === null;

        if ($allocation->card_credit_id !== (int) $data['card_credit_id']
            || ! $targetMatches
            || $allocation->amount !== (string) $amount
            || $allocation->applied_on->toDateString() !== $appliedOn->toDateString()) {
            throw ValidationException::withMessages(['operation_id' => 'Esta chave já foi usada com dados diferentes.']);
        }

        return $allocation;
    }
}
