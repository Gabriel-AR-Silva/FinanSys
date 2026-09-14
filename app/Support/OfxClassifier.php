<?php

namespace App\Support;

use App\Enums\OfxClassification;

class OfxClassifier
{
    /**
     * @param  list<OfxTransaction>  $transactions
     * @param  array<string, list<int>>  $relatedGroups
     * @return list<OfxClassificationResult>
     */
    public function classify(array $transactions, array $relatedGroups = []): array
    {
        $pixCreditIndexes = $this->pixCreditIndexes($transactions, $relatedGroups);

        return array_map(function (OfxTransaction $transaction) use ($pixCreditIndexes): OfxClassificationResult {
            if (isset($pixCreditIndexes[$transaction->sourceIndex])) {
                return new OfxClassificationResult(
                    sourceIndex: $transaction->sourceIndex,
                    classification: OfxClassification::CardCreditPixCandidate,
                    reason: 'Grupo relacionado a Pix no Crédito; exige validação e associação ao cartão.',
                );
            }

            return new OfxClassificationResult(
                sourceIndex: $transaction->sourceIndex,
                classification: OfxClassification::NeedsReview,
                reason: 'Sinal bancário não define sozinho a natureza financeira; usuário deve validar.',
            );
        }, $transactions);
    }

    /** @return array<int, true> */
    private function pixCreditIndexes(array $transactions, array $relatedGroups): array
    {
        $byIndex = [];
        foreach ($transactions as $transaction) {
            $byIndex[$transaction->sourceIndex] = $transaction;
        }

        $indexes = [];
        foreach ($relatedGroups as $group) {
            $isPixCredit = false;
            foreach ($group as $index) {
                if (isset($byIndex[$index]) && $this->looksLikePixOnCredit($byIndex[$index])) {
                    $isPixCredit = true;
                    break;
                }
            }

            if ($isPixCredit) {
                foreach ($group as $index) {
                    $indexes[$index] = true;
                }
            }
        }

        return $indexes;
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
