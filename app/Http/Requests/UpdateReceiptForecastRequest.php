<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;

class UpdateReceiptForecastRequest extends StoreReceiptForecastRequest
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
        $rules = parent::rules();
        unset($rules['operation_id'], $rules['recurrence_count']);

        return $rules + [
            'version' => ['required', 'integer', 'min:1'],
            'edit_scope' => ['sometimes', 'required', 'in:this,future'],
        ];
    }

    public function messages(): array
    {
        return parent::messages() + [
            'version.*' => 'Recarregue a previsão antes de editar.',
            'edit_scope.*' => 'Escolha editar somente esta ocorrência ou esta e as próximas.',
        ];
    }
}
