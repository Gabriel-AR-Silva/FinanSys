<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreCardPaymentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'credit_card_id' => ['required', 'integer'],
            'source_account_id' => ['required', 'integer'],
            'amount' => ['required', 'regex:/^\d{1,17}(?:\.\d{1,2})?$/', 'decimal:0,2', 'gt:0'],
            'paid_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'operation_id' => ['required', 'uuid'],
        ];
    }

    public function messages(): array
    {
        return [
            'credit_card_id.*' => 'Escolha um cartão disponível.', 'source_account_id.*' => 'Escolha a conta usada no pagamento.',
            'amount.*' => 'Informe um valor positivo com até duas casas decimais.', 'paid_on.*' => 'Informe uma data de pagamento válida.',
            'operation_id.*' => 'Reabra o formulário para iniciar uma nova operação.',
        ];
    }
}
