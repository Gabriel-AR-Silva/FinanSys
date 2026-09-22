<?php

namespace App\Support;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

/**
 * Resolves an explicitly recorded daily budget without reading the V1 derived
 * daily capacity. Backfills are supplied explicitly by the future adapter.
 * This pure helper does not confirm days or persist historical corrections.
 */
final class DailyBudgetVersionSelector
{
    /**
     * @param  list<array{id:int,user_id:int,amount:string,effective_at:string,recorded_at:string}>  $versions
     * @return array{id:int,amount:string}|null
     */
    public function resolve(int $userId, string $date, string $confirmedAt, array $versions): ?array
    {
        if ($userId < 1) {
            throw new InvalidArgumentException('A valid user is required.');
        }

        $day = DateTimeImmutable::createFromFormat('!Y-m-d', $date, new DateTimeZone('America/Sao_Paulo'));
        if ($day === false || $day->format('Y-m-d') !== $date || $day->format('Y') === '0000') {
            throw new InvalidArgumentException('A valid local day is required.');
        }

        $nextDay = $day->modify('+1 day');
        $confirmed = $this->timestamp($confirmedAt);
        if ($confirmed < $nextDay) {
            throw new InvalidArgumentException('A day cannot be confirmed before it ends in Sao Paulo.');
        }

        $selected = null;
        $selectedEffective = null;
        $selectedRecorded = null;
        $seen = [];

        foreach ($versions as $version) {
            if (! is_array($version)
                || ! isset($version['id'], $version['user_id'], $version['amount'], $version['effective_at'], $version['recorded_at'])
                || ! is_int($version['id']) || $version['id'] < 1
                || ! is_int($version['user_id']) || $version['user_id'] !== $userId
                || ! is_string($version['amount'])
                || preg_match('/^(0|[1-9]\d*)(\.\d{1,2})?$/D', $version['amount']) !== 1
                || isset($seen[$version['id']])) {
                throw new InvalidArgumentException('Budget versions must be unique, valid and belong to the user.');
            }

            $seen[$version['id']] = true;
            $effective = $this->timestamp($version['effective_at']);
            $recorded = $this->timestamp($version['recorded_at']);

            // A later change must not rewrite a previously closed day; an
            // explicitly backfilled version may have been recorded later.
            if ($effective >= $nextDay || $recorded > $confirmed) {
                continue;
            }

            if ($selected === null
                || $effective > $selectedEffective
                || ($effective == $selectedEffective && $recorded > $selectedRecorded)
                || ($effective == $selectedEffective && $recorded == $selectedRecorded && $version['id'] > $selected['id'])) {
                $selected = ['id' => $version['id'], 'amount' => $version['amount']];
                $selectedEffective = $effective;
                $selectedRecorded = $recorded;
            }
        }

        return $selected;
    }

    private function timestamp(string $value): DateTimeImmutable
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/D', $value) !== 1) {
            throw new InvalidArgumentException('A timestamp with an explicit UTC offset is required.');
        }

        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:sP', $value);
        if ($parsed === false || $parsed->format('Y-m-d\TH:i:sP') !== $value || $parsed->format('Y') === '0000') {
            throw new InvalidArgumentException('A valid timestamp is required.');
        }

        return $parsed;
    }
}
