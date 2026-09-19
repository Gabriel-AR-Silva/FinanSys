<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReceiptForecastReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'mode' => ['sometimes', 'required', Rule::in(['existing', 'new'])],
            'ledger_entry_id' => ['required_unless:mode,new', 'prohibited_if:mode,new', 'integer', 'min:1'],
            'account_id' => ['required_if:mode,new', 'prohibited_unless:mode,new', 'integer', 'min:1'],
            'amount' => ['required_if:mode,new', 'prohibited_unless:mode,new', 'regex:/^\d{1,10}(?:\.\d{1,2})?$/', 'decimal:0,2', 'gt:0'],
            'occurred_at' => ['required_if:mode,new', 'prohibited_unless:mode,new', 'date_format:Y-m-d'],
            'forecast_version' => ['required', 'integer', 'min:1'],
            'operation_id' => ['required', 'uuid'],
        ];
    }

    public function messages(): array
    {
        return [
            'mode.*' => 'Escolha como registrar o recebimento.',
            'ledger_entry_id.*' => 'Escolha uma receita disponível para vincular.',
            'account_id.*' => 'Escolha a conta em que o dinheiro realmente entrou.',
            'amount.*' => 'Informe um valor recebido positivo, com até duas casas decimais.',
            'occurred_at.*' => 'Informe a data real do recebimento.',
            'forecast_version.*' => 'Recarregue a previsão antes de vincular o recebimento.',
            'operation_id.*' => 'Não foi possível identificar esta tentativa. Reabra a vinculação e tente novamente.',
        ];
    }
}
