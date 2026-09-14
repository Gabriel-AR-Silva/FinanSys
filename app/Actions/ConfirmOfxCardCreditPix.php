<?php

namespace App\Actions;

use App\Enums\OfxClassification;
use App\Enums\OfxReviewStatus;
use App\Models\BankStatementImport;
use App\Models\CardPurchase;
use App\Models\OfxImportItem;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Ramsey\Uuid\Uuid;

class ConfirmOfxCardCreditPix
{
    public function __construct(private CreateCardPurchase $createCardPurchase) {}

    /**
     * @param  array{credit_card_id:int,category_id:int,planning_type:string,installments_count:int,first_due_on:string}  $data
     */
    public function handle(User $user, BankStatementImport $import, OfxImportItem $item, array $data): CardPurchase
    {
        if ((int) $import->user_id !== (int) $user->getKey()
            || (int) $item->user_id !== (int) $user->getKey()
            || (int) $item->bank_statement_import_id !== (int) $import->getKey()) {
            throw ValidationException::withMessages([
                'item' => 'A movimentação OFX selecionada não está disponível.',
            ]);
        }

        if ($item->review_status === OfxReviewStatus::Confirmed
            && $item->domain_type === CardPurchase::class
            && $item->domain_id !== null) {
            return CardPurchase::query()
                ->whereBelongsTo($user)
                ->findOrFail($item->domain_id);
        }

        return DB::transaction(function () use ($user, $import, $item, $data): CardPurchase {
            $lockedItem = OfxImportItem::query()
                ->where('user_id', $user->getKey())
                ->where('bank_statement_import_id', $import->getKey())
                ->lockForUpdate()
                ->findOrFail($item->getKey());

            if ($lockedItem->classification !== OfxClassification::CardCreditPixCandidate
                || $lockedItem->review_status !== OfxReviewStatus::PendingReview) {
                throw ValidationException::withMessages([
                    'item' => 'Este item não está pendente como Pix no Crédito.',
                ]);
            }

            $pair = $this->pairFor($user, $import, $lockedItem);
            $debit = $pair->first(fn (OfxImportItem $candidate): bool => $candidate->direction === 'debit');

            if ($debit === null) {
                throw ValidationException::withMessages([
                    'item' => 'Não foi possível identificar a saída bancária do Pix no Crédito.',
                ]);
            }

            $operationId = Uuid::uuid5(
                Uuid::NAMESPACE_URL,
                'finansys:ofx:pix-credit:'.$import->getKey().':'.$debit->getKey(),
            )->toString();

            $purchase = $this->createCardPurchase->handle($user, [
                'credit_card_id' => (int) $data['credit_card_id'],
                'category_id' => (int) $data['category_id'],
                'description' => mb_substr('Pix no Crédito — '.trim((string) $debit->description), 0, 255),
                'planning_type' => (string) $data['planning_type'],
                'gross_amount' => (string) $debit->amount,
                'purchased_on' => $debit->occurred_at->setTimezone('America/Sao_Paulo')->toDateString(),
                'installments_count' => (int) $data['installments_count'],
                'first_due_on' => (string) $data['first_due_on'],
                'operation_id' => $operationId,
            ]);

            foreach ($pair as $candidate) {
                $candidate->forceFill([
                    'review_status' => OfxReviewStatus::Confirmed,
                    'domain_type' => CardPurchase::class,
                    'domain_id' => $purchase->getKey(),
                ])->save();
            }

            $stillPending = OfxImportItem::query()
                ->where('bank_statement_import_id', $import->getKey())
                ->where('review_status', OfxReviewStatus::PendingReview)
                ->where('classification', '!=', OfxClassification::Duplicate)
                ->exists();

            if (! $stillPending) {
                $import->forceFill(['status' => OfxReviewStatus::Confirmed])->save();
            }

            return $purchase;
        }, 3);
    }

    /** @return Collection<int, OfxImportItem> */
    private function pairFor(User $user, BankStatementImport $import, OfxImportItem $selected): Collection
    {
        $minimumIndex = max(0, (int) $selected->source_index - 1);
        $maximumIndex = (int) $selected->source_index + 1;
        $selectedDate = $selected->occurred_at->setTimezone('America/Sao_Paulo')->toDateString();
        $oppositeDirection = $selected->direction === 'debit' ? 'credit' : 'debit';

        $candidates = OfxImportItem::query()
            ->where('user_id', $user->getKey())
            ->where('bank_statement_import_id', $import->getKey())
            ->where('classification', OfxClassification::CardCreditPixCandidate)
            ->whereBetween('source_index', [$minimumIndex, $maximumIndex])
            ->lockForUpdate()
            ->get();

        $companions = $candidates->filter(function (OfxImportItem $candidate) use ($selected, $selectedDate, $oppositeDirection): bool {
            return (int) $candidate->getKey() !== (int) $selected->getKey()
                && $candidate->review_status === OfxReviewStatus::PendingReview
                && $candidate->direction === $oppositeDirection
                && (string) $candidate->amount === (string) $selected->amount
                && $candidate->occurred_at->setTimezone('America/Sao_Paulo')->toDateString() === $selectedDate;
        })->values();

        if ($companions->count() !== 1) {
            throw ValidationException::withMessages([
                'item' => 'O par bancário do Pix no Crédito ficou ambíguo. Revise este extrato manualmente.',
            ]);
        }

        return collect([$selected, $companions->first()])
            ->sortBy('source_index')
            ->values();
    }
}
