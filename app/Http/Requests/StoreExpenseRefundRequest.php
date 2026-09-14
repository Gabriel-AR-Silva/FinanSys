<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreExpenseRefundRequest extends FormRequest
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
        return [
            'expense_ledger_entry_id' => ['required', 'integer', 'min:1'],
            'destination_account_id' => ['required', 'integer', 'min:1'],
            'amount' => ['required', 'decimal:0,2', 'gt:0', 'regex:/^\d{1,17}(?:\.\d{1,2})?$/'],
            'occurred_at' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'operation_id' => ['required', 'uuid'],
        ];
    }

    public function messages(): array
    {
        return [
            'amount.gt' => 'O reembolso deve ser maior que zero.',
            'amount.regex' => 'O valor ultrapassa o limite suportado.',
            'occurred_at.before_or_equal' => 'A data não pode estar no futuro.',
        ];
    }
}
