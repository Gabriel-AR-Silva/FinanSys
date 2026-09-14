<?php

namespace App\Enums;

enum LedgerEntryType: string
{
    case OpeningBalance = 'opening_balance';
    case Income = 'income';
    case Expense = 'expense';
    case Refund = 'refund';
    case TransferIn = 'transfer_in';
    case TransferOut = 'transfer_out';
    case CardPayment = 'card_payment';
    case CardAdvance = 'card_advance';
}
