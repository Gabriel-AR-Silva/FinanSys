<?php

namespace App\Actions;

use App\Enums\AuditAction;
use App\Enums\CardInstallmentStatus;
use App\Models\CardAdvanceAllocation;
use App\Models\CardCredit;
use App\Models\CardInstallment;
use App\Models\CardPurchase;
use App\Models\CardPurchaseReversal;
use App\Models\User;
use App\Support\AuditRecorder;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ReverseCardPurchase
{
    public function __construct(
        private AuditRecorder $auditRecorder,
        private RefreshCurrentInternalAlert $refreshAlert,
    ) {}

    /** @param array{card_purchase_id:int,reversed_on:string,operation_id:string,reason?:string|null} $data */
    public function handle(User $user, array $data): CardPurchaseReversal
    {
        $reversedOn = CarbonImmutable::createFromFormat('!Y-m-d', $data['reversed_on'], 'America/Sao_Paulo');
        if ($reversedOn === false || $reversedOn->format('Y-m-d') !== $data['reversed_on'] || $reversedOn->isAfter(today('America/Sao_Paulo'))) {
            throw ValidationException::withMessages(['reversed_on' => 'Informe uma data de estorno válida que não esteja no futuro.']);
        }
        if (! Str::isUuid($data['operation_id'])) {
            throw ValidationException::withMessages(['operation_id' => 'Informe uma chave de operação válida.']);
        }

        $operationId = strtolower($data['operation_id']);
        $reason = isset($data['reason']) ? trim((string) $data['reason']) : null;
        $reason = $reason === '' ? null : $reason;
        if ($reason !== null && mb_strlen($reason) > 255) {
            throw ValidationException::withMessages(['reason' => 'O motivo deve ter no máximo 255 caracteres.']);
        }

        try {
            return DB::transaction(function () use ($user, $data, $reversedOn, $operationId, $reason): CardPurchaseReversal {
                User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

                $existing = CardPurchaseReversal::query()
                    ->whereBelongsTo($user)
                    ->where('operation_id', $operationId)
                    ->with('credit')
                    ->first();
                if ($existing) {
                    return $this->validateReplay($existing, $data, $reversedOn, $reason);
                }

                $purchase = CardPurchase::query()
                    ->whereBelongsTo($user)
                    ->whereKey($data['card_purchase_id'])
                    ->lockForUpdate()
                    ->first();
                if (! $purchase) {
                    throw ValidationException::withMessages(['card_purchase_id' => 'Escolha uma compra disponível.']);
                }
                if ($reversedOn->isBefore($purchase->purchased_on)) {
                    throw ValidationException::withMessages(['reversed_on' => 'O estorno não pode ser anterior à compra.']);
                }
                if (CardPurchaseReversal::query()->whereBelongsTo($user)->where('card_purchase_id', $purchase->id)->exists()) {
                    throw ValidationException::withMessages(['card_purchase_id' => 'Esta compra já foi estornada.']);
                }

                $installments = CardInstallment::query()
                    ->whereBelongsTo($user)
                    ->where('card_purchase_id', $purchase->id)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();
                if ($installments->isEmpty()) {
                    throw ValidationException::withMessages(['card_purchase_id' => 'A compra não possui parcelas elegíveis para estorno.']);
                }
                if ($installments->contains(fn (CardInstallment $installment): bool => $installment->status === CardInstallmentStatus::Reversed)) {
                    throw ValidationException::withMessages(['card_purchase_id' => 'A compra possui parcela já estornada e precisa de revisão antes de repetir a operação.']);
                }

                $advanceByInstallment = CardAdvanceAllocation::query()
                    ->whereBelongsTo($user)
                    ->whereIn('card_installment_id', $installments->pluck('id'))
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('card_installment_id');

                $cancelledPending = BigDecimal::zero();
                $creditedPaid = BigDecimal::zero();

                foreach ($installments as $installment) {
                    $gross = BigDecimal::of($installment->gross_amount)->toScale(2, RoundingMode::Unnecessary);
                    $paid = BigDecimal::of($installment->paid_amount)->toScale(2, RoundingMode::Unnecessary);
                    $advance = $advanceByInstallment->get($installment->id);
                    $advancedGross = $advance ? BigDecimal::of($advance->gross_amount) : BigDecimal::zero();
                    $advancedNet = $advance ? BigDecimal::of($advance->net_amount) : BigDecimal::zero();

                    $eligibleAlreadySettled = $paid->plus($advancedGross);
                    if ($eligibleAlreadySettled->isGreaterThan($gross)) {
                        throw ValidationException::withMessages(['card_purchase_id' => 'A compra possui liquidações incompatíveis e não pode ser estornada automaticamente.']);
                    }

                    $cancelledPending = $cancelledPending->plus($gross->minus($eligibleAlreadySettled));
                    $creditedPaid = $creditedPaid->plus($paid)->plus($advancedNet);
                }

                if ($cancelledPending->plus($creditedPaid)->isGreaterThan(BigDecimal::of($purchase->gross_amount))) {
                    throw ValidationException::withMessages(['card_purchase_id' => 'Os valores elegíveis do estorno ultrapassam o valor da compra.']);
                }

                $reversal = CardPurchaseReversal::query()->create([
                    'user_id' => $user->id,
                    'credit_card_id' => $purchase->credit_card_id,
                    'card_purchase_id' => $purchase->id,
                    'reversed_on' => $reversedOn->toDateString(),
                    'cancelled_pending_amount' => (string) $cancelledPending,
                    'credited_paid_amount' => (string) $creditedPaid,
                    'reason' => $reason,
                    'operation_id' => $operationId,
                ]);
                $this->auditRecorder->record($user, AuditAction::Reversed, $reversal);

                $credit = null;
                if ($creditedPaid->isPositive()) {
                    $credit = CardCredit::query()->create([
                        'user_id' => $user->id,
                        'credit_card_id' => $purchase->credit_card_id,
                        'card_purchase_reversal_id' => $reversal->id,
                        'amount' => (string) $creditedPaid,
                        'applied_amount' => '0.00',
                        'credited_on' => $reversedOn->toDateString(),
                    ]);
                    $this->auditRecorder->record($user, AuditAction::Created, $credit);
                }

                foreach ($installments as $installment) {
                    $before = $installment->attributesToArray();
                    $installment->status = CardInstallmentStatus::Reversed;
                    $installment->save();
                    $this->auditRecorder->record($user, AuditAction::Updated, $installment, $before);
                }

                $purchaseBefore = $purchase->attributesToArray();
                $purchase->delete();
                $this->auditRecorder->record($user, AuditAction::Deleted, $purchase, $purchaseBefore);

                $this->refreshAlert->handle($user);

                return $reversal->setRelation('credit', $credit);
            }, 3);
        } catch (UniqueConstraintViolationException) {
            $existing = CardPurchaseReversal::query()->whereBelongsTo($user)->where('operation_id', $operationId)->with('credit')->first();
            if ($existing) {
                return $this->validateReplay($existing, $data, $reversedOn, $reason);
            }

            throw ValidationException::withMessages(['operation_id' => 'Não foi possível repetir o estorno com segurança.']);
        }
    }

    private function validateReplay(CardPurchaseReversal $reversal, array $data, CarbonImmutable $reversedOn, ?string $reason): CardPurchaseReversal
    {
        if ($reversal->card_purchase_id !== (int) $data['card_purchase_id']
            || $reversal->reversed_on->toDateString() !== $reversedOn->toDateString()
            || $reversal->reason !== $reason) {
            throw ValidationException::withMessages(['operation_id' => 'Esta chave já foi usada com dados diferentes.']);
        }

        return $reversal;
    }
}
