<?php

namespace App\Enums;

enum CardChargeType: string
{
    case Interest = 'interest';
    case LateFee = 'late_fee';
}
