<?php

namespace App\Queries;

use App\Enums\ExpensePlanningType;
use App\Enums\LedgerEntryType;
use App\Models\LedgerEntry;
use App\Models\User;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Preliminary ledger-only input for D2. This deliberately excludes card
 * purchases/installments, card settlements, transfers and fixed/extraordinary
 * expenses. It must NOT be presented as complete daily spending or used to
 * persist a confirmed check-in until the card/coverage adapter is implemented.
 * The observation cutoff applies to refund links and reversal effective dates;
 * this remains a current-state read, not an immutable historical snapshot.
 */
final class DailyOrdinaryLedgerExpenseQuery
{
    /** @return array{ordinary_total:string,entry_ids:list<int>,unclassified_count:int,unverifiable_entry_ids:list<int>,coverage:string} */
    public function forUserOnDay(User $user, string $localDate, ?CarbonImmutable $observedAt = null): array
    {
        $day = CarbonImmutable::createFromFormat('!Y-m-d', $localDate, 'America/Sao_Paulo');
        if ($day === false || $day->format('Y-m-d') !== $localDate) {
            throw new InvalidArgumentException('Informe um dia local válido.');
        }

        $observed = ($observedAt ?? CarbonImmutable::now('UTC'))->utc();
        if ($observed->lessThan($day->startOfDay()->utc())) {
            throw new InvalidArgumentException('Não é possível consultar gastos antes do início do dia.');
        }

        // V1 writers store offset-free application-local DATETIME values.
        // Compare in Sao Paulo wall time; the observation input remains UTC.
        $start = $day->startOfDay()->format('Y-m-d H:i:s');
        $end = $day->addDay()->startOfDay()->format('Y-m-d H:i:s');
        $cutoff = $observed->setTimezone('America/Sao_Paulo')->format('Y-m-d H:i:s');

        // An expense edited after the observation may have moved to or from a
        // different day, changed classification, or changed amount. Search all
        // user expenses rather than only the currently selected day, otherwise
        // a moved expense could disappear without any coverage warning. This
        // intentionally errs on the side of marking unrelated days partial.
        $unverifiable = LedgerEntry::withTrashed()
            ->where('user_id', $user->getKey())
            ->where('type', LedgerEntryType::Expense)
            ->whereNull('reversal_of_operation_id')
            ->where('created_at', '<=', $cutoff)
            ->where(function ($query) use ($cutoff): void {
                $query->where('updated_at', '>', $cutoff)
                    ->orWhere('deleted_at', '>', $cutoff);
            })
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $entries = LedgerEntry::query()
            ->where('user_id', $user->getKey())
            ->where('type', LedgerEntryType::Expense)
            ->whereNull('reversal_of_operation_id')
            ->where('occurred_at', '>=', $start)
            ->where('occurred_at', '<', $end)
            ->where('occurred_at', '<=', $cutoff)
            ->where('created_at', '<=', $cutoff)
            ->whereNotExists(fn ($query) => $query->selectRaw('1')
                ->from('ledger_entries as reversals')
                ->whereColumn('reversals.user_id', 'ledger_entries.user_id')
                ->whereColumn('reversals.reversal_of_operation_id', 'ledger_entries.operation_id')
                ->whereNull('reversals.deleted_at')
                ->where('reversals.created_at', '<=', $cutoff)
                ->where('reversals.occurred_at', '<=', $cutoff))
            ->with(['expenseRefunds' => fn ($query) => $query
                ->where('user_id', $user->getKey())
                ->where('created_at', '<=', $cutoff)
                ->whereHas('refundEntry', fn ($refund) => $refund
                    ->where('user_id', $user->getKey())
                    ->where('type', LedgerEntryType::Refund)
                    ->whereNull('reversal_of_operation_id')
                    ->where('created_at', '<=', $cutoff)
                    ->where('occurred_at', '<=', $cutoff)
                    ->whereNotExists(fn ($reversals) => $reversals->selectRaw('1')
                        ->from('ledger_entries as refund_reversals')
                        ->whereColumn('refund_reversals.user_id', 'ledger_entries.user_id')
                        ->whereColumn('refund_reversals.reversal_of_operation_id', 'ledger_entries.operation_id')
                        ->whereNull('refund_reversals.deleted_at')
                        ->where('refund_reversals.created_at', '<=', $cutoff)
                        ->where('refund_reversals.occurred_at', '<=', $cutoff)))
                ->with('refundEntry')])
            ->orderBy('id')
            ->get();

        $total = BigDecimal::zero();
        $ids = [];
        $unclassified = 0;
        foreach ($entries as $entry) {
            if (in_array((int) $entry->getKey(), $unverifiable, true)) {
                continue;
            }
            if ($entry->planning_type === null) {
                $unclassified++;

                continue;
            }
            if ($entry->planning_type !== ExpensePlanningType::Ordinary) {
                continue;
            }

            $refunds = $entry->expenseRefunds->reduce(
                fn (BigDecimal $amount, $refund): BigDecimal => $amount->plus($refund->refundEntry->amount),
                BigDecimal::zero(),
            );
            $total = $total->plus(BigDecimal::of($entry->amount)->minus($refunds));
            $ids[] = (int) $entry->getKey();
        }

        return [
            'ordinary_total' => (string) $total->toScale(2),
            'entry_ids' => $ids,
            'unclassified_count' => $unclassified,
            'unverifiable_entry_ids' => $unverifiable,
            'coverage' => $unverifiable === [] ? 'ledger_only' : 'partial_ledger_unverifiable_edits',
        ];
    }
}
