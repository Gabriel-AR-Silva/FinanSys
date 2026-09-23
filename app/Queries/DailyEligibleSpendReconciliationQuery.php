<?php

namespace App\Queries;

use App\Models\User;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;

/**
 * Read-only D2 reconciliation of known ordinary spending origins. This
 * excludes invoice payments, due installments, advances, transfers and
 * fixed/extraordinary commitments by construction. An ordinary card purchase
 * is consumption on its purchase date. Its installments are obligations and
 * card payments/advances are settlement facts, so neither is added again.
 * Recorded reversals correct the related purchase rather than creating income.
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
        if ($purchases['coverage'] !== 'ordinary_card_purchases_reconciled' || $purchases['unverifiable_purchase_ids'] !== []) {
            $blockers[] = 'card_purchase_history_unverifiable';
        }
        if ($purchases['unclassified_count'] > 0) {
            $blockers[] = 'card_purchase_classification_missing';
        }
        if ($charges['coverage'] !== 'card_charges_only' || $charges['unverifiable_charge_ids'] !== []) {
            $blockers[] = 'card_charge_history_unverifiable';
        }
        if ($charges['unclassified_count'] > 0) {
            $blockers[] = 'card_charge_classification_missing';
        }

        $ledgerTotal = BigDecimal::of($ledger['ordinary_total']);
        $purchaseTotal = BigDecimal::of($purchases['ordinary_purchase_total']);
        $chargeTotal = BigDecimal::of($charges['ordinary_charge_total']);
        if ($ledgerTotal->isNegative() || $purchaseTotal->isNegative() || $chargeTotal->isNegative()) {
            $blockers[] = 'negative_source_requires_refund_reconciliation';
        }
        $blockers = array_values(array_unique($blockers));

        return [
            'date' => $localDate,
            'observed_at' => $observed->toIso8601String(),
            'rule_version' => 'ordinary-consumption-on-occurrence-v2',
            'eligible_spent' => $blockers === [] ? (string) $ledgerTotal->plus($purchaseTotal)->plus($chargeTotal)->toScale(2) : null,
            'coverage' => $blockers === [] ? 'reconciled_ordinary_consumption' : 'blocked_incomplete_sources',
            'blockers' => $blockers,
            'sources' => [
                'ledger' => $ledger,
                'card_purchases' => $purchases,
                'card_charges' => $charges,
            ],
        ];
    }
}
