<?php

namespace App\Actions;

use App\Enums\AuditAction;
use App\Enums\ExpensePlanningType;
use App\Enums\LedgerEntryType;
use App\Enums\RecordStatus;
use App\Models\Account;
use App\Models\Category;
use App\Models\ExpenseRefund;
use App\Models\LedgerEntry;
use App\Models\OfxImportItem;
use App\Models\User;
use App\Support\AuditRecorder;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class CorrectManualLedgerEntry
{
    public function __construct(
        private AuditRecorder $auditRecorder,
        private RefreshCurrentInternalAlert $refreshAlert,
    ) {}

    /** @param array{category_id:int, amount:string, occurred_at:string, description?:?string, planning_type?:?string} $data */
    public function handle(User $user, int $entryId, array $data): LedgerEntry
    {
        return DB::transaction(function () use ($user, $entryId, $data): LedgerEntry {
            User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();
            $entry = LedgerEntry::query()->whereBelongsTo($user)->lockForUpdate()->findOrFail($entryId);

            $isStandalone = in_array($entry->type, [LedgerEntryType::Income, LedgerEntryType::Expense], true)
                && $entry->reference_type === (new Account)->getMorphClass()
                && $entry->reversal_of_operation_id === null
                && ! LedgerEntry::query()->whereBelongsTo($user)->where('reversal_of_operation_id', $entry->operation_id)->exists()
                && ! $entry->receiptForecastLink()->exists()
                && ! ExpenseRefund::query()->whereBelongsTo($user)->where('expense_ledger_entry_id', $entry->id)->exists()
                && ! OfxImportItem::query()->where('domain_type', $entry->getMorphClass())->where('domain_id', $entry->id)->exists();

            if (! $isStandalone) {
                throw ValidationException::withMessages(['ledger_entry' => 'Este lançamento possui vínculos ou histórico de correção. Use o fluxo específico de estorno ou desvínculo.']);
            }

            $category = Category::query()->whereBelongsTo($user)
                ->where('type', $entry->type->value)->where('status', RecordStatus::Active)
                ->whereKey($data['category_id'])->first();
            if (! $category) {
                throw ValidationException::withMessages(['category_id' => 'A categoria não está disponível para este lançamento.']);
            }

            $amount = BigDecimal::of($data['amount']);
            if ($amount->isLessThanOrEqualTo(0) || $amount->getScale() > 2) {
                throw ValidationException::withMessages(['amount' => 'Informe um valor positivo com até dois centavos.']);
            }
            $date = CarbonImmutable::createFromFormat('!Y-m-d', $data['occurred_at'], 'America/Sao_Paulo');
            if (! $date || $date->toDateString() !== $data['occurred_at'] || $date->isAfter(CarbonImmutable::now('America/Sao_Paulo')->startOfDay())) {
                throw ValidationException::withMessages(['occurred_at' => 'Informe uma data válida que não esteja no futuro.']);
            }

            $before = $entry->attributesToArray();
            $entry->fill([
                'category_id' => $category->id,
                'amount' => (string) $amount->toScale(2),
                'occurred_at' => $date,
                'description' => $data['description'] ?? null,
                'planning_type' => $entry->type === LedgerEntryType::Expense
                    ? ExpensePlanningType::from($data['planning_type']) : null,
            ]);
            if ($entry->isDirty()) {
                $entry->save();
                $this->auditRecorder->record($user, AuditAction::Updated, $entry, $before);
                $this->refreshAlert->handle($user);
            }

            return $entry;
        }, 3);
    }
}
