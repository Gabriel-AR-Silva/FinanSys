<?php

namespace App\Queries;

use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * Read-only D2 reconciliation boundary. These are different financial views,
 * not additive components of a daily expense: a purchase creates consumption
 * and future obligations, an advance changes obligation timing, and a reversal
 * cancels obligations or credits paid amounts. No check-in can consume this
 * envelope until eligibility, historical coverage and de-duplication are proven.
 */
final class DailyFinancialFactsQuery
{
    public function __construct(
        private DailyOrdinaryLedgerExpenseQuery $ledger,
        private DailyCardPurchaseRecognitionQuery $purchases,
        private DailyCardDueCommitmentQuery $due,
        private DailyCardAdvanceImpactQuery $advances,
        private DailyCardPurchaseReversalQuery $reversals,
    ) {}

    /**
     * @return array{ledger:array<string,mixed>,purchase:array<string,mixed>,due:array<string,mixed>,advance:array<string,mixed>,reversal:array<string,mixed>,observed_at:string,coverage_blockers:list<string>,as_of_unsupported_views:list<string>,eligible_spent:null,reconciliation_status:string}
     */
    public function forUserOnDay(User $user, string $localDate, ?CarbonImmutable $observedAt = null): array
    {
        $observed = ($observedAt ?? CarbonImmutable::now('UTC'))->utc();
        $ledger = $this->ledger->forUserOnDay($user, $localDate, $observed);
        $purchase = $this->purchases->forUserOnDay($user, $localDate, $observed);
        // Due commitments can filter by observation but cannot reconstruct a
        // moved due date, removed relationship, or other unversioned old state.
        $due = $this->due->forUserOnDay($user, $localDate, $observed);
        $advance = $this->advances->forUserOnDay($user, $localDate, $observed);
        $reversal = $this->reversals->forUserOnDay($user, $localDate, $observed);

        // Preserve the provenance of uncertainty across views. In particular,
        // an unclassified entry must not be mistaken for a verified zero, and
        // an edited amount must not be included in a historical subtotal.
        $blockers = [];
        foreach (['ledger' => $ledger, 'purchase' => $purchase, 'due' => $due, 'advance' => $advance] as $name => $view) {
            if ($view['unclassified_count'] > 0) {
                $blockers[] = $name.'_unclassified';
            }
        }
        foreach ([
            'ledger' => [$ledger, 'unverifiable_entry_ids'],
            'purchase' => [$purchase, 'unverifiable_purchase_ids'],
            'advance' => [$advance, 'unverifiable_allocation_ids'],
            'reversal' => [$reversal, 'unverifiable_reversal_ids'],
        ] as $name => [$view, $key]) {
            if ($view[$key] !== []) {
                $blockers[] = $name.'_unverifiable';
            }
        }
        if ($due['unverifiable_installment_ids'] !== [] || $due['unverifiable_charge_ids'] !== []) {
            $blockers[] = 'due_unverifiable';
        }

        return [
            'ledger' => $ledger,
            'purchase' => $purchase,
            'due' => $due,
            'advance' => $advance,
            'reversal' => $reversal,
            'observed_at' => $observed->toIso8601String(),
            'coverage_blockers' => $blockers,
            'as_of_unsupported_views' => ['due'],
            'eligible_spent' => null,
            'reconciliation_status' => 'unreconciled_distinct_financial_views',
        ];
    }
}
