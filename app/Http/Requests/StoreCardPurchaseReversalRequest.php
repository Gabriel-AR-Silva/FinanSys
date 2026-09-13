<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreCardPurchaseReversalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'card_purchase_id' => ['required', 'integer', 'min:1'],
            'reversed_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'reason' => ['nullable', 'string', 'max:255'],
            'operation_id' => ['required', 'uuid'],
        ];
    }

    public function messages(): array
    {
        return [
            'card_purchase_id.*' => 'Escolha uma compra disponível.',
            'reversed_on.*' => 'Informe uma data de estorno válida.',
            'reason.*' => 'O motivo deve ter no máximo 255 caracteres.',
            'operation_id.*' => 'Reabra o formulário para iniciar uma nova operação.',
        ];
    }
}
