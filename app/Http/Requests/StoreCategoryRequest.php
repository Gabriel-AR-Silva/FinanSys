<?php

namespace App\Http\Requests;

use App\Enums\CategoryType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:255'], 'type' => ['required', Rule::enum(CategoryType::class)]];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Informe o nome da categoria.',
            'name.max' => 'O nome da categoria deve ter no máximo 255 caracteres.',
            'type.required' => 'Escolha receita ou despesa.',
            'type.enum' => 'Escolha receita ou despesa.',
        ];
    }
}
