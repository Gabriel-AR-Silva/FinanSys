<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCreditCardLimitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'credit_limit' => ['nullable', 'regex:/\A\d{1,17}(?:\.\d{1,2})?\z/', 'gt:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'credit_limit.*' => 'Informe um limite positivo com até duas casas decimais ou deixe em branco.',
        ];
    }
}
