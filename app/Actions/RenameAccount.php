<?php

namespace App\Actions;

use App\Enums\AuditAction;
use App\Models\Account;
use App\Models\User;
use App\Support\AuditRecorder;
use Illuminate\Support\Facades\DB;

class RenameAccount
{
    public function __construct(private AuditRecorder $auditRecorder) {}

    public function handle(User $user, int $accountId, string $name): Account
    {
        return DB::transaction(function () use ($user, $accountId, $name): Account {
            $account = Account::query()
                ->whereBelongsTo($user)
                ->lockForUpdate()
                ->findOrFail($accountId);
            $before = $account->attributesToArray();

            $account->update(['name' => $name]);
            $this->auditRecorder->record($user, AuditAction::Updated, $account, $before);

            return $account;
        });
    }
}
