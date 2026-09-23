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

            $goal->delete();
            $this->auditRecorder->record($user, AuditAction::Deleted, $goal);
        }, 3);
    }
}
