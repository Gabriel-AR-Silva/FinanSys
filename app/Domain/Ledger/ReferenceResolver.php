<?php

namespace App\Domain\Ledger;

use App\Enums\LedgerEntryReferenceType;
use App\Enums\RecordStatus;
use App\Models\Account;
use App\Models\Pocket;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class ReferenceResolver
{
    public function resolve(User $user, LedgerEntryReferenceType $type, int $id): Account|Pocket
    {
        $reference = match ($type) {
            LedgerEntryReferenceType::Account => Account::query()
                ->whereBelongsTo($user)
                ->find($id),
            LedgerEntryReferenceType::Pocket => Pocket::query()
                ->whereBelongsTo($user)
                ->whereHas('account', fn ($query) => $query->whereBelongsTo($user))
                ->find($id),
        };

        if ($reference === null) {
            throw ValidationException::withMessages([
                'reference_id' => 'A referência não existe para o usuário autenticado.',
            ]);
        }

        return $reference;
    }

    public function resolveActive(User $user, LedgerEntryReferenceType $type, int $id, string $errorKey = 'reference_id'): Account|Pocket
    {
        $reference = match ($type) {
            LedgerEntryReferenceType::Account => Account::query()
                ->whereBelongsTo($user)
                ->where('status', RecordStatus::Active)
                ->find($id),
            LedgerEntryReferenceType::Pocket => Pocket::query()
                ->whereBelongsTo($user)
                ->where('status', RecordStatus::Active)
                ->whereHas('account', fn ($query) => $query
                    ->whereBelongsTo($user)
                    ->where('status', RecordStatus::Active))
                ->find($id),
        };

        if ($reference === null) {
            throw ValidationException::withMessages([
                $errorKey => 'A referência selecionada não está disponível.',
            ]);
        }

        return $reference;
    }
}
