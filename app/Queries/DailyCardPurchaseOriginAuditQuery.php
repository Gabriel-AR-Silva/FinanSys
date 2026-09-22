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
 * Original purchase facts from creation audits, independent of later edits to
 * purchased_on, amount or planning_type. This is not reconciled daily spending:
 * missing legacy audits, deletions and reversals require separate treatment.
 */
final class DailyCardPurchaseOriginAuditQuery
{
    /** @return array{ordinary_original_total:string,purchase_ids:list<int>,unclassified_count:int,unverifiable_audit_ids:list<int>,coverage:string} */
    public function forUserOnDay(User $user, string $localDate, ?CarbonImmutable $observedAt = null): array
    {
        $day = CarbonImmutable::createFromFormat('!Y-m-d', $localDate, 'America/Sao_Paulo');
        if ($day === false || $day->format('Y-m-d') !== $localDate) {
            throw new InvalidArgumentException('Informe um dia local válido.');
        }

        $observed = ($observedAt ?? CarbonImmutable::now('UTC'))->utc();
        if ($observed->lessThan($day->startOfDay()->utc())) {
            throw new InvalidArgumentException('Não é possível consultar compras antes do início do dia.');
        }

        $audits = AuditLog::query()
            ->where('user_id', $user->getKey())
            ->where('auditable_type', (new CardPurchase)->getMorphClass())
            ->where('action', AuditAction::Created->value)
            ->where('created_at', '<=', $observed)
            ->orderBy('id')
            ->get();

        $total = BigDecimal::zero();
        $ids = [];
        $unclassified = 0;
        $unverifiable = [];

        foreach ($audits as $audit) {
            $snapshot = $audit->after;
            if (! is_array($snapshot) || ! isset($snapshot['purchased_on']) || ! is_string($snapshot['purchased_on'])
                || ! preg_match('/\A\d{4}-\d{2}-\d{2}(?:T\d{2}:\d{2}:\d{2}(?:\.\d+)?Z)?\z/', $snapshot['purchased_on'])) {
                $unverifiable[] = (int) $audit->getKey();

                continue;
            }
            // Eloquent serializes immutable_date as ISO UTC; its calendar-date
            // prefix denotes the original date, not an additional transaction.
            if (substr($snapshot['purchased_on'], 0, 10) !== $localDate) {
                continue;
            }

            // Treat incomplete or inconsistent snapshots as unknown, not zero.
            if (! isset($snapshot['id'], $snapshot['user_id'], $snapshot['gross_amount'])
                || ! is_numeric($snapshot['id']) || (int) $snapshot['id'] !== (int) $audit->auditable_id
                || (int) $snapshot['user_id'] !== (int) $user->getKey()
                || ! is_string($snapshot['gross_amount'])
                || ! preg_match('/\A\d{1,17}(?:\.\d{1,2})?\z/', $snapshot['gross_amount'])) {
                $unverifiable[] = (int) $audit->getKey();

                continue;
            }

            $planningType = $snapshot['planning_type'] ?? null;
            if ($planningType === null) {
                $unclassified++;

                continue;
            }
            if (! in_array($planningType, array_column(ExpensePlanningType::cases(), 'value'), true)) {
                $unverifiable[] = (int) $audit->getKey();

                continue;
            }
            if ($planningType !== ExpensePlanningType::Ordinary->value) {
                continue;
            }

            $total = $total->plus($snapshot['gross_amount']);
            $ids[] = (int) $audit->auditable_id;
        }

        return [
            'ordinary_original_total' => (string) $total->toScale(2),
            'purchase_ids' => $ids,
            'unclassified_count' => $unclassified,
            'unverifiable_audit_ids' => $unverifiable,
            'coverage' => 'audited_card_purchase_creations_only',
        ];
    }
}
