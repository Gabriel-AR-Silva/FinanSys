<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInvestmentMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(['buy', 'sell', 'income', 'fee'])],
            'occurred_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.now('America/Sao_Paulo')->toDateString()],
            'quantity' => ['nullable', 'numeric', 'gt:0'],
            'unit_price' => ['nullable', 'numeric', 'gte:0'],
            'amount' => ['nullable', 'numeric', 'gte:0'],
            'fees' => ['nullable', 'numeric', 'gte:0'],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }
}
