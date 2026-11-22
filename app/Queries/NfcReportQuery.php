<?php

namespace App\Queries;

use DateTimeImmutable;

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
        $soldTypes = ['standard' => 0, 'custom' => 0];
        $paymentMethods = [];
        $salePaymentSplit = ['paid' => 0, 'pending' => 0];
        $start = new DateTimeImmutable($from);
        $end = new DateTimeImmutable($to);
        $days = (int) $start->diff($end)->format('%a') + 1;
        $bucketCount = min(8, $days);
        $timeline = [];
        for ($i = 0; $i < $bucketCount; $i++) {
            $tick = $start->modify('+'.(int) floor($days * $i / $bucketCount).' days');
            $timeline[] = [
                'label' => $tick->format($days > 365 ? 'm/y' : 'd/m'),
                'sales' => 0, 'received' => 0, 'pending' => 0, 'cost' => 0,
                'loss' => 0, 'result' => 0, 'sold' => 0, 'manufactured' => 0, 'defective' => 0,
            ];
        }
        $bucketFor = static function (?string $day) use ($from, $to, $start, $days, $bucketCount): ?int {
            if ($day === null || $day < $from || $day > $to) {
                return null;
            }
            $offset = (int) $start->diff(new DateTimeImmutable($day))->format('%a');

            return min($bucketCount - 1, (int) floor($offset * $bucketCount / $days));
        };

        foreach ($dashboard['companies'] as $company) {
            $companyRow = ['name' => $company['name'], 'sales' => 0, 'received' => 0, 'pending' => 0, 'sold' => 0];
            foreach ($company['sales'] as $sale) {
                $saleBucket = $bucketFor($sale['sold_on'] ?? null);
                if ($saleBucket !== null) {
                    $timeline[$saleBucket]['sales'] += $sale['total_cents'];
                    $timeline[$saleBucket]['pending'] += $sale['balance_cents'];
                    $timeline[$saleBucket]['sold'] += count($sale['plates']);
                    $salePaymentSplit['paid'] += max(0, $sale['total_cents'] - $sale['balance_cents']);
                    $salePaymentSplit['pending'] += max(0, $sale['balance_cents']);
                    $values['sales'] += $sale['total_cents'];
                    $companyRow['sales'] += $sale['total_cents'];
                    $values['pending'] += $sale['balance_cents'];
                    $companyRow['pending'] += $sale['balance_cents'];
                    $values['sold'] += count($sale['plates']);
                    $companyRow['sold'] += count($sale['plates']);
                    foreach ($sale['plates'] as $plate) {
                        $kind = ($plate['type'] ?? 'standard') === 'custom' ? 'custom' : 'standard';
                        $soldTypes[$kind]++;
                        if ($plate['defective'] ?? false) {
                            $timeline[$saleBucket]['defective']++;
                            $timeline[$saleBucket]['loss'] += $plate['estimated_loss_cents'] ?? 0;
                            $values['defective']++;
                            $values['loss'] += $plate['estimated_loss_cents'] ?? 0;
                            $unknownLosses += (int) (($plate['estimated_loss_cents'] ?? null) === null);
                        }
                    }
                }
                foreach ($sale['payments'] as $payment) {
                    $paymentBucket = $bucketFor($payment['received_on'] ?? null);
                    if ($paymentBucket !== null) {
                        $timeline[$paymentBucket]['received'] += $payment['amount_cents'];
                        $method = (string) ($payment['method'] ?? 'other');
                        $paymentMethods[$method] = ($paymentMethods[$method] ?? 0) + $payment['amount_cents'];
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
            $batchBucket = $bucketFor($batch['produced_on']);
            if ($batchBucket === null) {
                continue;
            }
            $timeline[$batchBucket]['cost'] += $batch['cost_cents'] ?? 0;
            $values['cost'] += $batch['cost_cents'] ?? 0;
            $unknownCosts += (int) (($batch['cost_cents'] ?? null) === null);
            if ($batch['status'] === 'completed') {
                $values['manufactured'] += $batch['quantity'];
                $timeline[$batchBucket]['manufactured'] += $batch['quantity'];
            }
        }
        $values['result'] = $unknownCosts || $undatedBatches ? null : $values['sales'] - $values['cost'];
        foreach ($timeline as &$bucket) {
            $bucket['result'] = $values['result'] === null ? null : $bucket['sales'] - $bucket['cost'];
        }
        unset($bucket);

        return [
            'values' => $values,
            'companies' => $companies,
            'timeline' => $timeline,
            'soldTypes' => $soldTypes,
            'paymentMethods' => $paymentMethods,
            'salePaymentSplit' => $salePaymentSplit,
            'margin' => $values['result'] !== null && $values['sales'] > 0 ? $values['result'] / $values['sales'] * 100 : null,
            'unknownCosts' => $unknownCosts,
            'unknownLosses' => $unknownLosses,
            'undatedPayments' => $undatedPayments,
            'undatedBatches' => $undatedBatches,
        ];
    }
}
