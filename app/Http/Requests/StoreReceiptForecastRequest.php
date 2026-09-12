<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreReceiptForecastRequest extends FormRequest
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
            'category_id' => ['required', 'integer'],
            'amount' => ['bail', 'required', 'regex:/^\d{1,10}(?:\.\d{1,2})?$/', 'decimal:0,2', 'gt:0'],
            'expected_on' => ['required', 'date_format:Y-m-d', 'regex:/^[1-9]\d{3}-\d{2}-\d{2}$/'],
            'operation_id' => ['required', 'uuid'],
            'recurrence_count' => ['sometimes', 'required', 'integer', 'min:1', 'max:60'],
        ];
    }

    public function messages(): array
    {
        return [
            'category_id.*' => 'Escolha uma categoria de receita disponível.',
            'amount.*' => 'Informe um valor positivo até R$ 9.999.999.999,99, com no máximo duas casas decimais.',
            'expected_on.*' => 'Informe uma data válida para o recebimento previsto.',
            'operation_id.*' => 'Reabra o formulário para iniciar uma nova operação.',
            'recurrence_count.*' => 'Escolha entre 1 e 60 ocorrências mensais.',
        ];
    }
}
