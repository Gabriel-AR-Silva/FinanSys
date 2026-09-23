<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePatrimonialAssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'category' => ['nullable', 'string', 'max:80'],
            'estimated_value' => ['required', 'regex:/\A(?:0|[1-9]\d{0,16})(?:\.\d{1,2})?\z/', 'gt:0'],
            'debt_balance' => ['nullable', 'regex:/\A(?:0|[1-9]\d{0,16})(?:\.\d{1,2})?\z/', 'gte:0'],
            'valued_on' => ['required', 'date_format:Y-m-d'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Informe o nome do bem.',
            'estimated_value.*' => 'Informe um valor estimado positivo com até duas casas decimais.',
            'debt_balance.*' => 'Informe um saldo devedor válido ou deixe zero.',
            'valued_on.*' => 'Informe a data usada para esta estimativa.',
        ];
    }
}
