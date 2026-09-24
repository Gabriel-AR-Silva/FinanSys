<?php

namespace App\Actions;

use App\Enums\AuditAction;
use App\Models\CreditCard;
use App\Models\User;
use App\Support\AuditRecorder;
use Illuminate\Support\Facades\DB;

class DeleteCreditCard
{
    public function __construct(private AuditRecorder $auditRecorder) {}

    public function handle(User $user, int $cardId): ?string
    {
        return DB::transaction(function () use ($user, $cardId): ?string {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $card = CreditCard::query()
                ->whereBelongsTo($user)
                ->whereKey($cardId)
                ->lockForUpdate()
                ->firstOrFail();

            $hasHistory = $card->purchases()->exists()
                || $card->charges()->exists()
                || $card->payments()->exists()
                || $card->advances()->exists()
                || $card->credits()->exists()
                || $card->purchaseReversals()->exists();

            if ($hasHistory) {
                return 'Este cartão ainda possui histórico financeiro. Remova compras lançadas por engano ou use os fluxos de correção/estorno antes de excluir o cartão.';
            }

            $before = $card->attributesToArray();
            $card->delete();
            $this->auditRecorder->record($user, AuditAction::Deleted, $card, $before);

            return null;
        }, 3);
    }
}
