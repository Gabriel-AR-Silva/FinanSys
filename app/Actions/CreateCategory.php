<?php

namespace App\Actions;

use App\Enums\AuditAction;
use App\Enums\CategoryType;
use App\Enums\RecordStatus;
use App\Models\Category;
use App\Models\User;
use App\Support\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateCategory
{
    public function __construct(private AuditRecorder $auditRecorder) {}

    public function handle(User $user, string $name, CategoryType $type): Category
    {
        $name = Str::of($name)->trim()->toString();
        $this->ensureUniqueName($user, $type, $name);

        return DB::transaction(function () use ($user, $name, $type): Category {
            $category = $user->categories()->create([
                'name' => $name,
                'type' => $type,
                'status' => RecordStatus::Active,
            ]);
            $this->auditRecorder->record($user, AuditAction::Created, $category);

            return $category;
        });
    }

    private function ensureUniqueName(User $user, CategoryType $type, string $name): void
    {
        $exists = Category::query()->whereBelongsTo($user)
            ->where('type', $type)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages(['name' => 'Já existe uma categoria com este nome para este tipo.']);
        }
    }
}
