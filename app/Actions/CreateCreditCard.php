<?php

namespace App\Actions;

use App\Enums\AuditAction;
use App\Models\CreditCard;
use App\Models\User;
use App\Support\AuditRecorder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateCreditCard
{
    public function __construct(private AuditRecorder $auditRecorder) {}

    /** @param array{name:string,closing_day:int,due_day:int,operation_id:string} $data */
    public function handle(User $user, array $data): CreditCard
    {
        $name = trim($data['name']);

        try {
            return DB::transaction(function () use ($user, $data, $name): CreditCard {
                $existing = CreditCard::query()->whereBelongsTo($user)->withTrashed()->where('operation_id', $data['operation_id'])->first();
                if ($existing !== null) {
                    return $this->validateReplay($existing, $data, $name);
                }

                $card = CreditCard::query()->create([
                    'user_id' => $user->id,
                    'name' => $name,
                    'closing_day' => $data['closing_day'],
                    'due_day' => $data['due_day'],
                    'operation_id' => strtolower($data['operation_id']),
                ]);
                $this->auditRecorder->record($user, AuditAction::Created, $card);

                return $card;
            }, 3);
        } catch (UniqueConstraintViolationException) {
            $existing = CreditCard::query()->whereBelongsTo($user)->withTrashed()->where('operation_id', $data['operation_id'])->first();
            if ($existing !== null) {
                return $this->validateReplay($existing, $data, $name);
            }

            throw ValidationException::withMessages(['operation_id' => 'Não foi possível repetir o cadastro com segurança.']);
        }
    }

    private function validateReplay(CreditCard $card, array $data, string $name): CreditCard
    {
        if ($card->name !== $name || $card->closing_day !== (int) $data['closing_day'] || $card->due_day !== (int) $data['due_day']) {
            throw ValidationException::withMessages(['operation_id' => 'Esta chave já foi usada com dados diferentes.']);
        }

        return $card;
    }
}
