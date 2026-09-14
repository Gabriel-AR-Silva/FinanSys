<?php

namespace App\Actions;

use App\Enums\AuditAction;
use App\Enums\CardInstallmentStatus;
use App\Enums\LedgerEntryType;
use App\Enums\RecordStatus;
use App\Models\Account;
use App\Models\CardAdvance;
use App\Models\CardAdvanceAllocation;
use App\Models\CardInstallment;
use App\Models\CardPaymentAllocation;
use App\Models\CreditCard;
use App\Models\LedgerEntry;
use App\Models\User;
use App\Support\AuditRecorder;
use App\Support\CardAdvanceCalculator;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AdvanceCardInstallments
{
    public function __construct(
        private CardAdvanceCalculator $calculator,
        private AuditRecorder $auditRecorder,
        private RefreshCurrentInternalAlert $refreshAlert,
    ) {}

    /** @param array{credit_card_id:int,source_account_id:int,installment_ids:list<int>,discount_amount:string,expected_gross_amount:string,advanced_on:string,operation_id:string} $data */
    public function handle(User $user, array $data): CardAdvance
    {
        $discount = $this->money($data['discount_amount'], 'discount_amount', true);
        $expectedGross = $this->money($data['expected_gross_amount'], 'expected_gross_amount');
        $advancedOn = CarbonImmutable::createFromFormat('!Y-m-d', $data['advanced_on'], 'America/Sao_Paulo');
        if ($advancedOn === false || $advancedOn->format('Y-m-d') !== $data['advanced_on'] || $advancedOn->isAfter(today('America/Sao_Paulo'))) {
            throw ValidationException::withMessages(['advanced_on' => 'Informe uma data válida que não esteja no futuro.']);
        }
        if (! Str::isUuid($data['operation_id'])) {
            throw ValidationException::withMessages(['operation_id' => 'Informe uma chave de operação válida.']);
        }
        $operationId = strtolower($data['operation_id']);
        $selectedIds = $this->selectedIds($data['installment_ids'] ?? null);

        try {
            return DB::transaction(function () use ($user, $data, $discount, $expectedGross, $advancedOn, $operationId, $selectedIds): CardAdvance {
                User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
                $existing = CardAdvance::query()->whereBelongsTo($user)->where('operation_id', $operationId)->with('allocations')->first();
                if ($existing) {
                    return $this->validateReplay($existing, $data, $discount, $expectedGross, $advancedOn, $selectedIds);
                }

                $card = CreditCard::query()->whereBelongsTo($user)->where('status', RecordStatus::Active)
                    ->whereKey($data['credit_card_id'])->lockForUpdate()->first();
                $account = Account::query()->whereBelongsTo($user)->where('status', RecordStatus::Active)
                    ->whereKey($data['source_account_id'])->lockForUpdate()->first();
                if (! $card) {
                    throw ValidationException::withMessages(['credit_card_id' => 'Escolha um cartão disponível.']);
                }
                if (! $account) {
                    throw ValidationException::withMessages(['source_account_id' => 'Escolha uma conta disponível.']);
                }

                $installments = CardInstallment::query()->whereBelongsTo($user)
                    ->where('status', CardInstallmentStatus::Pending)
                    ->whereDate('due_on', '>', $advancedOn->endOfMonth()->toDateString())
                    ->whereIn('id', $selectedIds)
                    ->whereHas('purchase', fn ($query) => $query->where('credit_card_id', $card->id))
                    ->with('purchase')->orderBy('id')->lockForUpdate()->get();
                if ($installments->count() !== count($selectedIds)) {
                    throw ValidationException::withMessages(['installment_ids' => 'Selecione apenas parcelas futuras e pendentes deste cartão.']);
                }
                if ($installments->contains(fn (CardInstallment $installment): bool => $installment->purchase->purchased_on->isAfter($advancedOn))) {
                    throw ValidationException::withMessages(['advanced_on' => 'A antecipação não pode ser anterior à compra selecionada.']);
                }
                if (CardPaymentAllocation::query()->whereBelongsTo($user)
                    ->whereIn('card_installment_id', $selectedIds)
                    ->whereHas('payment', fn ($query) => $query->whereDate('paid_on', '>', $advancedOn->toDateString()))
                    ->exists()) {
                    throw ValidationException::withMessages(['advanced_on' => 'Existem pagamentos posteriores à data informada. Use uma data atual ou revise as parcelas.']);
                }

                $remainingById = $installments->mapWithKeys(fn (CardInstallment $installment): array => [
                    $installment->id => (string) BigDecimal::of($installment->gross_amount)->minus($installment->paid_amount),
                ])->all();
                $gross = collect($remainingById)->reduce(
                    fn (BigDecimal $total, string $amount): BigDecimal => $total->plus($amount),
                    BigDecimal::zero(),
                )->toScale(2, RoundingMode::Unnecessary);
                if (! $gross->isEqualTo($expectedGross)) {
                    throw ValidationException::withMessages(['expected_gross_amount' => 'As parcelas mudaram. Revise a prévia antes de confirmar.']);
                }
                if ($discount->isGreaterThan($gross)) {
                    throw ValidationException::withMessages(['discount_amount' => 'O desconto não pode ultrapassar o saldo selecionado.']);
                }
                $net = $gross->minus($discount);
                if ($net->isPositive() && $this->accountBalance($account)->isLessThan($net)) {
                    throw ValidationException::withMessages(['discount_amount' => 'A conta escolhida não possui saldo suficiente para o valor líquido.']);
                }
                $allocationData = $this->calculator->allocate($remainingById, (string) $discount);

                $ledgerEntry = $net->isZero() ? null : $account->ledgerEntries()->create([
                    'user_id' => $user->id,
                    'type' => LedgerEntryType::CardAdvance,
                    'amount' => (string) $net,
                    'operation_id' => $operationId,
                    'occurred_at' => $advancedOn->toDateString(),
                    'description' => 'Antecipação de parcelas do cartão '.$card->name,
                ]);
                $advance = CardAdvance::query()->create([
                    'user_id' => $user->id, 'credit_card_id' => $card->id, 'source_account_id' => $account->id,
                    'ledger_entry_id' => $ledgerEntry?->id, 'gross_amount' => (string) $gross,
                    'discount_amount' => (string) $discount, 'net_amount' => (string) $net,
                    'advanced_on' => $advancedOn->toDateString(), 'selected_installment_ids' => $selectedIds,
                    'operation_id' => $operationId,
                ]);
                if ($ledgerEntry) {
                    $this->auditRecorder->record($user, AuditAction::Created, $ledgerEntry);
                }
                $this->auditRecorder->record($user, AuditAction::Created, $advance);

                foreach ($allocationData as $installmentId => $values) {
                    $installment = $installments->firstWhere('id', $installmentId);
                    $allocation = CardAdvanceAllocation::query()->create([
                        'user_id' => $user->id, 'card_advance_id' => $advance->id,
                        'card_installment_id' => $installment->id, 'gross_amount' => $values['gross'],
                        'discount_amount' => $values['discount'], 'net_amount' => $values['net'],
                        'original_due_on' => $installment->original_due_on->toDateString(),
                    ]);
                    $before = $installment->attributesToArray();
                    $installment->status = CardInstallmentStatus::Advanced;
                    $installment->save();
                    $this->auditRecorder->record($user, AuditAction::Created, $allocation);
                    $this->auditRecorder->record($user, AuditAction::Updated, $installment, $before);
                }

                $this->refreshAlert->handle($user);

                return $advance->load('allocations');
            }, 3);
        } catch (UniqueConstraintViolationException) {
            $existing = CardAdvance::query()->whereBelongsTo($user)->where('operation_id', $operationId)->with('allocations')->first();
            if ($existing) {
                return $this->validateReplay($existing, $data, $discount, $expectedGross, $advancedOn, $selectedIds);
            }

            throw ValidationException::withMessages(['operation_id' => 'Não foi possível repetir a antecipação com segurança.']);
        }
    }

    /** @return list<int> */
    private function selectedIds(mixed $rawIds): array
    {
        if (! is_array($rawIds) || $rawIds === [] || count($rawIds) > 200
            || collect($rawIds)->contains(fn ($id): bool => filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false)
            || count($rawIds) !== count(array_unique($rawIds, SORT_REGULAR))) {
            throw ValidationException::withMessages(['installment_ids' => 'Selecione parcelas válidas, sem repetição.']);
        }

        return collect($rawIds)->map(fn ($id): int => (int) $id)->sort()->values()->all();
    }

    private function money(string $value, string $field, bool $allowZero = false): BigDecimal
    {
        if (! preg_match('/\A\d{1,17}(?:\.\d{1,2})?\z/', $value)) {
            throw ValidationException::withMessages([$field => 'Informe um valor válido com até duas casas decimais.']);
        }
        $amount = BigDecimal::of($value)->toScale(2, RoundingMode::Unnecessary);
        if ($amount->isNegative() || (! $allowZero && $amount->isZero())) {
            throw ValidationException::withMessages([$field => 'Informe um valor válido.']);
        }

        return $amount;
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

    /** @param list<int> $selectedIds */
    private function validateReplay(CardAdvance $advance, array $data, BigDecimal $discount, BigDecimal $gross, CarbonImmutable $advancedOn, array $selectedIds): CardAdvance
    {
        if ($advance->credit_card_id !== (int) $data['credit_card_id'] || $advance->source_account_id !== (int) $data['source_account_id']
            || $advance->gross_amount !== (string) $gross || $advance->discount_amount !== (string) $discount
            || $advance->advanced_on->toDateString() !== $advancedOn->toDateString()
            || ($advance->selected_installment_ids ?? []) !== $selectedIds) {
            throw ValidationException::withMessages(['operation_id' => 'Esta chave já foi usada com dados diferentes.']);
        }

        return $advance;
    }
}
