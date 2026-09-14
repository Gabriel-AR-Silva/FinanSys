<?php

namespace App\Http\Controllers;

use App\Enums\ExpensePlanningType;
use App\Enums\OfxClassification;
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

        $item->forceFill([
            'classification' => $classification,
            'category_id' => $validated['category_id'] ?? null,
            'planning_type' => $classification === OfxClassification::Expense ? ($validated['planning_type'] ?? null) : null,
        ])->save();

        return back()->with('success', 'Revisão salva. Nenhum lançamento financeiro foi criado ainda.');
    }
}
