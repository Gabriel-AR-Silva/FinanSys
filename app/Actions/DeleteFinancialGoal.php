<?php

namespace App\Actions;

use App\Enums\AuditAction;
use App\Models\FinancialGoal;
use App\Models\User;
use App\Support\AuditRecorder;
use Illuminate\Support\Facades\DB;

class DeleteFinancialGoal
{
    public function __construct(private AuditRecorder $auditRecorder) {}

    public function handle(User $user, int $goalId): void
    {
        DB::transaction(function () use ($user, $goalId): void {
            $goal = FinancialGoal::query()
                ->whereBelongsTo($user)
                ->lockForUpdate()
                ->findOrFail($goalId);

            $before = $goal->attributesToArray();
            $goal->pocket_id = null;
            $goal->saveQuietly();
            $goal->delete();
            $this->auditRecorder->record($user, AuditAction::Deleted, $goal, $before);
        }, 3);
    }
}
