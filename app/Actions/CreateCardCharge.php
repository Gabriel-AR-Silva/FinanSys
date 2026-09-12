<?php

namespace App\Actions;

use App\Enums\AuditAction;
use App\Enums\CardChargeType;
use App\Enums\CardInstallmentStatus;
use App\Enums\CategoryType;
use App\Enums\ExpensePlanningType;
use App\Enums\RecordStatus;
use App\Models\CardCharge;
use App\Models\Category;
use App\Models\CreditCard;
use App\Models\User;
use App\Support\AuditRecorder;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateCardCharge
{
    public function __construct(
        private AuditRecorder $auditRecorder,
        private RefreshCurrentInternalAlert $refreshAlert,
    ) {}

    /** @param array{credit_card_id:int,category_id:int,type:string,description:string,planning_type:string,amount:string,charged_on:string,due_on:string,operation_id:string} $data */
    public function handle(User $user, array $data): CardCharge
    {
        $amount = $this->money($data['amount']);
        $description = trim($data['description']);
        if ($description === '' || mb_strlen($description) > 255) {
            throw ValidationException::withMessages(['description' => 'Informe uma descrição com até 255 caracteres.']);
        }
        $chargedOn = $this->date($data['charged_on'], 'charged_on');
        if ($chargedOn->isAfter(today('America/Sao_Paulo'))) {
            throw ValidationException::withMessages(['charged_on' => 'A cobrança não pode estar no futuro.']);
        }
        $dueOn = $this->date($data['due_on'], 'due_on');
        $type = CardChargeType::tryFrom($data['type']);
        $planningType = ExpensePlanningType::tryFrom($data['planning_type']);
        if ($type === null) {
            throw ValidationException::withMessages(['type' => 'Escolha juros ou multa.']);
        }
        if (! in_array($planningType, [ExpensePlanningType::Ordinary, ExpensePlanningType::Extraordinary], true)) {
            throw ValidationException::withMessages(['planning_type' => 'O encargo deve ser cotidiano ou extraordinário.']);
        }
        if (! Str::isUuid($data['operation_id'])) {
            throw ValidationException::withMessages(['operation_id' => 'Informe uma chave de operação válida.']);
        }
        $operationId = strtolower($data['operation_id']);

        try {
            return DB::transaction(function () use ($user, $data, $amount, $description, $chargedOn, $dueOn, $type, $planningType, $operationId): CardCharge {
                User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
                $existing = CardCharge::query()->whereBelongsTo($user)->where('operation_id', $operationId)->first();
                if ($existing) {
                    return $this->validateReplay($existing, $data, $amount, $description, $chargedOn, $dueOn, $type, $planningType);
                }
                $card = CreditCard::query()->whereBelongsTo($user)->where('status', RecordStatus::Active)->whereKey($data['credit_card_id'])->lockForUpdate()->first();
                $category = Category::query()->whereBelongsTo($user)->where('type', CategoryType::Expense)->where('status', RecordStatus::Active)->whereKey($data['category_id'])->lockForUpdate()->first();
                if (! $card) {
                    throw ValidationException::withMessages(['credit_card_id' => 'Escolha um cartão disponível.']);
                }
                if (! $category) {
                    throw ValidationException::withMessages(['category_id' => 'Escolha uma categoria de despesa disponível.']);
                }
                $charge = CardCharge::query()->create([
                    'user_id' => $user->id, 'credit_card_id' => $card->id, 'category_id' => $category->id,
                    'type' => $type, 'description' => $description, 'planning_type' => $planningType,
                    'amount' => (string) $amount, 'paid_amount' => '0.00', 'charged_on' => $chargedOn->toDateString(),
                    'due_on' => $dueOn->toDateString(), 'status' => CardInstallmentStatus::Pending, 'operation_id' => $operationId,
                ]);
                $this->auditRecorder->record($user, AuditAction::Created, $charge);
                $this->refreshAlert->handle($user);

                return $charge;
            }, 3);
        } catch (UniqueConstraintViolationException) {
            $existing = CardCharge::query()->whereBelongsTo($user)->where('operation_id', $operationId)->first();
            if ($existing) {
                return $this->validateReplay($existing, $data, $amount, $description, $chargedOn, $dueOn, $type, $planningType);
            }

            throw ValidationException::withMessages(['operation_id' => 'Não foi possível repetir o encargo com segurança.']);
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

    private function date(string $value, string $field): CarbonImmutable
    {
        $date = CarbonImmutable::createFromFormat('!Y-m-d', $value, 'America/Sao_Paulo');
        if ($date === false || $date->format('Y-m-d') !== $value) {
            throw ValidationException::withMessages([$field => 'Informe uma data válida.']);
        }

        return $date;
    }

    private function validateReplay(CardCharge $charge, array $data, BigDecimal $amount, string $description, CarbonImmutable $chargedOn, CarbonImmutable $dueOn, CardChargeType $type, ExpensePlanningType $planningType): CardCharge
    {
        if ($charge->credit_card_id !== (int) $data['credit_card_id'] || $charge->category_id !== (int) $data['category_id']
            || $charge->type !== $type || $charge->description !== $description || $charge->planning_type !== $planningType
            || $charge->amount !== (string) $amount || $charge->charged_on->toDateString() !== $chargedOn->toDateString()
            || $charge->due_on->toDateString() !== $dueOn->toDateString()) {
            throw ValidationException::withMessages(['operation_id' => 'Esta chave já foi usada com dados diferentes.']);
        }

        return $charge;
    }
}
