<?php

namespace App\Enums;

enum LedgerEntryReferenceType: string
{
    case Account = 'account';
    case Pocket = 'pocket';
}
