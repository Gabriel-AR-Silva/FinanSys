<?php

namespace App\Http\Controllers;

use App\Enums\CategoryType;
use App\Enums\ExpensePlanningType;
use App\Enums\OfxClassification;
use App\Enums\RecordStatus;
use App\Models\Category;
use App\Models\OfxImportItem;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class OfxImportReviewController extends Controller
{
    public function update(Request $request, OfxImportItem $item): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ((int) $item->user_id !== (int) $user->getKey()) {
            abort(404);
        }

        if (in_array($item->classification, [OfxClassification::Duplicate, OfxClassification::Unsupported], true)) {
            throw ValidationException::withMessages(['classification' => 'Este item não pode ser conciliado.']);
        }

        $validated = $request->validate([
            'classification' => ['required', Rule::in([
                OfxClassification::Income->value,
                OfxClassification::Expense->value,
                OfxClassification::NeedsReview->value,
            ])],
            'category_id' => ['nullable', 'integer'],
            'planning_type' => ['nullable', Rule::enum(ExpensePlanningType::class)],
        ]);

        $classification = OfxClassification::from($validated['classification']);
        $categoryId = null;

        if (in_array($classification, [OfxClassification::Income, OfxClassification::Expense], true)) {
            $expectedType = $classification === OfxClassification::Income ? CategoryType::Income : CategoryType::Expense;
            $category = Category::query()
                ->whereBelongsTo($user)
                ->where('status', RecordStatus::Active)
                ->where('type', $expectedType)
                ->find($validated['category_id'] ?? null);

            if ($category === null) {
                throw ValidationException::withMessages(['category_id' => 'Selecione uma categoria válida para esta classificação.']);
            }

            $categoryId = $category->getKey();
        }

        if ($classification === OfxClassification::Expense && empty($validated['planning_type'])) {
            throw ValidationException::withMessages(['planning_type' => 'Informe como a despesa participa do planejamento.']);
        }

        $item->forceFill([
            'classification' => $classification,
            'category_id' => $categoryId,
            'planning_type' => $classification === OfxClassification::Expense ? $validated['planning_type'] : null,
        ])->save();

        return back()->with('success', 'Revisão salva. Nenhum lançamento financeiro foi criado ainda.');
    }
}
