<?php

namespace App\Http\Requests;

use App\Domain\Ledger\ReferenceResolver;
use App\Enums\LedgerEntryReferenceType;
use App\Enums\LedgerEntryType;
use App\Enums\RecordStatus;
use App\Models\Account;
use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StoreLedgerEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in([LedgerEntryType::Income->value, LedgerEntryType::Expense->value])],
            'account_id' => ['required', 'integer', Rule::exists(Account::class, 'id')->where(fn ($query) => $query->where('user_id', $this->user()?->getKey())->where('status', RecordStatus::Active->value)->whereNull('deleted_at'))],
            'category_id' => ['required', 'integer', Rule::exists(Category::class, 'id')->where(fn ($query) => $query->where('user_id', $this->user()?->getKey())->where('type', $this->input('type'))->where('status', RecordStatus::Active->value))],
            'amount' => ['required', 'decimal:0,2', 'gt:0', 'regex:/^\d{1,17}(?:\.\d{1,2})?$/'],
            'occurred_at' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'description' => ['nullable', 'string', 'max:255'],
            'operation_id' => ['required', 'uuid'],
        ];
    }

    public function messages(): array
    {
        return [
            'type.in' => 'Escolha receita ou despesa.',
            'account_id.exists' => 'A conta selecionada não está disponível.',
            'category_id.required' => 'Escolha uma categoria.',
            'category_id.exists' => 'A categoria selecionada não está disponível para este tipo de lançamento.',
            'amount.gt' => 'O valor deve ser maior que zero.',
            'amount.decimal' => 'Informe um valor com no máximo duas casas decimais.',
            'amount.regex' => 'O valor ultrapassa o limite suportado.',
            'occurred_at.before_or_equal' => 'A data não pode estar no futuro.',
        ];
    }

    protected function passedValidation(): void
    {
        $account = app(ReferenceResolver::class)->resolve(
            $this->user(),
            LedgerEntryReferenceType::Account,
            $this->integer('account_id'),
        );

        if (! $account instanceof Account || $account->status !== RecordStatus::Active) {
            throw ValidationException::withMessages(['account_id' => 'A conta selecionada não está disponível.']);
        }
    }
}
