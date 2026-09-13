<?php

namespace App\Actions;

use App\Enums\AuditAction;
use App\Enums\CardInstallmentStatus;
use App\Enums\LedgerEntryType;
use App\Enums\RecordStatus;
use App\Models\Account;
use App\Models\CardCharge;
use App\Models\CardChargePaymentAllocation;
use App\Models\CardInstallment;
use App\Models\CardPayment;
use App\Models\CardPaymentAllocation;
use App\Models\CreditCard;
use App\Models\LedgerEntry;
use App\Models\User;
use App\Support\AuditRecorder;
use App\Support\CardPaymentAllocator;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class PayCreditCard
{
    public function __construct(
        private CardPaymentAllocator $allocator,
        private AuditRecorder $auditRecorder,
        private RefreshCurrentInternalAlert $refreshAlert,
    ) {}

    /** @param array{credit_card_id:int,source_account_id:int,amount:string,paid_on:string,operation_id:string,card_charge_ids?:list<int>} $data */
    public function handle(User $user, array $data): CardPayment
    {
        $amount = $this->money($data['amount']);
        $paidOn = CarbonImmutable::createFromFormat('!Y-m-d', $data['paid_on'], 'America/Sao_Paulo');
        if ($paidOn === false || $paidOn->format('Y-m-d') !== $data['paid_on'] || $paidOn->isAfter(today('America/Sao_Paulo'))) {
            throw ValidationException::withMessages(['paid_on' => 'Informe uma data válida que não esteja no futuro.']);
        }
        if (! Str::isUuid($data['operation_id'])) {
            throw ValidationException::withMessages(['operation_id' => 'Informe uma chave de operação válida.']);
        }
        $operationId = strtolower($data['operation_id']);
        $rawChargeIds = $data['card_charge_ids'] ?? [];
        if (! is_array($rawChargeIds) || count($rawChargeIds) > 200
            || collect($rawChargeIds)->contains(fn ($id): bool => filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false)
            || count($rawChargeIds) !== count(array_unique($rawChargeIds, SORT_REGULAR))) {
            throw ValidationException::withMessages(['card_charge_ids' => 'Selecione encargos válidos, sem repetição.']);
        }
        $selectedChargeIds = collect($rawChargeIds)->map(fn ($id): int => (int) $id)->sort()->values()->all();

        try {
            return DB::transaction(function () use ($user, $data, $amount, $paidOn, $operationId, $selectedChargeIds): CardPayment {
                User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
                $existing = CardPayment::query()->whereBelongsTo($user)->where('operation_id', $operationId)->with(['allocations', 'chargeAllocations'])->first();
                if ($existing) {
                    return $this->validateReplay($existing, $data, $amount, $paidOn, $selectedChargeIds);
                }
                $card = CreditCard::query()->whereBelongsTo($user)->where('status', RecordStatus::Active)->whereKey($data['credit_card_id'])->lockForUpdate()->first();
                $account = Account::query()->whereBelongsTo($user)->where('status', RecordStatus::Active)->whereKey($data['source_account_id'])->lockForUpdate()->first();
                if (! $card) {
                    throw ValidationException::withMessages(['credit_card_id' => 'Escolha um cartão disponível.']);
                }
                if (! $account) {
                    throw ValidationException::withMessages(['source_account_id' => 'Escolha uma conta disponível.']);
                }
                if ($this->accountBalance($account)->isLessThan($amount)) {
                    throw ValidationException::withMessages(['amount' => 'A conta escolhida não possui saldo suficiente.']);
                }
                $charges = CardCharge::query()->whereBelongsTo($user)
                    ->where('credit_card_id', $card->id)
                    ->where('status', CardInstallmentStatus::Pending)
                    ->whereDate('due_on', '<=', $paidOn->endOfMonth()->toDateString())
                    ->whereIn('id', $selectedChargeIds)
                    ->orderBy('due_on')->orderBy('id')->lockForUpdate()->get();
                if ($charges->count() !== count($selectedChargeIds)) {
                    throw ValidationException::withMessages(['card_charge_ids' => 'Selecione apenas encargos pendentes e disponíveis neste fechamento.']);
                }
                if ($charges->contains(fn (CardCharge $charge): bool => $charge->charged_on->isAfter($paidOn))) {
                    throw ValidationException::withMessages(['card_charge_ids' => 'Um encargo não pode ser pago antes da data em que foi cobrado.']);
                }
                $installments = CardInstallment::query()->whereBelongsTo($user)
                    ->where('status', CardInstallmentStatus::Pending)
                    ->whereDate('due_on', '<=', $paidOn->endOfMonth()->toDateString())
                    ->whereHas('purchase', fn ($query) => $query->where('credit_card_id', $card->id))
                    ->orderBy('due_on')->orderBy('id')->lockForUpdate()->get();
                $chargeDebt = $charges->reduce(
                    fn (BigDecimal $total, CardCharge $charge): BigDecimal => $total->plus(BigDecimal::of($charge->amount)->minus($charge->paid_amount)),
                    BigDecimal::zero(),
                );
                $chargePayment = $amount->isGreaterThan($chargeDebt) ? $chargeDebt : $amount;
                $chargeAllocations = $chargePayment->isPositive()
                    ? $this->allocator->allocate((string) $chargePayment, $charges->map(fn (CardCharge $charge): array => [
                        'id' => $charge->id, 'due_on' => $charge->due_on->toDateString(),
                        'remaining' => (string) BigDecimal::of($charge->amount)->minus($charge->paid_amount),
                    ])->all())
                    : [];
                $installmentPayment = $amount->minus($chargePayment);
                try {
                    $allocations = $installmentPayment->isPositive() ? $this->allocator->allocate((string) $installmentPayment, $installments->map(fn (CardInstallment $installment): array => [
                        'id' => $installment->id,
                        'due_on' => $installment->due_on->toDateString(),
                        'remaining' => (string) BigDecimal::of($installment->gross_amount)->minus($installment->paid_amount),
                    ])->all()) : [];
                } catch (InvalidArgumentException $exception) {
                    throw ValidationException::withMessages(['amount' => $exception->getMessage() === 'Payment must not exceed the selected debt.'
                        ? 'O pagamento não pode ultrapassar a dívida pendente do cartão.'
                        : 'Não há dívida válida para este pagamento.']);
                }
                $ledgerEntry = $account->ledgerEntries()->create([
                    'user_id' => $user->id,
                    'type' => LedgerEntryType::CardPayment,
                    'amount' => (string) $amount,
                    'operation_id' => $operationId,
                    'occurred_at' => $paidOn->toDateString(),
                    'description' => 'Pagamento do cartão '.$card->name,
                ]);
                $payment = CardPayment::query()->create([
                    'user_id' => $user->id,
                    'credit_card_id' => $card->id,
                    'source_account_id' => $account->id,
                    'ledger_entry_id' => $ledgerEntry->id,
                    'amount' => (string) $amount,
                    'paid_on' => $paidOn->toDateString(),
                    'selected_charge_ids' => $selectedChargeIds,
                    'operation_id' => $operationId,
                ]);
                $this->auditRecorder->record($user, AuditAction::Created, $ledgerEntry);
                $this->auditRecorder->record($user, AuditAction::Created, $payment);
                foreach ($chargeAllocations as $allocationData) {
                    $charge = $charges->firstWhere('id', $allocationData['installment_id']);
                    $allocation = CardChargePaymentAllocation::query()->create([
                        'user_id' => $user->id, 'card_payment_id' => $payment->id,
                        'card_charge_id' => $charge->id, 'amount' => $allocationData['amount'],
                    ]);
                    $before = $charge->attributesToArray();
                    $charge->paid_amount = (string) BigDecimal::of($charge->paid_amount)->plus($allocation->amount);
                    $charge->status = BigDecimal::of($charge->paid_amount)->isEqualTo($charge->amount)
                        ? CardInstallmentStatus::Paid : CardInstallmentStatus::Pending;
                    $charge->save();
                    $this->auditRecorder->record($user, AuditAction::Created, $allocation);
                    $this->auditRecorder->record($user, AuditAction::Updated, $charge, $before);
                }
                foreach ($allocations as $allocationData) {
                    $installment = $installments->firstWhere('id', $allocationData['installment_id']);
                    $allocation = CardPaymentAllocation::query()->create([
                        'user_id' => $user->id,
                        'card_payment_id' => $payment->id,
                        'card_installment_id' => $installment->id,
                        'amount' => $allocationData['amount'],
                    ]);
                    $before = $installment->attributesToArray();
                    $installment->paid_amount = (string) BigDecimal::of($installment->paid_amount)->plus($allocation->amount);
                    $installment->status = BigDecimal::of($installment->paid_amount)->isEqualTo($installment->gross_amount)
                        ? CardInstallmentStatus::Paid : CardInstallmentStatus::Pending;
                    $installment->save();
                    $this->auditRecorder->record($user, AuditAction::Created, $allocation);
                    $this->auditRecorder->record($user, AuditAction::Updated, $installment, $before);
                }

                $this->refreshAlert->handle($user);

                return $payment->load(['allocations', 'chargeAllocations']);
            }, 3);
        } catch (UniqueConstraintViolationException) {
            $existing = CardPayment::query()->whereBelongsTo($user)->where('operation_id', $operationId)->with(['allocations', 'chargeAllocations'])->first();
            if ($existing) {
                return $this->validateReplay($existing, $data, $amount, $paidOn, $selectedChargeIds);
            }

            throw ValidationException::withMessages(['operation_id' => 'Não foi possível repetir o pagamento com segurança.']);
        }
    }

    private function accountBalance(Account $account): BigDecimal
    {
        $positive = [LedgerEntryType::OpeningBalance->value, LedgerEntryType::Income->value, LedgerEntryType::Refund->value, LedgerEntryType::TransferIn->value];
        $placeholders = implode(', ', array_fill(0, count($positive), '?'));
        $balance = LedgerEntry::query()->where('user_id', $account->user_id)
            ->where('reference_type', $account->getMorphClass())->where('reference_id', $account->id)
            ->selectRaw("COALESCE(SUM(CASE WHEN type IN ({$placeholders}) THEN amount ELSE -amount END), 0) AS balance", $positive)->value('balance');

        return BigDecimal::of((string) $balance)->toScale(2, RoundingMode::Unnecessary);
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

    /** @param list<int> $selectedChargeIds */
    private function validateReplay(CardPayment $payment, array $data, BigDecimal $amount, CarbonImmutable $paidOn, array $selectedChargeIds): CardPayment
    {
        if ($payment->credit_card_id !== (int) $data['credit_card_id'] || $payment->source_account_id !== (int) $data['source_account_id']
            || $payment->amount !== (string) $amount || $payment->paid_on->toDateString() !== $paidOn->toDateString()
            || ($payment->selected_charge_ids ?? []) !== $selectedChargeIds) {
            throw ValidationException::withMessages(['operation_id' => 'Esta chave já foi usada com dados diferentes.']);
        }

        return $payment;
    }
}
