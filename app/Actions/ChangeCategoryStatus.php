<?php

namespace App\Actions;

use App\Enums\AuditAction;
use App\Enums\RecordStatus;
use App\Models\Category;
use App\Models\User;
use App\Support\AuditRecorder;
use Illuminate\Support\Facades\DB;

class ChangeCategoryStatus
{
    public function __construct(private AuditRecorder $auditRecorder) {}

    public function handle(User $user, int $categoryId, RecordStatus $status): Category
    {
        return DB::transaction(function () use ($user, $categoryId, $status): Category {
            $category = Category::query()->whereBelongsTo($user)->lockForUpdate()->findOrFail($categoryId);
            $before = $category->attributesToArray();
            $category->update(['status' => $status]);
            $this->auditRecorder->record($user, AuditAction::Updated, $category, $before);

            return $category;
        });
    }
}
