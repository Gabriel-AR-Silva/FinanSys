<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreCardCreditAllocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'card_credit_id' => ['required', 'integer', 'min:1'],
            'target_type' => ['required', 'string', 'in:installment,charge'],
            'target_id' => ['required', 'integer', 'min:1'],
            'amount' => ['required', 'regex:/^\d{1,17}(?:\.\d{1,2})?$/', 'decimal:0,2', 'gt:0'],
            'applied_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'operation_id' => ['required', 'uuid'],
        ];
    }

    public function messages(): array
    {
        return [
            'card_credit_id.*' => 'Escolha um crédito disponível.',
            'target_type.*' => 'Escolha o tipo de obrigação de destino.',
            'target_id.*' => 'Escolha uma obrigação válida.',
            'amount.*' => 'Informe um valor positivo com até duas casas decimais.',
            'applied_on.*' => 'Informe uma data de aplicação válida.',
            'operation_id.*' => 'Reabra o formulário para iniciar uma nova operação.',
        ];
    }
}
