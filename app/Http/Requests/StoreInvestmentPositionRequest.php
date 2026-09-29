<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreInvestmentPositionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'asset_type' => ['required', 'string', 'max:60'],
            'ticker' => ['nullable', 'string', 'max:40'],
            'name' => ['required', 'string', 'max:120'],
            'purchased_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.now('America/Sao_Paulo')->toDateString()],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'average_cost' => ['required', 'numeric', 'gte:0'],
            'current_value' => ['nullable', 'numeric', 'gte:0'],
            'valued_on' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:'.now('America/Sao_Paulo')->toDateString()],
        ];
    }
}
