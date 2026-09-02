<?php

namespace App\Queries;

use App\Enums\LedgerEntryType;
use App\Models\LedgerEntry;
use App\Models\Pocket;
use App\Models\User;
use Illuminate\Support\Collection;

class PocketBalanceQuery
{
    /** @return Collection<int, Pocket> */
    public function forUser(User $user): Collection
    {
        $positiveTypes = [LedgerEntryType::OpeningBalance->value, LedgerEntryType::Income->value, LedgerEntryType::TransferIn->value];
        $placeholders = implode(', ', array_fill(0, count($positiveTypes), '?'));

        return Pocket::query()->whereBelongsTo($user)
            ->with('account:id,name')
            ->select(['pockets.id', 'pockets.account_id', 'pockets.name', 'pockets.created_at'])
            ->selectSub(LedgerEntry::query()
                ->selectRaw("COALESCE(SUM(CASE WHEN type IN ({$placeholders}) THEN amount ELSE -amount END), 0)", $positiveTypes)
                ->whereColumn('reference_id', 'pockets.id')
                ->where('reference_type', (new Pocket)->getMorphClass()), 'balance')
            ->orderBy('name')->orderBy('id')->get();
    }
}
