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
use Illuminate\Validation\ValidationException;

class RecordInvestmentMovement
{
    public function __construct(private AuditRecorder $auditRecorder) {}

    /** @param array<string, mixed> $data */
    public function handle(User $user, int $positionId, array $data): InvestmentMovement
    {
        return DB::transaction(function () use ($user, $positionId, $data): InvestmentMovement {
            $position = InvestmentPosition::query()
                ->whereBelongsTo($user)
                ->whereKey($positionId)
                ->lockForUpdate()
                ->firstOrFail();

            $type = (string) $data['type'];
            $quantity = filled($data['quantity'] ?? null)
                ? BigDecimal::of((string) $data['quantity'])->toScale(8, RoundingMode::Unnecessary)
                : null;
            $unitPrice = filled($data['unit_price'] ?? null)
                ? BigDecimal::of((string) $data['unit_price'])->toScale(4, RoundingMode::Unnecessary)
                : null;
            $fees = BigDecimal::of((string) ($data['fees'] ?? '0'))->toScale(2, RoundingMode::Unnecessary);

            if (in_array($type, ['buy', 'sell'], true) && ($quantity === null || $unitPrice === null)) {
                throw ValidationException::withMessages(['quantity' => 'Informe quantidade e preço unitário para compra ou venda.']);
            }

            $amount = filled($data['amount'] ?? null)
                ? BigDecimal::of((string) $data['amount'])->toScale(2, RoundingMode::Unnecessary)
                : (($quantity !== null && $unitPrice !== null)
                    ? $quantity->multipliedBy($unitPrice)->toScale(2, RoundingMode::HalfUp)
                    : BigDecimal::zero()->toScale(2));

            $currentQuantity = BigDecimal::of($position->quantity);
            $currentInvested = BigDecimal::of($position->total_invested);
            $averageCost = BigDecimal::of($position->average_cost);

            if ($type === 'buy') {
                $newQuantity = $currentQuantity->plus($quantity);
                $newInvested = $currentInvested->plus($amount)->plus($fees);
                $position->forceFill([
                    'quantity' => (string) $newQuantity,
                    'total_invested' => (string) $newInvested,
                    'average_cost' => (string) $newInvested->dividedBy($newQuantity, 4, RoundingMode::HalfUp),
                    'current_value' => null,
                    'valuation_source' => null,
                    'valued_on' => null,
                ])->save();
            } elseif ($type === 'sell') {
                if ($quantity->compareTo($currentQuantity) > 0) {
                    throw ValidationException::withMessages(['quantity' => 'A venda não pode exceder a quantidade atual da posição.']);
                }

                $newQuantity = $currentQuantity->minus($quantity);
                $costRemoved = $averageCost->multipliedBy($quantity)->toScale(2, RoundingMode::HalfUp);
                $newInvested = $currentInvested->minus($costRemoved);
                if ($newInvested->isNegative()) {
                    $newInvested = BigDecimal::zero()->toScale(2);
                }

                $position->forceFill([
                    'quantity' => (string) $newQuantity,
                    'total_invested' => (string) $newInvested,
                    'average_cost' => $newQuantity->isZero() ? '0.0000' : (string) $averageCost,
                    'current_value' => null,
                    'valuation_source' => null,
                    'valued_on' => null,
                ])->save();
            } elseif ($type === 'fee') {
                $newInvested = $currentInvested->plus($amount);
                $position->forceFill([
                    'total_invested' => (string) $newInvested,
                    'average_cost' => $currentQuantity->isZero()
                        ? '0.0000'
                        : (string) $newInvested->dividedBy($currentQuantity, 4, RoundingMode::HalfUp),
                ])->save();
            }

            $movement = InvestmentMovement::query()->create([
                'user_id' => $user->id,
                'investment_position_id' => $position->id,
                'type' => $type,
                'occurred_on' => (string) $data['occurred_on'],
                'quantity' => $quantity === null ? null : (string) $quantity,
                'unit_price' => $unitPrice === null ? null : (string) $unitPrice,
                'amount' => (string) $amount,
                'fees' => (string) $fees,
                'notes' => filled($data['notes'] ?? null) ? trim((string) $data['notes']) : null,
            ]);

            $this->auditRecorder->record($user, AuditAction::Updated, $position);

            return $movement;
        }, 3);
    }
}
