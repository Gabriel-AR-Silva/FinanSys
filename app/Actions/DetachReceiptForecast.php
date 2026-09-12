<?php

namespace App\Actions;

use App\Enums\AuditAction;
use App\Enums\ReceiptForecastUnlinkReason;
use App\Models\LedgerEntry;
use App\Models\ReceiptForecast;
use App\Models\ReceiptForecastLink;
use App\Models\User;
use App\Support\AuditRecorder;

class DetachReceiptForecast
{
    public function __construct(
        private AuditRecorder $auditRecorder,
        private RecalculateReceiptForecast $recalculate,
    ) {}

    public function handle(User $user, LedgerEntry $entry, ReceiptForecastUnlinkReason $reason): void
    {
        $link = ReceiptForecastLink::query()
            ->whereBelongsTo($user)
            ->whereBelongsTo($entry, 'ledgerEntry')
            ->whereNull('unlinked_at')
            ->lockForUpdate()
            ->first();
        if (! $link) {
            return;
        }

        $forecast = ReceiptForecast::query()->whereBelongsTo($user)->whereKey($link->receipt_forecast_id)->lockForUpdate()->firstOrFail();
        $before = $link->attributesToArray();
        $link->update([
            'unlinked_at' => now(),
            'unlink_reason' => $reason,
            'version' => $link->version + 1,
        ]);
        $this->auditRecorder->record($user, AuditAction::Unlinked, $link, $before);
        $this->recalculate->handle($user, $forecast);
    }
}
