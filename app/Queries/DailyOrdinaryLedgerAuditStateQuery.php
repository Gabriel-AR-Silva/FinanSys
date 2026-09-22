<?php

namespace App\Queries;

use App\Enums\AuditAction;
use App\Enums\ExpensePlanningType;
use App\Enums\LedgerEntryType;
use App\Models\AuditLog;
use App\Models\LedgerEntry;
use App\Models\User;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Provisional reconstruction of ledger creation/update events only. Reversals,
 * refund links, deletions and missing historical events are NOT reconciled.
 * Never use this subtotal as confirmed spending or combine it with the
 * current-state ledger query: the two views describe overlapping entries.
 */
final class DailyOrdinaryLedgerAuditStateQuery
{
    /** @return array{ordinary_audited_total:string,entry_ids:list<int>,unverifiable_entry_ids:list<int>,unclassified_count:int,coverage:string} */
    public function forUserOnDay(User $user, string $localDate, ?CarbonImmutable $observedAt = null): array
    {
        $day = CarbonImmutable::createFromFormat('!Y-m-d', $localDate, 'America/Sao_Paulo');
        if ($day === false || $day->format('Y-m-d') !== $localDate) {
            throw new InvalidArgumentException('Informe um dia local válido.');
        }

        $observed = ($observedAt ?? CarbonImmutable::now('UTC'))->utc();
        if ($observed->lessThan($day->startOfDay()->utc())) {
            throw new InvalidArgumentException('Não é possível consultar antes do início do dia.');
        }

        // V1 audit_logs.created_at is an offset-free Sao Paulo local DATETIME.
        $cutoff = $observed->setTimezone('America/Sao_Paulo')->format('Y-m-d H:i:s');
        $events = AuditLog::query()
            ->where('user_id', $user->getKey())
            ->where('auditable_type', (new LedgerEntry)->getMorphClass())
            ->whereIn('action', [AuditAction::Created->value, AuditAction::Updated->value])
            ->where('created_at', '<=', $cutoff)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $states = [];
        $invalid = [];
        $createdIds = [];
        foreach ($events as $event) {
            $id = (int) $event->auditable_id;
            if ($event->action === AuditAction::Created->value) {
                $createdIds[$id] = true;
                if (array_key_exists($id, $states)) {
                    $invalid[$id] = true;

                    continue;
                }
            } elseif (! array_key_exists($id, $states)) {
                $invalid[$id] = true;

                continue;
            }

            $after = $event->after;
            if (! is_array($after)
                || ! isset($after['id'], $after['user_id'], $after['type'], $after['occurred_at'], $after['amount'])
                || ! is_numeric($after['id']) || (int) $after['id'] !== $id
                || ! is_numeric($after['user_id']) || (int) $after['user_id'] !== (int) $user->getKey()
                || ! in_array($after['type'], array_column(LedgerEntryType::cases(), 'value'), true)
                || ! is_string($after['occurred_at'])
                || ! preg_match('/\A\d{4}-\d{2}-\d{2}(?:[ T]\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:\d{2})?)?\z/', $after['occurred_at'])
                || ! is_string($after['amount'])
                || ! preg_match('/\A\d{1,17}(?:\.\d{1,2})?\z/', $after['amount'])
                || (array_key_exists('planning_type', $after) && $after['planning_type'] !== null
                    && ! in_array($after['planning_type'], array_column(ExpensePlanningType::cases(), 'value'), true))) {
                $invalid[$id] = true;

                continue;
            }

            $states[$id] = $after;
        }

        // A mutable occurred_at cannot delimit this check. An old expense
        // without creation audit might have moved out of its original day.
        $presentIds = LedgerEntry::withTrashed()->where('user_id', $user->getKey())
            ->where('created_at', '<=', $cutoff)->pluck('id');
        foreach ($presentIds as $id) {
            if (! isset($createdIds[(int) $id])) {
                $invalid[(int) $id] = true;
            }
        }

        // Creation/update events alone cannot reconstruct a deletion. A row
        // soft-deleted by the observation cutoff must not remain in the
        // audited subtotal merely because its last creation snapshot survived.
        // Ledger timestamps use the same local DATETIME convention as above.
        $deletedIds = LedgerEntry::withTrashed()
            ->where('user_id', $user->getKey())
            ->where('type', LedgerEntryType::Expense)
            ->where('created_at', '<=', $cutoff)
            ->whereNotNull('deleted_at')
            ->where('deleted_at', '<=', $cutoff)
            ->pluck('id');
        foreach ($deletedIds as $id) {
            $invalid[(int) $id] = true;
        }

        $total = BigDecimal::zero();
        $ids = [];
        $unclassified = 0;
        foreach ($states as $id => $state) {
            if (isset($invalid[$id]) || substr($state['occurred_at'], 0, 10) !== $localDate
                || $state['type'] !== LedgerEntryType::Expense->value
                || ($state['reversal_of_operation_id'] ?? null) !== null) {
                continue;
            }
            if (($state['planning_type'] ?? null) === null) {
                $unclassified++;

                continue;
            }
            if ($state['planning_type'] !== ExpensePlanningType::Ordinary->value) {
                continue;
            }
            $total = $total->plus($state['amount']);
            $ids[] = (int) $id;
        }
        sort($ids);
        $unverifiable = array_map('intval', array_keys($invalid));
        sort($unverifiable);

        return [
            'ordinary_audited_total' => (string) $total->toScale(2),
            'entry_ids' => $ids,
            'unverifiable_entry_ids' => $unverifiable,
            'unclassified_count' => $unclassified,
            'coverage' => $unverifiable === [] ? 'audited_ledger_creation_updates_only' : 'partial_audited_ledger_creation_updates',
        ];
    }
}
