<?php

namespace App\Actions;

use App\Enums\AuditAction;
use App\Enums\RecordStatus;
use App\Models\Account;
use App\Models\Pocket;
use App\Models\User;
use App\Support\AuditRecorder;
use Illuminate\Support\Facades\DB;

class RenamePocket
{
    /**
     * Create a new class instance.
     */
    public function __construct(private AuditRecorder $auditRecorder) {}

    public function handle(User $user, int $pocketId, string $name): Pocket
    {
        return DB::transaction(function () use ($user, $pocketId, $name): Pocket {
            $pocketAccountId = Pocket::query()->whereBelongsTo($user)->whereKey($pocketId)->value('account_id');
            $account = Account::query()->whereBelongsTo($user)->where('status', RecordStatus::Active)->lockForUpdate()->findOrFail($pocketAccountId);
            $pocket = Pocket::query()->whereBelongsTo($user)->whereBelongsTo($account)->lockForUpdate()->findOrFail($pocketId);
            $before = $pocket->attributesToArray();
            $pocket->update(['name' => $name]);
            $this->auditRecorder->record($user, AuditAction::Updated, $pocket, $before);

            return $pocket;
        });
    }
}
