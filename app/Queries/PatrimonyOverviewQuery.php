<?php

namespace App\Queries;

use App\Enums\CardInstallmentStatus;
use App\Models\CardCharge;
use App\Models\CardInstallment;
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
        $cardLiability = $this->cardLiability($user);

        if (! Schema::hasTable('patrimonial_assets')) {
            return [
                'summary' => [
                    'asset_count' => 0,
                    'financial_balance' => (string) $financial,
                    'assets_total' => '0.00',
                    'debts_total' => '0.00',
                    'card_liability' => (string) $cardLiability,
                    'asset_equity' => '0.00',
                    'estimated_net_worth' => (string) $financial->minus($cardLiability),
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

        return [
            'summary' => [
                'asset_count' => $assets->count(),
                'financial_balance' => (string) $financial,
                'assets_total' => (string) $gross,
                'debts_total' => (string) $debt,
                'card_liability' => (string) $cardLiability,
                'asset_equity' => (string) $equity,
                'estimated_net_worth' => (string) $financial->plus($equity)->minus($cardLiability)->toScale(2, RoundingMode::Unnecessary),
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
    private function cardLiability(User $user): BigDecimal
    {
        $installments = CardInstallment::query()->whereBelongsTo($user)
            ->where('status', CardInstallmentStatus::Pending)
            ->get(['gross_amount', 'paid_amount'])
            ->reduce(fn (BigDecimal $total, CardInstallment $installment): BigDecimal => $total->plus(BigDecimal::of($installment->gross_amount)->minus($installment->paid_amount)), BigDecimal::zero());
        $charges = CardCharge::query()->whereBelongsTo($user)
            ->where('status', CardInstallmentStatus::Pending)
            ->get(['amount', 'paid_amount'])
            ->reduce(fn (BigDecimal $total, CardCharge $charge): BigDecimal => $total->plus(BigDecimal::of($charge->amount)->minus($charge->paid_amount)), BigDecimal::zero());

        return $installments->plus($charges)->toScale(2, RoundingMode::Unnecessary);
    }
}
