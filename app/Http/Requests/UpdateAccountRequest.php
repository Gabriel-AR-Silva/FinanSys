<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:255']];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Informe o nome da conta.',
            'name.max' => 'O nome da conta deve ter no máximo 255 caracteres.',
        ];
    }
}
