<?php

namespace App\Queries;

use App\Models\InvestmentPosition;
use App\Models\PatrimonialAsset;
use App\Models\User;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\Schema;

class PatrimonyOverviewQuery
{
    /** @return array<string, mixed> */
    public function forUser(User $user, string $financialBalance = '0.00'): array
    {
        $financial = BigDecimal::of($financialBalance)->toScale(2, RoundingMode::Unnecessary);

        if (! Schema::hasTable('patrimonial_assets')) {
            return [
                'summary' => [
                    'asset_count' => 0,
                    'financial_balance' => (string) $financial,
                    'assets_total' => '0.00',
                    'debts_total' => '0.00',
                    'asset_equity' => '0.00',
                    'investments_total' => '0.00',
                    'investment_count' => 0,
                    'estimated_net_worth' => (string) $financial,
                    'available' => false,
                ],
                'assets' => [],
            ];
        }

        $assets = PatrimonialAsset::query()
            ->whereBelongsTo($user)
            ->orderByDesc('valued_on')
            ->orderBy('name')
            ->get();

        $gross = $assets->reduce(
            fn (BigDecimal $total, PatrimonialAsset $asset): BigDecimal => $total->plus($asset->estimated_value),
            BigDecimal::zero(),
        )->toScale(2, RoundingMode::Unnecessary);

        $debt = $assets->reduce(
            fn (BigDecimal $total, PatrimonialAsset $asset): BigDecimal => $total->plus($asset->debt_balance),
            BigDecimal::zero(),
        )->toScale(2, RoundingMode::Unnecessary);

        $equity = $gross->minus($debt)->toScale(2, RoundingMode::Unnecessary);
        $investments = Schema::hasTable('investment_positions')
            ? InvestmentPosition::query()->whereBelongsTo($user)->get()
            : collect();
        $investmentsTotal = $investments->reduce(
            fn (BigDecimal $total, InvestmentPosition $position): BigDecimal => $total->plus($position->current_value ?? $position->total_invested),
            BigDecimal::zero(),
        )->toScale(2, RoundingMode::Unnecessary);

        return [
            'summary' => [
                'asset_count' => $assets->count(),
                'financial_balance' => (string) $financial,
                'assets_total' => (string) $gross,
                'debts_total' => (string) $debt,
                'asset_equity' => (string) $equity,
                'investments_total' => (string) $investmentsTotal,
                'investment_count' => $investments->count(),
                'estimated_net_worth' => (string) $financial->plus($equity)->plus($investmentsTotal)->toScale(2, RoundingMode::Unnecessary),
                'available' => true,
            ],
            'assets' => $assets->map(fn (PatrimonialAsset $asset): array => [
                'id' => $asset->id,
                'name' => $asset->name,
                'category' => $asset->category,
                'estimated_value' => $asset->estimated_value,
                'debt_balance' => $asset->debt_balance,
                'equity' => (string) BigDecimal::of($asset->estimated_value)->minus($asset->debt_balance)->toScale(2, RoundingMode::Unnecessary),
                'valued_on' => $asset->valued_on->toDateString(),
            ])->values()->all(),
        ];
    }
}
