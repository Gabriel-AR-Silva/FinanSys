<?php

namespace App\Support;

class OfxRelationshipDetector
{
    /**
     * @param list<OfxTransaction> $transactions
     * @return array<string, list<int>>
     */
    public function fitIdGroups(array $transactions): array
    {
        $groups = [];

        foreach ($transactions as $transaction) {
            if ($transaction->externalId === null) {
                continue;
            }

            $base = preg_replace('/:(?:reversal|estorno)$/i', '', $transaction->externalId);
            $groups[$base][] = $transaction->sourceIndex;
        }

        return array_filter($groups, fn (array $indexes): bool => count($indexes) > 1);
    }
}
