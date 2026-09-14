<?php

namespace App\Enums;

enum OfxClassification: string
{
    case Income = 'income';
    case Expense = 'expense';
    case TransferCandidate = 'transfer_candidate';
    case CardCreditPixCandidate = 'card_credit_pix_candidate';
    case Duplicate = 'duplicate';
    case Unsupported = 'unsupported';
    case NeedsReview = 'needs_review';
}
