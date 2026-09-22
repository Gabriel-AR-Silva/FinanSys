<?php

namespace App\Queries;

use App\Enums\AuditAction;
use App\Enums\ExpensePlanningType;
use App\Models\AuditLog;
use App\Models\CardPurchase;
use App\Models\User;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Read-only reconstruction from recorded purchase creation/update audits only.
 * This is not complete card spending: reversals, deletions and linked credits
 * still require reconciliation. Unverifiable IDs are user-wide, not day-only,
 * because an unaudited purchase may have been moved away from its original day.
 */
final class DailyCardPurchaseAuditStateQuery
{
    /** @return array{ordinary_audited_total:string,purchase_ids:list<int>,unverifiable_purchase_ids:list<int>,coverage:string} */
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

        // Card purchase and audit DATETIME columns store offset-free UTC.
        $cutoff = $observed->format('Y-m-d H:i:s');
        $events = AuditLog::query()
            ->where('user_id', $user->getKey())
            ->where('auditable_type', (new CardPurchase)->getMorphClass())
            ->whereIn('action', [AuditAction::Created->value, AuditAction::Updated->value])
            ->where('created_at', '<=', $cutoff)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $states = [];
        $invalid = [];
        $createdIds = [];
        $lastAuditAt = [];
        foreach ($events as $event) {
            $id = (int) $event->auditable_id;
            $lastAuditAt[$id] = $event->getRawOriginal('created_at');
            if ($event->action === AuditAction::Created->value) {
                $createdIds[$id] = true;
            }
            if ($event->action === AuditAction::Updated->value && ! array_key_exists($id, $states)) {
                $invalid[$id] = true;

                continue;
            }
            if ($event->action === AuditAction::Created->value && array_key_exists($id, $states)) {
                $invalid[$id] = true;

                continue;
            }
            $after = $event->after;
            if (! is_array($after) || ! isset($after['id'], $after['user_id'], $after['purchased_on'], $after['gross_amount'], $after['planning_type'])
                || ! is_numeric($after['id']) || (int) $after['id'] !== $id
                || ! is_numeric($after['user_id']) || (int) $after['user_id'] !== (int) $user->getKey()
                || ! is_string($after['purchased_on'])
                || ! preg_match('/\A\d{4}-\d{2}-\d{2}(?:T\d{2}:\d{2}:\d{2}(?:\.\d+)?Z)?\z/', $after['purchased_on'])
                || ! is_string($after['gross_amount'])
                || ! preg_match('/\A\d{1,17}(?:\.\d{1,2})?\z/', $after['gross_amount'])
                || ! in_array($after['planning_type'], array_column(ExpensePlanningType::cases(), 'value'), true)) {
                $invalid[$id] = true;

                continue;
            }
            $states[$id] = $after;
        }

        // Do not scope to mutable purchased_on: unaudited changes may move a
        // purchase away from the requested day. The V1 timestamp precision is
        // one second; equal-second changes cannot be ordered conclusively.
        $present = CardPurchase::withTrashed()
            ->where('user_id', $user->getKey())
            ->where('created_at', '<=', $cutoff)
            ->get(['id', 'updated_at', 'deleted_at']);
        foreach ($present as $purchase) {
            $id = (int) $purchase->getKey();
            if (! isset($createdIds[$id])) {
                $invalid[$id] = true;

                continue;
            }

            // An edit that happened by the observation but after its latest
            // creation/update audit cannot be reconstructed from that audit.
            // Later edits must not invalidate an earlier observation.
            $updatedAt = $purchase->getRawOriginal('updated_at');
            if ($updatedAt !== null && $updatedAt <= $cutoff && $updatedAt > $lastAuditAt[$id]) {
                $invalid[$id] = true;
            }

            // Deletions and reversals are not reconstructed by this view.
            $deletedAt = $purchase->getRawOriginal('deleted_at');
            if ($deletedAt !== null && $deletedAt <= $cutoff) {
                $invalid[$id] = true;
            }
        }

        $total = BigDecimal::zero();
        $ids = [];
        foreach ($states as $id => $state) {
            if (isset($invalid[$id]) || substr($state['purchased_on'], 0, 10) !== $localDate
                || $state['planning_type'] !== ExpensePlanningType::Ordinary->value) {
                continue;
            }
            $total = $total->plus($state['gross_amount']);
            $ids[] = (int) $id;
        }
        sort($ids);
        $unverifiable = array_map('intval', array_keys($invalid));
        sort($unverifiable);

        return [
            'ordinary_audited_total' => (string) $total->toScale(2),
            'purchase_ids' => $ids,
            'unverifiable_purchase_ids' => $unverifiable,
            'coverage' => $unverifiable === [] ? 'audited_purchase_states_only' : 'partial_audited_purchase_states',
        ];
    }
}
