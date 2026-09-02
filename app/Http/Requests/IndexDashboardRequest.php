<?php

namespace App\Http\Requests;

use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexDashboardRequest extends FormRequest
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
            'period' => ['nullable', 'integer'],
            'category_id' => [
                'nullable',
                'integer',
                Rule::exists(Category::class, 'id')->where(fn ($query) => $query->where('user_id', $this->user()?->getKey())),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'category_id.exists' => 'A categoria selecionada não está disponível.',
        ];
    }
}
