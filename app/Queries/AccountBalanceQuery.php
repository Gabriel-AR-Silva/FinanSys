<?php

namespace App\Queries;

use App\Enums\LedgerEntryType;
use App\Models\Account;
use App\Models\LedgerEntry;
use App\Models\Pocket;
use App\Models\User;
use Illuminate\Support\Collection;

class AccountBalanceQuery
{
    /** @return Collection<int, Account> */
    public function forUser(User $user): Collection
    {
        $positiveTypes = [
            LedgerEntryType::OpeningBalance->value,
            LedgerEntryType::Income->value,
            LedgerEntryType::TransferIn->value,
        ];
        $placeholders = implode(', ', array_fill(0, count($positiveTypes), '?'));

        return Account::query()
            ->whereBelongsTo($user)
            ->select(['accounts.id', 'accounts.name', 'accounts.created_at'])
            ->selectSub(
                LedgerEntry::query()
                    ->selectRaw("COALESCE(SUM(CASE WHEN type IN ({$placeholders}) THEN amount ELSE -amount END), 0)", $positiveTypes)
                    ->whereColumn('reference_id', 'accounts.id')
                    ->where('reference_type', (new Account)->getMorphClass()),
                'balance',
            )
            ->selectSub(
                LedgerEntry::query()
                    ->selectRaw("COALESCE(SUM(CASE WHEN type IN ({$placeholders}) THEN amount ELSE -amount END), 0)", $positiveTypes)
                    ->join('pockets', function ($join): void {
                        $join->on('pockets.id', '=', 'ledger_entries.reference_id')->whereNull('pockets.deleted_at');
                    })
                    ->whereColumn('pockets.account_id', 'accounts.id')
                    ->where('ledger_entries.reference_type', (new Pocket)->getMorphClass()),
                'pockets_total',
            )
            ->orderBy('name')
            ->orderBy('id')
            ->get();
    }
}
