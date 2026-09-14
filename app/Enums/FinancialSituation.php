<?php

namespace App\Enums;

enum FinancialSituation: string
{
    case NoBasis = 'no_basis';
    case Insufficient = 'insufficient';
    case UnderControl = 'under_control';
    case Balanced = 'balanced';
    case OutsidePlan = 'outside_plan';
}
