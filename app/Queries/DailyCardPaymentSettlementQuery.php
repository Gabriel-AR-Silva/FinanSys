<?php

namespace App\Queries;

use App\Enums\LedgerEntryType;
use App\Models\Account;
use App\Models\CardPayment;
use App\Models\LedgerEntry;
use App\Models\User;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Card settlements are cash outflows that discharge obligations, not new
 * consumption. This view is never an eligible-spending input. A changed or
 * broken payment/ledger link cannot prove a historical settlement amount.
 */
final class DailyCardPaymentSettlementQuery
{
    /** @return array{settled_total:string,payment_ids:list<int>,ledger_entry_ids:list<int>,unverifiable_payment_ids:list<int>,unmatched_ledger_entry_ids:list<int>,coverage:string} */
    public function forUserOnDay(User $user, string $localDate, ?CarbonImmutable $observedAt = null): array
    {
        $day = CarbonImmutable::createFromFormat('!Y-m-d', $localDate, 'America/Sao_Paulo');
        if ($day === false || $day->format('Y-m-d') !== $localDate) {
            throw new InvalidArgumentException('Informe um dia local válido.');
        }

        $observed = ($observedAt ?? CarbonImmutable::now('UTC'))->utc();
        if ($observed->lessThan($day->startOfDay()->utc())) {
            throw new InvalidArgumentException('Não é possível consultar pagamentos antes do início do dia.');
        }

        $payments = CardPayment::query()
            ->where('user_id', $user->getKey())
            ->where('created_at', '<=', $observed)
            ->where(fn ($query) => $query->whereDate('paid_on', $localDate)
                ->orWhere('updated_at', '>', $observed))
            ->with([
                'ledgerEntry' => fn ($query) => $query->withTrashed(),
                'sourceAccount',
            ])
            ->orderBy('id')
            ->get();

        $total = BigDecimal::zero();
        $paymentIds = [];
        $ledgerIds = [];
        $unverifiable = [];
        $accountType = (new Account)->getMorphClass();

        foreach ($payments as $payment) {
            $entry = $payment->ledgerEntry;
            $account = $payment->sourceAccount;
            if ($this->recordedAfter($payment->getRawOriginal('updated_at'), $observed)
                || $entry === null
                || $account === null
                || (int) $account->user_id !== (int) $user->getKey()
                || (int) $entry->user_id !== (int) $user->getKey()
                || $entry->trashed()
                || $this->recordedAfter($entry->getRawOriginal('created_at'), $observed)
                || $this->recordedAfter($entry->getRawOriginal('updated_at'), $observed)
                || $entry->type !== LedgerEntryType::CardPayment
                || $entry->operation_id !== $payment->operation_id
                || $entry->reference_type !== $accountType
                || (int) $entry->reference_id !== (int) $account->getKey()
                || substr((string) $entry->getRawOriginal('occurred_at'), 0, 10) !== $payment->paid_on->toDateString()
                || ! BigDecimal::of($entry->amount)->isEqualTo($payment->amount)) {
                $unverifiable[] = (int) $payment->getKey();

                continue;
            }

            $total = $total->plus($payment->amount);
            $paymentIds[] = (int) $payment->getKey();
            $ledgerIds[] = (int) $entry->getKey();
        }

        // A ledger cash outflow without a matching payment row must not be
        // invisible merely because the reconciliation starts from payments.
        // Include later-edited entries across dates: their old date is unknown.
        // Ledger DATETIME is stored in application-local wall time, unlike the
        // payment timestamps above, which are compared as UTC instants.
        $cutoff = $observed->setTimezone('America/Sao_Paulo')->format('Y-m-d H:i:s');
        $unmatchedLedgerIds = LedgerEntry::withTrashed()
            ->where('user_id', $user->getKey())
            ->where('type', LedgerEntryType::CardPayment)
            ->where('created_at', '<=', $cutoff)
            ->where(fn ($query) => $query->whereDate('occurred_at', $localDate)
                ->orWhere('updated_at', '>', $cutoff)
                ->orWhere('deleted_at', '>', $cutoff))
            ->whereNotExists(fn ($query) => $query->selectRaw('1')
                ->from('card_payments')
                ->whereColumn('card_payments.user_id', 'ledger_entries.user_id')
                ->whereColumn('card_payments.ledger_entry_id', 'ledger_entries.id')
                ->where('card_payments.created_at', '<=', $observed->format('Y-m-d H:i:s')))
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        return [
            'settled_total' => (string) $total->toScale(2),
            'payment_ids' => $paymentIds,
            'ledger_entry_ids' => $ledgerIds,
            'unverifiable_payment_ids' => $unverifiable,
            'unmatched_ledger_entry_ids' => $unmatchedLedgerIds,
            'coverage' => $unverifiable === [] && $unmatchedLedgerIds === []
                ? 'card_settlement_only' : 'partial_card_settlement_unverifiable_links',
        ];
    }

    private function recordedAfter(mixed $rawTimestamp, CarbonImmutable $observed): bool
    {
        return $rawTimestamp !== null
            && CarbonImmutable::parse((string) $rawTimestamp, 'UTC')->greaterThan($observed);
    }
}
