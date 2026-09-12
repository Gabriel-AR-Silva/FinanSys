<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreReceiptForecastReceiptRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'ledger_entry_id' => ['required', 'integer', 'min:1'],
            'forecast_version' => ['required', 'integer', 'min:1'],
            'operation_id' => ['required', 'uuid'],
        ];
    }

    public function messages(): array
    {
        return [
            'ledger_entry_id.*' => 'Escolha uma receita disponível para vincular.',
            'forecast_version.*' => 'Recarregue a previsão antes de vincular o recebimento.',
            'operation_id.*' => 'Não foi possível identificar esta tentativa. Reabra a vinculação e tente novamente.',
        ];
    }
}
