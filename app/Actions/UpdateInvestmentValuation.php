<?php

namespace App\Actions;

use App\Enums\AuditAction;
use App\Models\InvestmentPosition;
use App\Models\User;
use App\Support\AuditRecorder;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

class UpdateInvestmentValuation
{
    public function __construct(private AuditRecorder $auditRecorder) {}

    public function handle(User $user, int $positionId, string $currentValue, string $valuedOn): InvestmentPosition
    {
        $position = InvestmentPosition::query()->whereBelongsTo($user)->whereKey($positionId)->firstOrFail();
        $position->update([
            'current_value' => (string) BigDecimal::of($currentValue)->toScale(2, RoundingMode::Unnecessary),
            'valuation_source' => 'manual',
            'valued_on' => $valuedOn,
        ]);
        $this->auditRecorder->record($user, AuditAction::Updated, $position);

        return $position;
    }
}
