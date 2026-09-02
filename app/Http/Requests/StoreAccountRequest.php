<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'opening_balance' => ['required', 'decimal:0,2', 'min:0'],
            'operation_id' => ['nullable', 'uuid'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Informe o nome da conta.',
            'name.max' => 'O nome da conta deve ter no máximo 255 caracteres.',
            'opening_balance.required' => 'Informe o saldo inicial.',
            'opening_balance.decimal' => 'Informe um saldo com no máximo duas casas decimais.',
            'opening_balance.min' => 'O saldo inicial deve ser maior ou igual a zero.',
        ];
    }
}
