<?php

namespace App\Enums;

enum ExpensePlanningType: string
{
    case Fixed = 'fixed';
    case Ordinary = 'ordinary';
    case Extraordinary = 'extraordinary';
}
