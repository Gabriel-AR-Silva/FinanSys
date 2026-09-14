<?php

namespace App\Actions;

use App\Enums\AuditAction;
use App\Enums\CategoryType;
use App\Enums\RecordStatus;
use App\Models\Category;
use App\Models\MonthlyFinancialSetting;
use App\Models\User;
use App\Support\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveFinancialSettings
{
    public function __construct(private AuditRecorder $auditRecorder) {}

    /** @param array{month:string,version:int,protection_type:string,protection_value:string,essentials:array<int,array{category_id:int,amount:string}>} $data */
    public function handle(User $user, array $data): MonthlyFinancialSetting
    {
        return DB::transaction(function () use ($user, $data): MonthlyFinancialSetting {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $settings = MonthlyFinancialSetting::query()->whereBelongsTo($user)->where('month', $data['month'])->first();
            if (($settings?->version ?? 0) !== (int) $data['version']) {
                throw ValidationException::withMessages(['version' => 'Esta configuração mudou em outra aba. Recarregue antes de salvar.']);
            }
            $existing = $settings?->essentials()->withTrashed()->get()->keyBy('category_id') ?? collect();
            $categories = Category::query()->whereBelongsTo($user)
                ->whereIn('id', array_column($data['essentials'], 'category_id'))->lockForUpdate()->get()->keyBy('id');
            foreach ($data['essentials'] as $index => $item) {
                $category = $categories->get($item['category_id']);
                $budget = $existing->get($item['category_id']);
                if (! $category || $category->type !== CategoryType::Expense
                    || ($category->status !== RecordStatus::Active && (! $budget || $budget->trashed()))) {
                    throw ValidationException::withMessages(["essentials.$index.category_id" => 'Escolha uma categoria de despesa disponível.']);
                }
            }
            $before = $settings?->attributesToArray();
            $settings ??= new MonthlyFinancialSetting(['user_id' => $user->id, 'month' => $data['month']]);
            $settings->fill([
                'protection_type' => $data['protection_type'], 'protection_value' => $data['protection_value'],
                'version' => ($settings->version ?? 0) + 1,
            ])->save();
            $this->auditRecorder->record($user, $before ? AuditAction::Updated : AuditAction::Created, $settings, $before);
            $selected = [];
            foreach ($data['essentials'] as $item) {
                $selected[] = (int) $item['category_id'];
                $budget = $existing->get($item['category_id']);
                if (! $budget) {
                    $budget = $settings->essentials()->create($item + ['user_id' => $user->id]);
                    $this->auditRecorder->record($user, AuditAction::Created, $budget);

                    continue;
                }
                $beforeBudget = $budget->attributesToArray();
                $wasDeleted = $budget->trashed();
                $budget->amount = $item['amount'];
                $budget->deleted_at = null;
                if ($budget->isDirty()) {
                    $budget->save();
                    $this->auditRecorder->record($user, $wasDeleted ? AuditAction::Restored : AuditAction::Updated, $budget, $beforeBudget);
                }
            }
            foreach ($existing as $budget) {
                if (! $budget->trashed() && ! in_array((int) $budget->category_id, $selected, true)) {
                    $beforeBudget = $budget->attributesToArray();
                    $budget->delete();
                    $this->auditRecorder->record($user, AuditAction::Deleted, $budget, $beforeBudget);
                }
            }

            return $settings;
        });
    }
}
