<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CardAdvance extends Model
{
    protected $fillable = ['user_id', 'credit_card_id', 'gross_amount', 'discount_amount', 'net_amount', 'advanced_on', 'operation_id'];

    protected $casts = [
        'gross_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'advanced_on' => 'immutable_date',
    ];
}
