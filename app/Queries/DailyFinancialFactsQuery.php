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
     * @return array{ledger:array<string,mixed>,purchase:array<string,mixed>,due:array<string,mixed>,advance:array<string,mixed>,reversal:array<string,mixed>,eligible_spent:null,reconciliation_status:string}
     */
    public function forUserOnDay(User $user, string $localDate, ?CarbonImmutable $observedAt = null): array
    {
        $observed = ($observedAt ?? CarbonImmutable::now('UTC'))->utc();

        return [
            'ledger' => $this->ledger->forUserOnDay($user, $localDate, $observed),
            'purchase' => $this->purchases->forUserOnDay($user, $localDate, $observed),
            // Due commitments currently have no as-of observation contract.
            // Explicitly segregate their current-state data from dated facts.
            'due' => $this->due->forUserOnDay($user, $localDate),
            'advance' => $this->advances->forUserOnDay($user, $localDate, $observed),
            'reversal' => $this->reversals->forUserOnDay($user, $localDate, $observed),
            'eligible_spent' => null,
            'reconciliation_status' => 'unreconciled_distinct_financial_views',
        ];
    }
}
