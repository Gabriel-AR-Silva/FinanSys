<?php

namespace App\Actions;

use App\Enums\AuditAction;
use App\Models\CreditCard;
use App\Models\User;
use App\Support\AuditRecorder;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateCreditCard
{
    public function __construct(private AuditRecorder $auditRecorder) {}

    /** @param array{name:string,closing_day:int,due_day:int,credit_limit?:string|null,operation_id:string} $data */
    public function handle(User $user, array $data): CreditCard
    {
        $name = trim($data['name']);
        $creditLimit = $this->creditLimit($data['credit_limit'] ?? null);

        try {
            return DB::transaction(function () use ($user, $data, $name, $creditLimit): CreditCard {
                $existing = CreditCard::query()->whereBelongsTo($user)->withTrashed()->where('operation_id', $data['operation_id'])->first();
                if ($existing !== null) {
                    return $this->validateReplay($existing, $data, $name, $creditLimit);
                }

                $card = CreditCard::query()->create([
                    'user_id' => $user->id,
                    'name' => $name,
                    'closing_day' => $data['closing_day'],
                    'due_day' => $data['due_day'],
                    'credit_limit' => $creditLimit,
                    'operation_id' => strtolower($data['operation_id']),
                ]);
                $this->auditRecorder->record($user, AuditAction::Created, $card);

                return $card;
            }, 3);
        } catch (UniqueConstraintViolationException) {
            $existing = CreditCard::query()->whereBelongsTo($user)->withTrashed()->where('operation_id', $data['operation_id'])->first();
            if ($existing !== null) {
                return $this->validateReplay($existing, $data, $name, $creditLimit);
            }

            throw ValidationException::withMessages(['operation_id' => 'Não foi possível repetir o cadastro com segurança.']);
        }
    }

    private function validateReplay(CreditCard $card, array $data, string $name, ?string $creditLimit): CreditCard
    {
        if ($card->name !== $name || $card->closing_day !== (int) $data['closing_day'] || $card->due_day !== (int) $data['due_day']
            || $card->credit_limit !== $creditLimit) {
            throw ValidationException::withMessages(['operation_id' => 'Esta chave já foi usada com dados diferentes.']);
        }

        return $card;
    }

    private function creditLimit(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }
        if (! preg_match('/\A\d{1,17}(?:\.\d{1,2})?\z/', $value)) {
            throw ValidationException::withMessages(['credit_limit' => 'Informe um limite positivo com até duas casas decimais.']);
        }

        $limit = BigDecimal::of($value)->toScale(2, RoundingMode::Unnecessary);
        if (! $limit->isPositive()) {
            throw ValidationException::withMessages(['credit_limit' => 'O limite deve ser maior que zero.']);
        }

        return (string) $limit;
    }
}
