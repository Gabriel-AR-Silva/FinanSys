<?php

namespace App\Actions;

use App\Enums\AuditAction;
use App\Models\CreditCard;
use App\Models\User;
use App\Support\AuditRecorder;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateCreditCardLimit
{
    public function __construct(private AuditRecorder $auditRecorder) {}

    public function handle(User $user, CreditCard $card, ?string $value): CreditCard
    {
        $limit = $this->creditLimit($value);

        return DB::transaction(function () use ($user, $card, $limit): CreditCard {
            $ownedCard = CreditCard::query()
                ->whereBelongsTo($user)
                ->whereKey($card->getKey())
                ->lockForUpdate()
                ->first();

            if (! $ownedCard) {
                throw ValidationException::withMessages(['credit_limit' => 'Cartão indisponível.']);
            }

            if ($ownedCard->credit_limit === $limit) {
                return $ownedCard;
            }

            $before = $ownedCard->attributesToArray();
            $ownedCard->update(['credit_limit' => $limit]);
            $this->auditRecorder->record($user, AuditAction::Updated, $ownedCard, $before);

            return $ownedCard->refresh();
        }, 3);
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
