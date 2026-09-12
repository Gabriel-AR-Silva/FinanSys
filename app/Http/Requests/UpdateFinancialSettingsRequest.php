<?php

namespace App\Http\Requests;

use App\Enums\ProtectionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFinancialSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $decimal = ['bail', 'required', 'regex:/^\d{1,10}(?:\.\d{1,2})?$/', 'decimal:0,2', 'gte:0'];

        return [
            'month' => ['required', 'date_format:Y-m', 'regex:/^[1-9]\d{3}-(0[1-9]|1[0-2])$/'],
            'version' => ['required', 'integer', 'min:0'],
            'protection_type' => ['required', Rule::enum(ProtectionType::class)],
            'protection_value' => [...$decimal, Rule::when($this->input('protection_type') === 'percentage', ['lte:100'])],
            'essentials' => ['present', 'array', 'max:100'],
            'essentials.*' => ['array:category_id,amount'],
            'essentials.*.category_id' => ['bail', 'required', 'integer', 'distinct'],
            'essentials.*.amount' => $decimal,
        ];
    }

    public function messages(): array
    {
        return [
            'month.*' => 'Escolha um mês válido.',
            'version.*' => 'Recarregue a configuração antes de salvar.',
            'protection_type.*' => 'Escolha valor fixo ou percentual.',
            'protection_value.lte' => 'O percentual deve ficar entre 0 e 100.',
            'protection_value.regex' => 'Informe até R$ 9.999.999.999,99, com no máximo duas casas decimais.',
            'essentials.*.amount.regex' => 'Informe até R$ 9.999.999.999,99, com no máximo duas casas decimais.',
            'protection_value.*' => 'Informe um valor não negativo com até duas casas decimais.',
            'essentials.*.amount.*' => 'Informe um valor não negativo com até duas casas decimais.',
            'essentials.*.category_id.distinct' => 'Cada categoria pode aparecer apenas uma vez.',
            'essentials.*.category_id.*' => 'Escolha uma categoria de despesa disponível.',
            'essentials.*' => 'Confira a lista de essenciais (máximo de 100 categorias).',
        ];
    }
}
