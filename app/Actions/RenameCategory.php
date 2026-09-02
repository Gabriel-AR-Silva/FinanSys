<?php

namespace App\Actions;

use App\Enums\AuditAction;
use App\Models\Category;
use App\Models\User;
use App\Support\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RenameCategory
{
    public function __construct(private AuditRecorder $auditRecorder) {}

    public function handle(User $user, int $categoryId, string $name): Category
    {
        return DB::transaction(function () use ($user, $categoryId, $name): Category {
            $category = Category::query()->whereBelongsTo($user)->lockForUpdate()->findOrFail($categoryId);
            $name = Str::of($name)->trim()->toString();
            $exists = Category::query()->whereBelongsTo($user)
                ->where('type', $category->type)
                ->whereKeyNot($category)
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
                ->exists();

            if ($exists) {
                throw ValidationException::withMessages(['name' => 'Já existe uma categoria com este nome para este tipo.']);
            }

            $before = $category->attributesToArray();
            $category->update(['name' => $name]);
            $this->auditRecorder->record($user, AuditAction::Updated, $category, $before);

            return $category;
        });
    }
}
