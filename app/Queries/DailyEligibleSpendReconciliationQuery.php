<?php

namespace App\Queries;

use App\Models\User;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;

/**
 * Read-only D2 reconciliation of known ordinary spending origins. This
 * excludes invoice payments, due installments, advances, transfers and
 * fixed/extraordinary commitments by construction. Gross card purchases are
 * observed separately: their daily-budget competence remains a product
 * decision, so they block a final eligible_spent rather than being silently
 * counted on purchase date or spread over installment due dates.
 *
 * This is not a persisted check-in or a certificate of complete legacy audit.
 */
final class DailyEligibleSpendReconciliationQuery
{
    public function __construct(
        private DailyOrdinaryLedgerExpenseQuery $ledger,
        private DailyCardPurchaseRecognitionQuery $purchases,
        private DailyCardChargeRecognitionQuery $charges,
    ) {}

    /** @return array{date:string,observed_at:string,rule_version:string,eligible_spent:?string,coverage:string,blockers:list<string>,sources:array<string,mixed>} */
    public function forUserOnDay(User $user, string $localDate, ?CarbonImmutable $observedAt = null): array
    {
        $observed = ($observedAt ?? CarbonImmutable::now('UTC'))->utc();
        $ledger = $this->ledger->forUserOnDay($user, $localDate, $observed);
        $purchases = $this->purchases->forUserOnDay($user, $localDate, $observed);
        $charges = $this->charges->forUserOnDay($user, $localDate, $observed);

        $blockers = [];
        if ($ledger['coverage'] !== 'ledger_only' || $ledger['unverifiable_entry_ids'] !== []) {
            $blockers[] = 'ledger_history_unverifiable';
        }
        if ($ledger['unclassified_count'] > 0) {
            $blockers[] = 'ledger_classification_missing';
        }
        if ($purchases['coverage'] !== 'gross_card_purchases_only' || $purchases['unverifiable_purchase_ids'] !== []) {
            $blockers[] = 'card_purchase_history_unverifiable';
        }
        if ($purchases['unclassified_count'] > 0) {
            $blockers[] = 'card_purchase_classification_missing';
        }
        if ($purchases['purchase_ids'] !== []) {
            $blockers[] = 'card_purchase_daily_competence_undecided';
        }
        if ($charges['coverage'] !== 'card_charges_only' || $charges['unverifiable_charge_ids'] !== []) {
            $blockers[] = 'card_charge_history_unverifiable';
        }
        if ($charges['unclassified_count'] > 0) {
            $blockers[] = 'card_charge_classification_missing';
        }

        $ledgerTotal = BigDecimal::of($ledger['ordinary_total']);
        $chargeTotal = BigDecimal::of($charges['ordinary_charge_total']);
        if ($ledgerTotal->isNegative() || $chargeTotal->isNegative()) {
            $blockers[] = 'negative_source_requires_refund_reconciliation';
        }
        $blockers = array_values(array_unique($blockers));

        return [
            'date' => $localDate,
            'observed_at' => $observed->toIso8601String(),
            'rule_version' => 'ordinary-ledger-and-charged-card-fees-v1',
            'eligible_spent' => $blockers === [] ? (string) $ledgerTotal->plus($chargeTotal)->toScale(2) : null,
            'coverage' => $blockers === [] ? 'reconciled_without_card_purchase_principal' : 'blocked_incomplete_sources',
            'blockers' => $blockers,
            'sources' => [
                'ledger' => $ledger,
                'card_purchases_gross_behavior_only' => $purchases,
                'card_charges' => $charges,
            ],
        ];
    }
}
