<?php

namespace App\Enums;

enum CardInstallmentStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Advanced = 'advanced';
}
