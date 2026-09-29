<?php

namespace App\Actions;

use App\Enums\AuditAction;
use App\Models\InvestmentMovement;
use App\Models\InvestmentPosition;
use App\Models\User;
use App\Support\AuditRecorder;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;

class CreateInvestmentPosition
{
    public function __construct(private AuditRecorder $auditRecorder) {}

    /** @param array<string, mixed> $data */
    public function handle(User $user, array $data): InvestmentPosition
    {
        $quantity = BigDecimal::of((string) $data['quantity'])->toScale(8, RoundingMode::Unnecessary);
        $averageCost = BigDecimal::of((string) $data['average_cost'])->toScale(4, RoundingMode::Unnecessary);
        $totalInvested = $quantity->multipliedBy($averageCost)->toScale(2, RoundingMode::HalfUp);

        return DB::transaction(function () use ($user, $data, $quantity, $averageCost, $totalInvested): InvestmentPosition {
            $position = InvestmentPosition::query()->create([
                'user_id' => $user->id,
                'asset_type' => trim((string) $data['asset_type']),
                'ticker' => filled($data['ticker'] ?? null) ? strtoupper(trim((string) $data['ticker'])) : null,
                'name' => trim((string) $data['name']),
                'purchased_on' => (string) $data['purchased_on'],
                'quantity' => (string) $quantity,
                'average_cost' => (string) $averageCost,
                'total_invested' => (string) $totalInvested,
                'current_value' => filled($data['current_value'] ?? null)
                    ? (string) BigDecimal::of((string) $data['current_value'])->toScale(2, RoundingMode::Unnecessary)
                    : null,
                'valuation_source' => filled($data['current_value'] ?? null) ? 'manual' : null,
                'valued_on' => $data['valued_on'] ?? null,
            ]);

            InvestmentMovement::query()->create([
                'user_id' => $user->id,
                'investment_position_id' => $position->id,
                'type' => 'buy',
                'occurred_on' => (string) $data['purchased_on'],
                'quantity' => (string) $quantity,
                'unit_price' => (string) $averageCost,
                'amount' => (string) $totalInvested,
                'fees' => '0.00',
                'notes' => 'Posição inicial',
            ]);
            $this->auditRecorder->record($user, AuditAction::Created, $position);

            return $position;
        }, 3);
    }
}
