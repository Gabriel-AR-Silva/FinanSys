<?php

namespace App\Actions;

use App\Enums\AuditAction;
use App\Enums\LedgerEntryType;
use App\Enums\ReceiptForecastStatus;
use App\Models\LedgerEntry;
use App\Models\ReceiptForecast;
use App\Models\ReceiptForecastLink;
use App\Models\User;
use App\Support\AuditRecorder;
use App\Support\FinancialPlanningMath;
use Brick\Math\BigDecimal;

class RecalculateReceiptForecast
{
    public function __construct(
        private AuditRecorder $auditRecorder,
        private FinancialPlanningMath $math,
    ) {}

    /** @return array{received:string,pending:string,excess:string,fulfilled:bool} */
    public function calculate(User $user, ReceiptForecast $forecast): array
    {
        $links = ReceiptForecastLink::query()
            ->whereBelongsTo($user)
            ->whereBelongsTo($forecast, 'forecast')
            ->whereNull('unlinked_at')
            ->with('ledgerEntry')
            ->get();
        $entries = $links->pluck('ledgerEntry')->filter(fn (?LedgerEntry $entry): bool => $entry instanceof LedgerEntry
            && $entry->type === LedgerEntryType::Income
            && $entry->reversal_of_operation_id === null);
        $reversedOperations = LedgerEntry::query()
            ->whereBelongsTo($user)
            ->whereIn('reversal_of_operation_id', $entries->pluck('operation_id'))
            ->pluck('reversal_of_operation_id')
            ->all();
        $received = $entries
            ->reject(fn (LedgerEntry $entry): bool => in_array($entry->operation_id, $reversedOperations, true))
            ->reduce(
                fn (BigDecimal $total, LedgerEntry $entry): BigDecimal => $total->plus($entry->amount),
                BigDecimal::zero(),
            );

        return $this->math->receiptProgress($forecast->amount, (string) $received);
    }

    /** @return array{received:string,pending:string,excess:string,fulfilled:bool} */
    public function handle(User $user, ReceiptForecast $forecast): array
    {
        $progress = $this->calculate($user, $forecast);
        $before = $forecast->attributesToArray();
        if ($forecast->status !== ReceiptForecastStatus::Cancelled) {
            $forecast->status = $progress['fulfilled'] ? ReceiptForecastStatus::Fulfilled : ReceiptForecastStatus::Expected;
        }
        $forecast->version++;
        $forecast->save();
        $this->auditRecorder->record($user, AuditAction::Updated, $forecast, $before);

        return $progress;
    }
}
