<?php

namespace App\Http\Requests;

use App\Enums\LedgerEntryReferenceType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StoreTransferRequest extends FormRequest
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
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'source_type' => ['required', Rule::enum(LedgerEntryReferenceType::class)],
            'source_id' => ['required', 'integer'],
            'destination_type' => ['required', Rule::enum(LedgerEntryReferenceType::class)],
            'destination_id' => ['required', 'integer'],
            'amount' => ['required', 'decimal:0,2', 'gt:0', 'regex:/^\d{1,17}(?:\.\d{1,2})?$/'],
            'operation_id' => ['required', 'uuid'],
        ];
    }

    public function messages(): array
    {
        return [
            'source_type.enum' => 'Escolha uma origem válida.',
            'destination_type.enum' => 'Escolha um destino válido.',
            'amount.gt' => 'O valor deve ser maior que zero.',
            'amount.decimal' => 'Informe um valor com no máximo duas casas decimais.',
            'amount.regex' => 'O valor ultrapassa o limite suportado.',
        ];
    }

    protected function passedValidation(): void
    {
        if ($this->string('source_type')->toString() === $this->string('destination_type')->toString()
            && $this->integer('source_id') === $this->integer('destination_id')) {
            throw ValidationException::withMessages([
                'destination_id' => 'A origem e o destino devem ser diferentes.',
            ]);
        }
    }
}
