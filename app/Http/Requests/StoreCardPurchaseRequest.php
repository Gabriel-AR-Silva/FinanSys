<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreCardPurchaseRequest extends FormRequest
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
            'category_id' => ['required', 'integer'],
            'description' => ['required', 'string', 'max:255'],
            'planning_type' => ['required', 'in:ordinary,extraordinary'],
            'gross_amount' => ['required', 'regex:/^\d{1,17}(?:\.\d{1,2})?$/', 'decimal:0,2', 'gt:0'],
            'purchased_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'installments_count' => ['required', 'integer', 'between:1,120'],
            'first_due_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:purchased_on'],
            'operation_id' => ['required', 'uuid'],
        ];
    }

    public function messages(): array
    {
        return [
            'credit_card_id.*' => 'Escolha um cartão disponível.', 'category_id.*' => 'Escolha uma categoria de despesa.',
            'description.*' => 'Informe uma descrição com até 255 caracteres.', 'planning_type.*' => 'Escolha se a compra é cotidiana ou extraordinária.',
            'gross_amount.*' => 'Informe um valor positivo com até duas casas decimais.', 'purchased_on.*' => 'Informe uma data de compra válida.',
            'installments_count.*' => 'Escolha entre 1 e 120 parcelas.', 'first_due_on.*' => 'Informe um primeiro vencimento igual ou posterior à compra.',
            'operation_id.*' => 'Reabra o formulário para iniciar uma nova operação.',
        ];
    }
}
