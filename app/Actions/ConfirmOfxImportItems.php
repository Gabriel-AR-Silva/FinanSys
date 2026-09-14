<?php

namespace App\Actions;

use App\Enums\ExpensePlanningType;
use App\Enums\LedgerEntryType;
use App\Enums\OfxClassification;
use App\Enums\OfxReviewStatus;
use App\Models\BankStatementImport;
use App\Models\LedgerEntry;
use App\Models\OfxImportItem;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Ramsey\Uuid\Uuid;

class ConfirmOfxImportItems
{
    public function __construct(private CreateManualLedgerEntry $createLedgerEntry) {}

    /**
     * @param  list<int>  $itemIds
     * @return Collection<int, OfxImportItem>
     */
    public function handle(User $user, BankStatementImport $import, array $itemIds): Collection
    {
        if ((int) $import->user_id !== (int) $user->getKey() || $itemIds === []) {
            throw ValidationException::withMessages(['item_ids' => 'Selecione ao menos um item válido desta importação.']);
        }

        $ids = collect($itemIds)->map(fn ($id): int => (int) $id)->unique()->values();

        return DB::transaction(function () use ($user, $import, $ids): Collection {
            $items = OfxImportItem::query()
                ->where('bank_statement_import_id', $import->getKey())
                ->where('user_id', $user->getKey())
                ->whereIn('id', $ids)
                ->lockForUpdate()
                ->get();

            if ($items->count() !== $ids->count()) {
                throw ValidationException::withMessages(['item_ids' => 'Há itens inválidos ou de outra importação na seleção.']);
            }

            foreach ($items as $item) {
                $this->confirmItem($user, $import, $item);
            }

            $stillPending = OfxImportItem::query()
                ->where('bank_statement_import_id', $import->getKey())
                ->where('review_status', OfxReviewStatus::PendingReview)
                ->where('classification', '!=', OfxClassification::Duplicate)
                ->exists();

            if (! $stillPending) {
                $import->forceFill(['status' => OfxReviewStatus::Confirmed])->save();
            }

            return $items->fresh();
        });
    }

    private function confirmItem(User $user, BankStatementImport $import, OfxImportItem $item): void
    {
        if ($item->review_status === OfxReviewStatus::Confirmed) {
            return;
        }

        if (! in_array($item->classification, [OfxClassification::Income, OfxClassification::Expense], true)) {
            throw ValidationException::withMessages(['item_ids' => 'Todos os itens selecionados precisam estar revisados como receita ou despesa.']);
        }

        if ($item->category_id === null) {
            throw ValidationException::withMessages(['item_ids' => 'Todos os itens selecionados precisam ter uma categoria válida.']);
        }

        $type = $item->classification === OfxClassification::Income
            ? LedgerEntryType::Income
            : LedgerEntryType::Expense;
        $planningType = $type === LedgerEntryType::Expense
            ? ExpensePlanningType::tryFrom((string) $item->planning_type)
            : null;

        if ($type === LedgerEntryType::Expense && $planningType === null) {
            throw ValidationException::withMessages(['item_ids' => 'Toda despesa selecionada precisa ter classificação de planejamento.']);
        }

        $operationId = Uuid::uuid5(Uuid::NAMESPACE_URL, 'finansys:ofx:item:'.$item->getKey())->toString();
        $entry = $this->createLedgerEntry->handle(
            user: $user,
            accountId: (int) $import->account_id,
            categoryId: (int) $item->category_id,
            type: $type,
            value: (string) $item->amount,
            occurredAt: $item->occurred_at->setTimezone('America/Sao_Paulo')->toDateString(),
            description: mb_substr((string) $item->description, 0, 255),
            operationId: $operationId,
            planningType: $planningType,
        );

        $item->forceFill([
            'review_status' => OfxReviewStatus::Confirmed,
            'domain_type' => LedgerEntry::class,
            'domain_id' => $entry->getKey(),
        ])->save();
    }
}
