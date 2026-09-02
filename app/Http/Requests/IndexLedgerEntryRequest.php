<?php

namespace App\Http\Requests;

use App\Enums\LedgerEntryType;
use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexLedgerEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'type' => ['nullable', Rule::in(['all', ...array_map(fn (LedgerEntryType $type): string => $type->value, LedgerEntryType::cases())])],
            'account_id' => ['nullable', 'integer', 'min:1'],
            'category_id' => ['nullable', 'integer', Rule::exists(Category::class, 'id')->where(fn ($query) => $query->where('user_id', $this->user()?->getKey()))],
            'period' => ['nullable', Rule::in(['all', '7', '15', '30', '60', '365'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return ['category_id.exists' => 'A categoria selecionada não está disponível.'];
    }
}
