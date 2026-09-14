<?php

namespace App\Support;

use App\Enums\FinancialSituation;
use App\Enums\ProtectionType;
use Brick\Math\BigDecimal;
use Brick\Math\BigRational;
use Brick\Math\RoundingMode;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

final class FinancialPlanningMath
{
    /**
     * Totals belong to one open forecast, with receipts already deduplicated by its adapter.
     * The excess is informational and must never be added to received income again.
     * This does not decide cancellation, reversal or the month of a receipt.
     *
     * @return array{received: string, pending: string, excess: string, fulfilled: bool}
     */
    public function receiptProgress(string $expected, string $received): array
    {
        $forecast = $this->nonNegativeDecimal($expected);
        $actual = $this->nonNegativeDecimal($received);

        if ($forecast->isZero()) {
            throw new InvalidArgumentException('A receipt forecast must have a positive expected amount.');
        }

        $difference = $forecast->minus($actual);
        $fulfilled = $difference->isLessThanOrEqualTo(0);

        return [
            'received' => (string) $actual,
            'pending' => $fulfilled ? '0.00' : (string) $difference,
            'excess' => $difference->isNegative() ? (string) $difference->abs() : '0.00',
            'fulfilled' => $fulfilled,
        ];
    }

    /**
     * Extraordinary expenses and outstanding obligations must already be exclusive
     * from the ordinary projection. A paid obligation must be removed by the adapter.
     *
     * @return array{budget:string,ordinary_projected:string,extraordinary:string,obligations:string,projected_total:string}
     */
    public function essentialProjection(string $budget, string $ordinaryProjected, string $extraordinary, string $obligations): array
    {
        $configured = $this->nonNegativeDecimal($budget);
        $ordinary = $this->nonNegativeDecimal($ordinaryProjected);
        $exceptional = $this->nonNegativeDecimal($extraordinary);
        $outstanding = $this->nonNegativeDecimal($obligations);
        $observedProjection = $ordinary->plus($exceptional)->plus($outstanding);

        return [
            'budget' => (string) $configured,
            'ordinary_projected' => (string) $ordinary,
            'extraordinary' => (string) $exceptional,
            'obligations' => (string) $outstanding,
            'projected_total' => (string) ($configured->isGreaterThan($observedProjection) ? $configured : $observedProjection),
        ];
    }

    public function essentialRemaining(string $budget, string $realizedEligible): string
    {
        $difference = $this->nonNegativeDecimal($budget)->minus($this->nonNegativeDecimal($realizedEligible));

        return $difference->isNegative() ? '0.00' : (string) $difference;
    }

    /** @param list<string> $essentialRemainings */
    public function freeMargin(string $variableRemaining, array $essentialRemainings): string
    {
        $margin = $this->signedDecimal($variableRemaining);
        foreach ($essentialRemainings as $remaining) {
            if (! is_string($remaining)) {
                throw new InvalidArgumentException('Essential remaining amounts must be decimal strings.');
            }
            $margin = $margin->minus($this->nonNegativeDecimal($remaining));
        }

        return (string) $margin;
    }

    public function variableBase(string $consideredIncome, string $protection, string $fixed, string $previousCommitments): string
    {
        return (string) $this->nonNegativeDecimal($consideredIncome)
            ->minus($this->nonNegativeDecimal($protection))
            ->minus($this->nonNegativeDecimal($fixed))
            ->minus($this->nonNegativeDecimal($previousCommitments));
    }

    /** @return array{diagnostic_available:bool,daily_rate:?string,projected_total:string} */
    public function ordinaryProjection(string $completedRealized, int $completedDays, string $todayRealized, int $daysAfterToday): array
    {
        if ($completedDays < 0 || $completedDays > 30 || $daysAfterToday < 0 || $daysAfterToday > 30) {
            throw new InvalidArgumentException('Day counts must be between zero and thirty.');
        }

        $completed = $this->nonNegativeDecimal($completedRealized);
        $today = $this->nonNegativeDecimal($todayRealized);
        if ($completedDays < 2) {
            return [
                'diagnostic_available' => false,
                'daily_rate' => null,
                'projected_total' => (string) $completed->plus($today),
            ];
        }

        $rate = BigRational::of($completed)->dividedBy($completedDays);
        $todayProjection = BigRational::of($today)->isGreaterThan($rate) ? BigRational::of($today) : $rate;
        $total = BigRational::of($completed)->plus($todayProjection)->plus($rate->multipliedBy($daysAfterToday));

        return [
            'diagnostic_available' => true,
            'daily_rate' => (string) $rate->toScale(2, RoundingMode::Floor),
            'projected_total' => (string) $total->toScale(2, RoundingMode::Ceiling),
        ];
    }

    /** @return array{situation:FinancialSituation,percentage:?string} */
    public function financialSituation(string $variableBase, string $projectedVariableSpending): array
    {
        $base = $this->signedDecimal($variableBase);
        $spending = $this->nonNegativeDecimal($projectedVariableSpending);
        if ($base->isLessThanOrEqualTo(0)) {
            return [
                'situation' => $spending->isZero() && $base->isZero() ? FinancialSituation::NoBasis : FinancialSituation::Insufficient,
                'percentage' => null,
            ];
        }

        $percentage = BigRational::of($spending)->multipliedBy(100)->dividedBy($base);
        $situation = $percentage->isLessThanOrEqualTo(90)
            ? FinancialSituation::UnderControl
            : ($percentage->isLessThanOrEqualTo(100) ? FinancialSituation::Balanced : FinancialSituation::OutsidePlan);

        return [
            'situation' => $situation,
            'percentage' => (string) $percentage->toScale(2, RoundingMode::HalfUp),
        ];
    }

    /**
     * Receipts must be eligible, non-overlapping amounts for a single selected view.
     * This primitive does not select ledger records or infer projected income.
     *
     * @param  list<string>  $receipts
     */
    public function protection(array $receipts, ProtectionType $type, string $value): string
    {
        $configured = $this->nonNegativeDecimal($value);
        $total = BigDecimal::zero();

        foreach ($receipts as $receipt) {
            if (! is_string($receipt)) {
                throw new InvalidArgumentException('Receipts must be decimal strings.');
            }

            $total = $total->plus($this->nonNegativeDecimal($receipt));
        }

        if ($type === ProtectionType::Fixed) {
            return (string) $configured;
        }

        if ($configured->isGreaterThan(100)) {
            throw new InvalidArgumentException('Protection percentage must not exceed 100.');
        }

        return (string) $total->multipliedBy($configured)->dividedBy(100, 2, RoundingMode::Ceiling);
    }

    /**
     * Distributes only a non-negative planning amount; deficits must remain separate.
     * Closed periods must not call this method with a zero divisor.
     *
     * @return array{daily_amount: string, remainder: string}
     */
    public function dailyAllocation(string $available, int $remainingDays): array
    {
        if ($remainingDays < 1 || $remainingDays > 31) {
            throw new InvalidArgumentException('Remaining days must be between 1 and 31.');
        }

        $amount = $this->nonNegativeDecimal($available);
        $daily = $amount->dividedBy($remainingDays, 2, RoundingMode::Floor);

        return [
            'daily_amount' => (string) $daily,
            'remainder' => (string) $amount->minus($daily->multipliedBy($remainingDays)),
        ];
    }

    /** Days remaining in the evaluation's current Brasília month, including today. */
    public function remainingDaysInCurrentMonth(DateTimeImmutable $evaluatedAt): int
    {
        $local = $evaluatedAt->setTimezone(new DateTimeZone('America/Sao_Paulo'));

        return (int) $local->format('t') - (int) $local->format('j') + 1;
    }

    private function nonNegativeDecimal(string $value): BigDecimal
    {
        if (! preg_match('/\A\d+(?:\.\d{1,2})?\z/', $value)) {
            throw new InvalidArgumentException('Amounts must be non-negative decimal strings with at most two decimal places.');
        }

        return BigDecimal::of($value)->toScale(2, RoundingMode::Unnecessary);
    }

    private function signedDecimal(string $value): BigDecimal
    {
        if (! preg_match('/\A-?\d+(?:\.\d{1,2})?\z/', $value)) {
            throw new InvalidArgumentException('Amounts must be decimal strings with at most two decimal places.');
        }

        return BigDecimal::of($value)->toScale(2, RoundingMode::Unnecessary);
    }
}
