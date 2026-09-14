<?php

namespace App\Http\Controllers;

use App\Actions\CreateCategory;
use App\Actions\SaveFinancialSettings;
use App\Enums\CategoryType;
use App\Http\Requests\UpdateFinancialSettingsRequest;
use App\Models\Category;
use App\Models\EssentialBudget;
use App\Models\MonthlyFinancialSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FinancialSettingsController extends Controller
{
    public function edit(Request $request): Response
    {
        $request->validate(['month' => ['sometimes', 'required', 'date_format:Y-m', 'regex:/^[1-9]\d{3}-(0[1-9]|1[0-2])$/']]);
        $month = $request->input('month', now('America/Sao_Paulo')->format('Y-m'));
        $settings = MonthlyFinancialSetting::query()->whereBelongsTo($request->user())->where('month', $month)->with('essentials')->first();

        return Inertia::render('FinancialSettings/Edit', [
            'month' => $month,
            'settings' => [
                'configured' => $settings !== null,
                'version' => $settings?->version ?? 0,
                'protection_type' => $settings?->protection_type->value ?? 'fixed',
                'protection_value' => $settings?->protection_value ?? '0.00',
                'essentials' => $settings?->essentials->map(fn (EssentialBudget $budget): array => [
                    'category_id' => $budget->category_id, 'amount' => $budget->amount,
                ])->values()->all() ?? [],
            ],
            'categories' => Category::query()->whereBelongsTo($request->user())->where('type', CategoryType::Expense)
                ->orderBy('name')->orderBy('id')->get(['id', 'name', 'status']),
        ]);
    }

    public function update(UpdateFinancialSettingsRequest $request, SaveFinancialSettings $save): RedirectResponse
    {
        $save->handle($request->user(), $request->validated());

        return to_route('financial-settings.edit', ['month' => $request->validated('month')])
            ->with('success', '🤝 Boa, meu parceiro! Configuração do mês salva.');
    }

    public function storeCategory(Request $request, CreateCategory $create): RedirectResponse
    {
        $data = $request->validate([
            'month' => ['required', 'date_format:Y-m', 'regex:/^[1-9]\d{3}-(0[1-9]|1[0-2])$/'],
            'name' => ['required', 'string', 'max:255'],
        ], ['name.required' => 'Informe o nome da categoria.', 'name.max' => 'Use no máximo 255 caracteres.']);
        $create->handle($request->user(), $data['name'], CategoryType::Expense);

        return to_route('financial-settings.edit', ['month' => $data['month']])->with('success', 'Categoria de despesa criada.');
    }
}
