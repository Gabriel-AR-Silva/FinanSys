<?php

namespace App\Actions;

use App\Enums\AuditAction;
use App\Models\PatrimonialAsset;
use App\Models\User;
use App\Support\AuditRecorder;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;

class UpdatePatrimonialAsset
{
    public function __construct(private AuditRecorder $auditRecorder) {}

    /** @param array<string, mixed> $data */
    public function handle(User $user, int $assetId, array $data): PatrimonialAsset
    {
        return DB::transaction(function () use ($user, $assetId, $data): PatrimonialAsset {
            $asset = PatrimonialAsset::query()->whereBelongsTo($user)->whereKey($assetId)->lockForUpdate()->firstOrFail();
            $before = $asset->attributesToArray();
            $asset->update([
                'name' => trim((string) $data['name']),
                'category' => filled($data['category'] ?? null) ? trim((string) $data['category']) : null,
                'estimated_value' => (string) BigDecimal::of((string) $data['estimated_value'])->toScale(2, RoundingMode::Unnecessary),
                'debt_balance' => (string) BigDecimal::of((string) ($data['debt_balance'] ?? '0'))->toScale(2, RoundingMode::Unnecessary),
                'valued_on' => (string) $data['valued_on'],
            ]);
            $this->auditRecorder->record($user, AuditAction::Updated, $asset, $before);

            return $asset->refresh();
        }, 3);
    }
}
