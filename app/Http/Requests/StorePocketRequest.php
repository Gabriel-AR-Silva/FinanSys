<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StorePocketRequest extends FormRequest
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
        return ['account_id' => ['required', 'integer'], 'name' => ['required', 'string', 'max:255'], 'operation_id' => ['nullable', 'uuid']];
    }

    public function messages(): array
    {
        return ['account_id.required' => 'Selecione uma conta.', 'name.required' => 'Informe o nome da caixinha.', 'name.max' => 'O nome da caixinha deve ter no máximo 255 caracteres.'];
    }
}
