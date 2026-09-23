<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreFinancialGoalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'target_amount' => ['required', 'regex:/^(?:0|[1-9]\d{0,16})(?:\.\d{1,2})?$/', 'not_in:0,0.0,0.00'],
            'target_date' => ['required', 'date_format:Y-m-d'],
            'pocket_id' => ['nullable', 'integer'],
            'operation_id' => ['required', 'uuid'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Informe um nome para a meta.',
            'name.max' => 'Use no máximo 120 caracteres.',
            'target_amount.required' => 'Informe o valor da meta.',
            'target_amount.regex' => 'Informe um valor válido com até duas casas decimais.',
            'target_amount.not_in' => 'O valor da meta deve ser maior que zero.',
            'target_date.required' => 'Informe a data-alvo.',
            'target_date.date_format' => 'Informe uma data-alvo válida.',
            'operation_id.uuid' => 'Não foi possível identificar a operação. Tente novamente.',
        ];
    }
}
