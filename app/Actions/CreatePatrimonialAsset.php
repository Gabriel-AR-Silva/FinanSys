<?php

namespace App\Actions;

use App\Enums\AuditAction;
use App\Models\PatrimonialAsset;
use App\Models\User;
use App\Support\AuditRecorder;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreatePatrimonialAsset
{
    public function __construct(private AuditRecorder $auditRecorder) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(User $user, array $data): PatrimonialAsset
    {
        $payload = $this->normalize($data);

        return DB::transaction(function () use ($user, $payload): PatrimonialAsset {
            $asset = PatrimonialAsset::query()->create(['user_id' => $user->getKey(), ...$payload]);
            $this->auditRecorder->record($user, AuditAction::Created, $asset);

            return $asset;
        }, 3);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, string|null>
     */
    private function normalize(array $data): array
    {
        $value = BigDecimal::of((string) $data['estimated_value'])->toScale(2, RoundingMode::Unnecessary);
        $debt = BigDecimal::of((string) ($data['debt_balance'] ?? '0'))->toScale(2, RoundingMode::Unnecessary);

        if ($value->compareTo('0') <= 0 || $debt->compareTo('0') < 0) {
            throw ValidationException::withMessages(['estimated_value' => 'Revise os valores patrimoniais informados.']);
        }

        return [
            'name' => trim((string) $data['name']),
            'category' => filled($data['category'] ?? null) ? trim((string) $data['category']) : null,
            'estimated_value' => (string) $value,
            'debt_balance' => (string) $debt,
            'valued_on' => (string) $data['valued_on'],
        ];
    }
}
