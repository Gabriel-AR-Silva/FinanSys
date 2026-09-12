<?php

namespace App\Enums;

enum ReceiptForecastStatus: string
{
    case Expected = 'expected';
    case Fulfilled = 'fulfilled';
    case Cancelled = 'cancelled';
}
