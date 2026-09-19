<?php

namespace App\Actions;

use App\Enums\ExpensePlanningType;
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

        return DB::transaction(function () use ($user, $import, $item, $data): CardPurchase {
            // Serializar confirmações Pix da mesma importação antes de bloquear qualquer item.
            // Requisições pelos lados opostos do par não adquirem os itens em ordem inversa.
            BankStatementImport::query()
                ->where('user_id', $user->getKey())
                ->lockForUpdate()
                ->findOrFail($import->getKey());

            $lockedItem = OfxImportItem::query()
                ->where('user_id', $user->getKey())
                ->where('bank_statement_import_id', $import->getKey())
                ->lockForUpdate()
                ->findOrFail($item->getKey());

            // A segunda requisição deve consultar o estado depois de adquirir o bloqueio.
            if ($lockedItem->review_status === OfxReviewStatus::Confirmed
                && $lockedItem->domain_type === CardPurchase::class
                && $lockedItem->domain_id !== null) {
                $purchase = CardPurchase::query()
                    ->whereBelongsTo($user)
                    ->findOrFail($lockedItem->domain_id);

                return $this->validateReplay($purchase, $data);
            }

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

    /**
     * @param  array{credit_card_id:int,category_id:int,planning_type:string,installments_count:int,first_due_on:string}  $data
     */
    private function validateReplay(CardPurchase $purchase, array $data): CardPurchase
    {
        $firstInstallment = $purchase->installments()
            ->orderBy('installment_number')
            ->first();

        if ($purchase->credit_card_id !== (int) $data['credit_card_id']
            || $purchase->category_id !== (int) $data['category_id']
            || $purchase->planning_type !== ExpensePlanningType::tryFrom((string) $data['planning_type'])
            || $purchase->installments_count !== (int) $data['installments_count']
            || $firstInstallment?->due_on->toDateString() !== (string) $data['first_due_on']) {
            throw ValidationException::withMessages([
                'item' => 'Esta confirmação já foi realizada com dados diferentes.',
            ]);
        }

        return $purchase;
    }

    /** @return Collection<int, OfxImportItem> */
    private function pairFor(User $user, BankStatementImport $import, OfxImportItem $selected): Collection
    {
        if ($selected->relationship_key === null) {
            throw ValidationException::withMessages([
                'item' => 'Não foi possível identificar o vínculo bancário do Pix no Crédito.',
            ]);
        }

        $pair = OfxImportItem::query()
            ->where('user_id', $user->getKey())
            ->where('bank_statement_import_id', $import->getKey())
            ->where('relationship_key', $selected->relationship_key)
            ->where('classification', OfxClassification::CardCreditPixCandidate)
            ->where('review_status', OfxReviewStatus::PendingReview)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        if ($pair->count() !== 2) {
            throw ValidationException::withMessages([
                'item' => 'O par bancário do Pix no Crédito ficou ambíguo. Revise este extrato manualmente.',
            ]);
        }

        $credit = $pair->first(fn (OfxImportItem $candidate): bool => $candidate->direction === 'credit');
        $debit = $pair->first(fn (OfxImportItem $candidate): bool => $candidate->direction === 'debit');
        $sameAmount = $credit !== null && $debit !== null && (string) $credit->amount === (string) $debit->amount;
        $sameDate = $credit !== null && $debit !== null
            && $credit->occurred_at->setTimezone('America/Sao_Paulo')->toDateString()
            === $debit->occurred_at->setTimezone('America/Sao_Paulo')->toDateString();

        if (! $sameAmount || ! $sameDate) {
            throw ValidationException::withMessages([
                'item' => 'O par bancário do Pix no Crédito não é consistente. Revise este extrato manualmente.',
            ]);
        }

        return $pair->sortBy('source_index')->values();
    }
}
