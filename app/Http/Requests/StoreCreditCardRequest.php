<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreCreditCardRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:100'],
            'closing_day' => ['required', 'integer', 'between:1,31'],
            'due_day' => ['required', 'integer', 'between:1,31'],
            'credit_limit' => ['nullable', 'regex:/\\A\\d{1,17}(?:\\.\\d{1,2})?\\z/', 'gt:0'],
            'operation_id' => ['required', 'uuid'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.*' => 'Informe um nome com até 100 caracteres.',
            'closing_day.*' => 'Escolha um dia de fechamento entre 1 e 31.',
            'due_day.*' => 'Escolha um vencimento entre 1 e 31.',
            'credit_limit.*' => 'Informe um limite positivo com até duas casas decimais ou deixe em branco.',
            'operation_id.*' => 'Reabra o formulário para iniciar uma nova operação.',
        ];
    }
}
