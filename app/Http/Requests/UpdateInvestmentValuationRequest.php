<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateInvestmentValuationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'current_value' => ['required', 'numeric', 'gte:0'],
            'valued_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.now('America/Sao_Paulo')->toDateString()],
        ];
    }
}
