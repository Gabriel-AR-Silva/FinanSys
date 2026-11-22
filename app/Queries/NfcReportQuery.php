<?php

namespace App\Queries;

class NfcReportQuery
{
    public const METRICS = ['sales', 'received', 'pending', 'cost', 'loss', 'result', 'sold', 'manufactured', 'defective'];

    public function calculate(array $dashboard, string $from, string $to): array
    {
        $inRange = fn (?string $date): bool => $date !== null && $date >= $from && $date <= $to;
        $values = array_fill_keys(self::METRICS, 0);
        $unknownCosts = 0;
        $unknownLosses = 0;
        $undatedPayments = 0;
        $undatedBatches = 0;
        $companies = [];
        foreach ($dashboard['companies'] as $company) {
            $companyRow = ['name' => $company['name'], 'sales' => 0, 'received' => 0, 'pending' => 0, 'sold' => 0];
            foreach ($company['sales'] as $sale) {
                if ($inRange($sale['sold_on'] ?? null)) {
                    $values['sales'] += $sale['total_cents'];
                    $companyRow['sales'] += $sale['total_cents'];
                    $values['pending'] += $sale['balance_cents'];
                    $companyRow['pending'] += $sale['balance_cents'];
                    $values['sold'] += count($sale['plates']);
                    $companyRow['sold'] += count($sale['plates']);
                    foreach ($sale['plates'] as $plate) {
                        if ($plate['defective'] ?? false) {
                            $values['defective']++;
                            $values['loss'] += $plate['estimated_loss_cents'] ?? 0;
                            $unknownLosses += (int) (($plate['estimated_loss_cents'] ?? null) === null);
                        }
                    }
                }
                foreach ($sale['payments'] as $payment) {
                    if ($inRange($payment['received_on'] ?? null)) {
                        $values['received'] += $payment['amount_cents'];
                        $companyRow['received'] += $payment['amount_cents'];
                    } elseif (empty($payment['received_on'])) {
                        $undatedPayments++;
                    }
                }
            }
            if ($companyRow['sales'] !== 0 || $companyRow['received'] !== 0 || $companyRow['sold'] !== 0) {
                $companies[] = $companyRow;
            }
        }
        foreach ($dashboard['production'] as $batch) {
            if (empty($batch['produced_on'])) {
                $undatedBatches++;

                continue;
            }
            if (! $inRange($batch['produced_on'])) {
                continue;
            }
            $values['cost'] += $batch['cost_cents'] ?? 0;
            $unknownCosts += (int) (($batch['cost_cents'] ?? null) === null);
            if ($batch['status'] === 'completed') {
                $values['manufactured'] += $batch['quantity'];
            }
        }
        $values['result'] = $unknownCosts || $undatedBatches ? null : $values['sales'] - $values['cost'];

        return [
            'values' => $values,
            'companies' => $companies,
            'margin' => $values['result'] !== null && $values['sales'] > 0 ? $values['result'] / $values['sales'] * 100 : null,
            'unknownCosts' => $unknownCosts,
            'unknownLosses' => $unknownLosses,
            'undatedPayments' => $undatedPayments,
            'undatedBatches' => $undatedBatches,
        ];
    }
}
