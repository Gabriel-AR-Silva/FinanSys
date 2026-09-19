<?php

namespace App\Http\Requests;

use App\Enums\ExpensePlanningType;
use App\Enums\LedgerEntryType;
use App\Models\LedgerEntry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CorrectLedgerEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $entry = LedgerEntry::query()->whereBelongsTo($this->user())
            ->find((int) $this->route('ledgerEntry'));
        $expense = $entry?->type === LedgerEntryType::Expense;

        return [
            'category_id' => ['required', 'integer'],
            'amount' => ['required', 'decimal:0,2', 'gt:0', 'regex:/^\d{1,17}(?:\.\d{1,2})?$/'],
            'occurred_at' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'description' => ['nullable', 'string', 'max:255'],
            'planning_type' => [Rule::requiredIf($expense), Rule::prohibitedIf(! $expense), Rule::enum(ExpensePlanningType::class)],
        ];
    }
}
