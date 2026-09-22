<?php

namespace App\Queries;

use App\Actions\RecalculateReceiptForecast;
use App\Enums\ReceiptForecastStatus;
use App\Models\ReceiptForecast;
use App\Models\ReceiptForecastLink;
use App\Models\User;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use UnexpectedValueException;

/**
 * Read-only, current-state residual of expected receipts in a local month.
 * This is NOT received income, cash available, or a historical as-of view.
 */
final class MonthlyReceiptForecastResidualQuery
{
    public function __construct(private RecalculateReceiptForecast $recalculateForecast) {}

    /** @return array{month:string,pending_total:string,forecasts:list<array{id:int,expected_on:string,received:string,pending:string}>,coverage:string} */
    public function forUserInMonth(User $user, string $month): array
    {
        $start = CarbonImmutable::createFromFormat('!Y-m', $month, 'America/Sao_Paulo');
        if ($start === false || $start->format('Y-m') !== $month) {
            throw new InvalidArgumentException('Informe um mês válido no formato AAAA-MM.');
        }

        // Half-open bounds include the final day even when DATE casts are
        // persisted as DATETIME strings with a midnight time component.
        $forecasts = ReceiptForecast::query()
            ->whereBelongsTo($user)
            ->where('status', '!=', ReceiptForecastStatus::Cancelled)
            ->where('expected_on', '>=', $start->toDateString())
            ->where('expected_on', '<', $start->addMonth()->toDateString())
            ->orderBy('expected_on')
            ->orderBy('id')
            ->get();

        $pendingTotal = BigDecimal::zero();
        $items = [];

        foreach ($forecasts as $forecast) {
            // Older or manually imported references might bypass the V1 action's
            // ownership check. Never count a foreign or missing ledger entry.
            $invalidLink = ReceiptForecastLink::query()
                ->whereBelongsTo($user)
                ->whereBelongsTo($forecast, 'forecast')
                ->whereNull('unlinked_at')
                ->whereDoesntHave('ledgerEntry', fn ($query) => $query->whereBelongsTo($user))
                ->exists();
            if ($invalidLink) {
                throw new UnexpectedValueException('Inconsistent receipt forecast linkage.');
            }

            $progress = $this->recalculateForecast->calculate($user, $forecast);
            $pendingTotal = $pendingTotal->plus($progress['pending']);
            $items[] = [
                'id' => (int) $forecast->getKey(),
                'expected_on' => $forecast->expected_on->toDateString(),
                'received' => $progress['received'],
                'pending' => $progress['pending'],
            ];
        }

        return [
            'month' => $month,
            'pending_total' => (string) $pendingTotal->toScale(2),
            'forecasts' => $items,
            'coverage' => 'current_receipt_forecasts_only',
        ];
    }
}
