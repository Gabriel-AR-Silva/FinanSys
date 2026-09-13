<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreCardAdvanceRequest extends FormRequest
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
            'source_account_id' => ['required', 'integer'],
            'installment_ids' => ['required', 'array', 'min:1', 'max:200'],
            'installment_ids.*' => ['required', 'integer', 'distinct'],
            'discount_amount' => ['required', 'regex:/^\d{1,17}(?:\.\d{1,2})?$/', 'decimal:0,2', 'gte:0'],
            'expected_gross_amount' => ['required', 'regex:/^\d{1,17}(?:\.\d{1,2})?$/', 'decimal:0,2', 'gt:0'],
            'advanced_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'operation_id' => ['required', 'uuid'],
        ];
    }

    public function messages(): array
    {
        return [
            'credit_card_id.*' => 'Escolha um cartão disponível.',
            'source_account_id.*' => 'Escolha a conta usada na antecipação.',
            'installment_ids.*' => 'Selecione de 1 a 200 parcelas futuras deste cartão.',
            'discount_amount.*' => 'Informe um desconto válido com até duas casas decimais.',
            'expected_gross_amount.*' => 'Revise o saldo bruto da prévia.',
            'advanced_on.*' => 'Informe uma data de antecipação válida.',
            'operation_id.*' => 'Reabra o formulário para iniciar uma nova operação.',
        ];
    }
}
