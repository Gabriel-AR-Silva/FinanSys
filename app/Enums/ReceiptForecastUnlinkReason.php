<?php

namespace App\Enums;

enum ReceiptForecastUnlinkReason: string
{
    case LedgerDeleted = 'ledger_deleted';
    case LedgerReversed = 'ledger_reversed';
}
