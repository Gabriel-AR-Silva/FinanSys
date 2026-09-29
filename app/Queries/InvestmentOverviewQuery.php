<?php

namespace App\Queries;

use App\Models\InvestmentPosition;
use App\Models\User;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\Schema;

class InvestmentOverviewQuery
{
    /** @return array<string, mixed> */
    public function forUser(User $user): array
    {
        if (! Schema::hasTable('investment_positions')) {
            return [
                'summary' => [
                    'position_count' => 0,
                    'total_invested' => '0.00',
                    'current_value' => '0.00',
                    'unrealized_result' => '0.00',
                    'available' => false,
                ],
                'positions' => [],
            ];
        }

        $positions = InvestmentPosition::query()
            ->whereBelongsTo($user)
            ->orderBy('name')
            ->get();

        $invested = $positions->reduce(
            fn (BigDecimal $total, InvestmentPosition $position): BigDecimal => $total->plus($position->total_invested),
            BigDecimal::zero(),
        )->toScale(2, RoundingMode::Unnecessary);

        $current = $positions->reduce(
            fn (BigDecimal $total, InvestmentPosition $position): BigDecimal => $total->plus($position->current_value ?? $position->total_invested),
            BigDecimal::zero(),
        )->toScale(2, RoundingMode::Unnecessary);

        return [
            'summary' => [
                'position_count' => $positions->count(),
                'total_invested' => (string) $invested,
                'current_value' => (string) $current,
                'unrealized_result' => (string) $current->minus($invested)->toScale(2, RoundingMode::Unnecessary),
                'available' => true,
            ],
            'positions' => $positions->map(fn (InvestmentPosition $position): array => [
                'id' => $position->id,
                'asset_type' => $position->asset_type,
                'ticker' => $position->ticker,
                'name' => $position->name,
                'quantity' => $position->quantity,
                'average_cost' => $position->average_cost,
                'total_invested' => $position->total_invested,
                'current_value' => $position->current_value,
                'valuation_source' => $position->valuation_source,
                'valued_on' => $position->valued_on?->toDateString(),
            ])->values()->all(),
        ];
    }
}
