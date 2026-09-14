<?php

namespace App\Support;

use App\Enums\OfxClassification;

class OfxClassifier
{
    /**
     * @param array<string, list<int>> $relatedGroups
     * @return list<OfxClassificationResult>
     */
    public function classify(array $transactions, array $relatedGroups = []): array
    {
        $compoundIndexes = array_flip(array_merge(...array_values($relatedGroups ?: [[]])));

        return array_map(function (OfxTransaction $transaction) use ($compoundIndexes): OfxClassificationResult {
            if (isset($compoundIndexes[$transaction->sourceIndex]) && $this->looksLikePixOnCredit($transaction)) {
                return new OfxClassificationResult(
                    sourceIndex: $transaction->sourceIndex,
                    classification: OfxClassification::CardCreditPixCandidate,
                    reason: 'Movimentação relacionada a Pix no Crédito; exige validação e associação ao cartão.',
                );
            }

            return new OfxClassificationResult(
                sourceIndex: $transaction->sourceIndex,
                classification: OfxClassification::NeedsReview,
                reason: 'Sinal bancário não define sozinho a natureza financeira; usuário deve validar.',
            );
        }, $transactions);
    }

    private function looksLikePixOnCredit(OfxTransaction $transaction): bool
    {
        $text = mb_strtolower($transaction->description.' '.($transaction->memo ?? ''));

        return str_contains($text, 'pix no crédito')
            || str_contains($text, 'pix no credito')
            || str_contains($text, 'cartão de crédito')
            || str_contains($text, 'cartao de credito');
    }
}
