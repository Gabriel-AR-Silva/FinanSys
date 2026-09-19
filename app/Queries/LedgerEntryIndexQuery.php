<?php

namespace App\Queries;

use App\Enums\LedgerEntryType;
use App\Enums\RecordStatus;
use App\Models\Account;
use App\Models\ExpenseCommitmentPayment;
use App\Models\LedgerEntry;
use App\Models\OfxImportItem;
use App\Models\Pocket;
use App\Models\User;
use Brick\Math\BigDecimal;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class LedgerEntryIndexQuery
{
    /** @param array{type?: string, account_id?: int|string, category_id?: int|string, period?: string} $filters */
    public function paginateForUser(User $user, array $filters): LengthAwarePaginator
    {
        $reversedOperationIds = LedgerEntry::query()
            ->whereBelongsTo($user)
            ->whereNotNull('reversal_of_operation_id')
            ->pluck('reversal_of_operation_id')
            ->flip();

        return $this->filteredQuery($user, $filters)
            ->with(['reference', 'category:id,name', 'expenseRefunds.refundEntry'])
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (LedgerEntry $entry): array => $this->serialize($entry, $reversedOperationIds));
    }

    /** @return Collection<int, array<string, mixed>> */
    public function restorableForUser(User $user): Collection
    {
        $threshold = now()->subDays((int) config('finansys.soft_delete_retention_days'));

        return LedgerEntry::onlyTrashed()
            ->whereBelongsTo($user)
            ->whereNull('deletion_batch_id')
            ->whereIn('type', [LedgerEntryType::Income, LedgerEntryType::Expense])
            ->where('reference_type', (new Account)->getMorphClass())
            ->where('deleted_at', '>=', $threshold->format('Y-m-d H:i:s.u'))
            ->whereHasMorph('reference', [Account::class], fn (Builder $query) => $query
                ->whereBelongsTo($user)
                ->where('status', RecordStatus::Active))
            ->with(['reference' => fn ($query) => $query->select('id', 'name'), 'category:id,name'])
            ->orderByDesc('deleted_at')
            ->get()
            ->map(fn (LedgerEntry $entry): array => $this->serialize($entry) + [
                'restorable_until' => $entry->deleted_at->addDays((int) config('finansys.soft_delete_retention_days'))->toIso8601String(),
            ]);
    }

    /** @param array{type?: string, account_id?: int|string, category_id?: int|string, period?: string} $filters */
    private function filteredQuery(User $user, array $filters): Builder
    {
        return LedgerEntry::query()
            ->whereBelongsTo($user)
            ->when(($filters['type'] ?? 'all') !== 'all', fn (Builder $query) => $query->where('type', $filters['type']))
            ->when(($filters['period'] ?? 'all') !== 'all', fn (Builder $query) => $query->where('occurred_at', '>=', now()->subDays(((int) $filters['period']) - 1)->startOfDay()))
            ->when(isset($filters['category_id']), fn (Builder $query) => $query->where('category_id', (int) $filters['category_id']))
            ->when(isset($filters['account_id']), function (Builder $query) use ($filters): void {
                $accountId = (int) $filters['account_id'];
                $query->where(function (Builder $references) use ($accountId): void {
                    $references->where(fn (Builder $account) => $account->where('reference_type', (new Account)->getMorphClass())->where('reference_id', $accountId))
                        ->orWhere(fn (Builder $pocket) => $pocket->where('reference_type', (new Pocket)->getMorphClass())->whereIn('reference_id', Pocket::query()->select('id')->where('account_id', $accountId)));
                });
            });
    }

    /** @return array<string, mixed> */
    private function serialize(LedgerEntry $entry, ?Collection $reversedOperationIds = null): array
    {
        $isCommitmentPayment = ExpenseCommitmentPayment::query()->where('user_id', $entry->user_id)->where('ledger_entry_id', $entry->id)->exists();

        return [
            'id' => $entry->id,
            'type' => $entry->type->value,
            'planning_type' => $entry->planning_type?->value,
            'amount' => $entry->amount,
            'description' => $entry->description,
            'category' => $entry->category === null ? null : ['id' => $entry->category->id, 'name' => $entry->category->name],
            'occurred_at' => $entry->occurred_at->toDateString(),
            'reference' => ['type' => $entry->reference_type, 'name' => $entry->reference?->name],
            'can_correct' => ! $isCommitmentPayment
                && $entry->reference_type === (new Account)->getMorphClass()
                && in_array($entry->type, [LedgerEntryType::Income, LedgerEntryType::Expense], true)
                && $entry->reversal_of_operation_id === null
                && ! ($reversedOperationIds?->has($entry->operation_id) ?? false)
                && ! $entry->receiptForecastLink()->exists()
                && $entry->expenseRefunds->isEmpty()
                && ! OfxImportItem::query()->where('domain_type', $entry->getMorphClass())->where('domain_id', $entry->id)->exists(),
            'can_delete' => ! $isCommitmentPayment
                && $entry->reversal_of_operation_id === null
                && ! ($reversedOperationIds?->has($entry->operation_id) ?? false)
                && in_array($entry->type, [LedgerEntryType::Income, LedgerEntryType::Expense], true),
            'is_reversal' => $entry->reversal_of_operation_id !== null,
            'can_reverse' => ! $isCommitmentPayment
                && $entry->reversal_of_operation_id === null
                && ! ($reversedOperationIds?->has($entry->operation_id) ?? false)
                && in_array($entry->type, [LedgerEntryType::Income, LedgerEntryType::Expense, LedgerEntryType::Refund, LedgerEntryType::TransferOut], true),
            'can_refund' => ! $isCommitmentPayment
                && $entry->type === LedgerEntryType::Expense
                && $entry->reversal_of_operation_id === null
                && ! ($reversedOperationIds?->has($entry->operation_id) ?? false)
                && $this->refundableAmount($entry, $reversedOperationIds) !== '0.00',
            'refundable_amount' => $this->refundableAmount($entry, $reversedOperationIds),
        ];
    }

    private function refundableAmount(LedgerEntry $entry, ?Collection $reversedOperationIds): string
    {
        if ($entry->type !== LedgerEntryType::Expense) {
            return '0.00';
        }

        $refunded = $entry->expenseRefunds->pluck('refundEntry')->filter()
            ->reject(fn (LedgerEntry $refund): bool => $reversedOperationIds?->has($refund->operation_id) ?? false)
            ->reduce(fn (BigDecimal $total, LedgerEntry $refund): BigDecimal => $total->plus($refund->amount), BigDecimal::zero());
        $remaining = BigDecimal::of($entry->amount)->minus($refunded);

        return (string) ($remaining->isNegative() ? BigDecimal::zero() : $remaining);
    }
}
