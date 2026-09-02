<?php

namespace App\Http\Requests;

use App\Enums\RecordStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCategoryStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return ['status' => ['required', Rule::enum(RecordStatus::class)]];
    }

    public function messages(): array
    {
        return ['status.enum' => 'Escolha um status válido para a categoria.'];
    }
}
