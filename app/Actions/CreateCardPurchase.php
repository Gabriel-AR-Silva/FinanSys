<?php

namespace App\Actions;

use App\Enums\AuditAction;
use App\Enums\CardInstallmentStatus;
use App\Enums\CategoryType;
use App\Enums\ExpensePlanningType;
use App\Enums\RecordStatus;
use App\Models\CardPurchase;
use App\Models\Category;
use App\Models\CreditCard;
use App\Models\User;
use App\Queries\CreditCardLimitQuery;
use App\Support\AuditRecorder;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateCardPurchase
{
    public function __construct(
        private AuditRecorder $auditRecorder,
        private RefreshCurrentInternalAlert $refreshAlert,
        private CreditCardLimitQuery $cardLimits,
    ) {}

    /** @param array{credit_card_id:int,category_id:int,description:string,planning_type:string,gross_amount:string,purchased_on:string,installments_count:int,first_due_on:string,operation_id:string} $data */
    public function handle(User $user, array $data): CardPurchase
    {
        $amount = $this->money($data['gross_amount']);
        $installmentsCount = (int) $data['installments_count'];
        if ($installmentsCount < 1 || $installmentsCount > 120) {
            throw ValidationException::withMessages(['installments_count' => 'Escolha entre 1 e 120 parcelas.']);
        }
        $description = trim($data['description']);
        if ($description === '' || mb_strlen($description) > 255) {
            throw ValidationException::withMessages(['description' => 'Informe uma descrição com até 255 caracteres.']);
        }
        $purchasedOn = $this->date($data['purchased_on'], 'purchased_on');
        if ($purchasedOn->isAfter(today('America/Sao_Paulo'))) {
            throw ValidationException::withMessages(['purchased_on' => 'A compra não pode estar no futuro.']);
        }
        $firstDueOn = $this->date($data['first_due_on'], 'first_due_on');
        if (! Str::isUuid($data['operation_id'])) {
            throw ValidationException::withMessages(['operation_id' => 'Informe uma chave de operação válida.']);
        }
        $planningType = ExpensePlanningType::tryFrom($data['planning_type']);
        if (! in_array($planningType, [ExpensePlanningType::Ordinary, ExpensePlanningType::Extraordinary], true)) {
            throw ValidationException::withMessages(['planning_type' => 'Compra parcelada deve ser cotidiana ou extraordinária.']);
        }
        $operationId = strtolower($data['operation_id']);

        try {
            return DB::transaction(function () use ($user, $data, $amount, $installmentsCount, $description, $purchasedOn, $firstDueOn, $planningType, $operationId): CardPurchase {
                User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
                $existing = CardPurchase::query()->whereBelongsTo($user)->where('operation_id', $operationId)->with('installments')->first();
                if ($existing) {
                    return $this->validateReplay($existing, $data, $amount, $installmentsCount, $description, $purchasedOn, $firstDueOn, $planningType);
                }
                $card = CreditCard::query()->whereBelongsTo($user)->where('status', RecordStatus::Active)->whereKey($data['credit_card_id'])->lockForUpdate()->first();
                if (! $card) {
                    throw ValidationException::withMessages(['credit_card_id' => 'Escolha um cartão disponível.']);
                }
                if ($card->credit_limit !== null) {
                    $limit = $this->cardLimits->forCard($card);
                    if (BigDecimal::of($amount)->isGreaterThan(BigDecimal::of($limit['available'] ?? '0.00'))) {
                        throw ValidationException::withMessages([
                            'gross_amount' => 'A compra ultrapassa o limite disponível deste cartão.',
                        ]);
                    }
                }
                $category = Category::query()->whereBelongsTo($user)->where('type', CategoryType::Expense)->where('status', RecordStatus::Active)->whereKey($data['category_id'])->lockForUpdate()->first();
                if (! $category) {
                    throw ValidationException::withMessages(['category_id' => 'Escolha uma categoria de despesa disponível.']);
                }
                $purchase = CardPurchase::query()->create([
                    'user_id' => $user->id,
                    'credit_card_id' => $card->id,
                    'category_id' => $category->id,
                    'description' => $description,
                    'planning_type' => $planningType,
                    'gross_amount' => (string) $amount,
                    'purchased_on' => $purchasedOn->toDateString(),
                    'installments_count' => $installmentsCount,
                    'operation_id' => $operationId,
                ]);
                $this->auditRecorder->record($user, AuditAction::Created, $purchase);
                foreach ($this->split($amount, $installmentsCount) as $index => $installmentAmount) {
                    $dueOn = $this->installmentDate($firstDueOn, $index);
                    $installment = $purchase->installments()->create([
                        'user_id' => $user->id,
                        'installment_number' => $index + 1,
                        'gross_amount' => $installmentAmount,
                        'paid_amount' => '0.00',
                        'due_on' => $dueOn,
                        'original_due_on' => $dueOn,
                        'status' => CardInstallmentStatus::Pending,
                    ]);
                    $this->auditRecorder->record($user, AuditAction::Created, $installment);
                }

                $this->refreshAlert->handle($user);

                return $purchase->load('installments');
            }, 3);
        } catch (UniqueConstraintViolationException) {
            $existing = CardPurchase::query()->whereBelongsTo($user)->where('operation_id', $operationId)->with('installments')->first();
            if ($existing) {
                return $this->validateReplay($existing, $data, $amount, $installmentsCount, $description, $purchasedOn, $firstDueOn, $planningType);
            }

            throw ValidationException::withMessages(['operation_id' => 'Não foi possível repetir a compra com segurança.']);
        }
    }

    /** @return list<string> */
    private function split(BigDecimal $amount, int $count): array
    {
        $base = $amount->dividedBy($count, 2, RoundingMode::Floor);
        $remainderCents = (int) (string) $amount->minus($base->multipliedBy($count))->multipliedBy(100);

        return array_map(fn (int $index): string => (string) ($index < $remainderCents ? $base->plus('0.01') : $base), range(0, $count - 1));
    }

    private function installmentDate(CarbonImmutable $firstDueOn, int $offset): string
    {
        $month = $firstDueOn->startOfMonth()->addMonths($offset);
        if ($month->year > 9999) {
            throw ValidationException::withMessages(['first_due_on' => 'As parcelas ultrapassam o calendário suportado.']);
        }

        return $month->day(min($firstDueOn->day, $month->daysInMonth))->toDateString();
    }

    private function money(string $value): BigDecimal
    {
        if (! preg_match('/\A\d{1,17}(?:\.\d{1,2})?\z/', $value)) {
            throw ValidationException::withMessages(['gross_amount' => 'Informe um valor positivo com até duas casas decimais.']);
        }
        $amount = BigDecimal::of($value)->toScale(2, RoundingMode::Unnecessary);
        if (! $amount->isPositive()) {
            throw ValidationException::withMessages(['gross_amount' => 'O valor deve ser maior que zero.']);
        }

        return $amount;
    }

    private function date(string $value, string $field): CarbonImmutable
    {
        $date = CarbonImmutable::createFromFormat('!Y-m-d', $value, 'America/Sao_Paulo');
        if ($date === false || $date->format('Y-m-d') !== $value) {
            throw ValidationException::withMessages([$field => 'Informe uma data válida.']);
        }

        return $date;
    }

    private function validateReplay(CardPurchase $purchase, array $data, BigDecimal $amount, int $count, string $description, CarbonImmutable $purchasedOn, CarbonImmutable $firstDueOn, ExpensePlanningType $planningType): CardPurchase
    {
        if ($purchase->credit_card_id !== (int) $data['credit_card_id'] || $purchase->category_id !== (int) $data['category_id']
            || $purchase->description !== $description || $purchase->planning_type !== $planningType || $purchase->gross_amount !== (string) $amount
            || $purchase->purchased_on->toDateString() !== $purchasedOn->toDateString() || $purchase->installments_count !== $count
            || $purchase->installments->first()?->due_on->toDateString() !== $firstDueOn->toDateString()) {
            throw ValidationException::withMessages(['operation_id' => 'Esta chave já foi usada com dados diferentes.']);
        }

        return $purchase;
    }
}
