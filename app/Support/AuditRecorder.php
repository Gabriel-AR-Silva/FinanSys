<?php

namespace App\Support;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\UnauthorizedException;

class AuditRecorder
{
    public function record(User $actor, AuditAction $action, Model $model, ?array $before = null): AuditLog
    {
        if ((int) $model->getAttribute('user_id') !== (int) $actor->getKey()) {
            throw new UnauthorizedException('O ator não possui o registro auditado.');
        }

        return AuditLog::query()->create([
            'user_id' => $actor->getKey(),
            'action' => $action->value,
            'auditable_type' => $model->getMorphClass(),
            'auditable_id' => $model->getKey(),
            'before' => $before,
            'after' => $model->attributesToArray(),
            'created_at' => now(),
        ]);
    }
}
