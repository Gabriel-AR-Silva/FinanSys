<?php

namespace App\Actions;

use App\Enums\AuditAction;
use App\Models\PatrimonialAsset;
use App\Models\User;
use App\Support\AuditRecorder;
use Illuminate\Support\Facades\DB;

class DeletePatrimonialAsset
{
    public function __construct(private AuditRecorder $auditRecorder) {}

    public function handle(User $user, int $assetId): void
    {
        DB::transaction(function () use ($user, $assetId): void {
            $asset = PatrimonialAsset::query()->whereBelongsTo($user)->whereKey($assetId)->lockForUpdate()->firstOrFail();
            $this->auditRecorder->record($user, AuditAction::Deleted, $asset, $asset->attributesToArray());
            $asset->delete();
        }, 3);
    }
}
