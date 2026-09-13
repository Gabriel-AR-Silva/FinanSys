<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CardAdvanceAllocation extends Model
{
    protected $fillable = ['user_id', 'card_advance_id', 'card_installment_id', 'gross_amount', 'discount_amount', 'net_amount'];

    protected $casts = [
        'gross_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'net_amount' => 'decimal:2',
    ];
}
