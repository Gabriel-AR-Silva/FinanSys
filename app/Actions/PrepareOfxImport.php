<?php

namespace App\Actions;

use App\Enums\OfxClassification;
use App\Enums\OfxReviewStatus;
use App\Models\Account;
use App\Models\BankStatementImport;
use App\Models\OfxImportItem;
use App\Models\User;
use App\Support\OfxClassificationResult;
use App\Support\OfxStatement;
use App\Support\OfxTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PrepareOfxImport
{
    /**
     * @param  list<OfxClassificationResult>  $classifications
     */
    public function handle(User $user, Account $account, OfxStatement $statement, array $classifications): BankStatementImport
    {
        if ((int) $account->user_id !== (int) $user->getKey()) {
            throw ValidationException::withMessages(['account_id' => 'A conta selecionada não pertence ao usuário autenticado.']);
        }

        $byIndex = collect($classifications)->keyBy('sourceIndex');

        return DB::transaction(function () use ($user, $account, $statement, $byIndex): BankStatementImport {
            $import = BankStatementImport::query()->create([
                'user_id' => $user->getKey(),
                'account_id' => $account->getKey(),
                'institution' => $statement->institution,
                'institution_id' => $statement->institutionId,
                'currency' => $statement->currency,
                'source_account_hash' => $statement->accountHash,
                'source_account_suffix' => $statement->accountSuffix,
                'period_start' => $statement->periodStart,
                'period_end' => $statement->periodEnd,
                'ledger_balance' => $statement->ledgerBalance,
                'ledger_balance_at' => $statement->ledgerBalanceAt,
                'status' => OfxReviewStatus::PendingReview,
                'transaction_count' => count($statement->transactions),
            ]);

            foreach ($statement->transactions as $transaction) {
                $this->storeItem($import, $user, $account, $statement, $transaction, $byIndex->get($transaction->sourceIndex));
            }

            return $import->load('items');
        });
    }

    private function storeItem(BankStatementImport $import, User $user, Account $account, OfxStatement $statement, OfxTransaction $transaction, ?OfxClassificationResult $result): void
    {
        $dedupKey = $this->dedupKey($user, $account, $statement, $transaction);
        $duplicate = OfxImportItem::query()->where('dedup_key', $dedupKey)->exists();

        $item = new OfxImportItem;
        $item->forceFill([
            'bank_statement_import_id' => $import->getKey(),
            'user_id' => $user->getKey(),
            'account_id' => $account->getKey(),
            'source_index' => $transaction->sourceIndex,
            'bank_type' => $transaction->bankType,
            'occurred_at' => $transaction->occurredAt,
            'amount' => $transaction->amount,
            'direction' => $transaction->direction,
            'description' => $transaction->description,
            'external_id_hash' => $transaction->externalId === null ? null : hash('sha256', $transaction->externalId),
            'fingerprint' => $transaction->fingerprint,
            'dedup_key' => $duplicate ? null : $dedupKey,
            'classification' => $duplicate ? OfxClassification::Duplicate : ($result?->classification ?? OfxClassification::NeedsReview),
            'review_status' => OfxReviewStatus::PendingReview,
        ]);
        $item->save();
    }

    private function dedupKey(User $user, Account $account, OfxStatement $statement, OfxTransaction $transaction): string
    {
        $identity = $transaction->externalId === null
            ? $transaction->fingerprint
            : hash('sha256', $transaction->externalId);

        return hash('sha256', implode('|', [
            $user->getKey(),
            $account->getKey(),
            $statement->institutionId ?? $statement->institution,
            $identity,
        ]));
    }
}
