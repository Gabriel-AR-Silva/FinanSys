<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreCardChargeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'credit_card_id' => ['required', 'integer'],
            'category_id' => ['required', 'integer'],
            'type' => ['required', 'in:interest,late_fee'],
            'description' => ['required', 'string', 'max:255'],
            'planning_type' => ['required', 'in:ordinary,extraordinary'],
            'amount' => ['required', 'regex:/^\d{1,17}(?:\.\d{1,2})?$/', 'decimal:0,2', 'gt:0'],
            'charged_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'due_on' => ['required', 'date_format:Y-m-d'],
            'operation_id' => ['required', 'uuid'],
        ];
    }

    public function messages(): array
    {
        return [
            'credit_card_id.*' => 'Escolha um cartão disponível.', 'category_id.*' => 'Escolha uma categoria de despesa.',
            'type.*' => 'Escolha juros ou multa.', 'description.*' => 'Informe uma descrição com até 255 caracteres.',
            'planning_type.*' => 'Escolha se o encargo é cotidiano ou extraordinário.',
            'amount.*' => 'Informe um valor positivo com até duas casas decimais.', 'charged_on.*' => 'Informe uma data de cobrança válida.',
            'due_on.*' => 'Informe uma data de vencimento válida.', 'operation_id.*' => 'Reabra o formulário para iniciar uma nova operação.',
        ];
    }
}
