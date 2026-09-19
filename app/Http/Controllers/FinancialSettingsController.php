<?php

namespace App\Http\Controllers;

use App\Actions\CreateCategory;
use App\Actions\RecalculateReceiptForecast;
use App\Actions\SaveFinancialSettings;
use App\Enums\CategoryType;
use App\Enums\ReceiptForecastStatus;
use App\Enums\RecordStatus;
use App\Http\Requests\UpdateFinancialSettingsRequest;
use App\Models\Account;
use App\Models\Category;
use App\Models\EssentialBudget;
use App\Models\LedgerEntry;
use App\Models\MonthlyFinancialSetting;
use App\Models\ReceiptForecast;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class FinancialSettingsController extends Controller
{
    public function edit(Request $request, RecalculateReceiptForecast $progressCalculator): Response
    {
        $request->validate([
            'month' => ['sometimes', 'required', 'date_format:Y-m', 'regex:/^[1-9]\d{3}-(0[1-9]|1[0-2])$/'],
            'tab' => ['sometimes', 'required', Rule::in(['protection', 'essentials', 'receipts'])],
        ]);
        $month = $request->input('month', now('America/Sao_Paulo')->format('Y-m'));
        $today = now('America/Sao_Paulo')->toDateString();
        $settings = MonthlyFinancialSetting::query()->whereBelongsTo($request->user())->where('month', $month)->with('essentials')->first();
        $start = CarbonImmutable::createFromFormat('!Y-m', $month, 'America/Sao_Paulo');
        $forecasts = ReceiptForecast::query()->whereBelongsTo($request->user())
            ->whereBetween('expected_on', [$start->toDateString(), $start->endOfMonth()->toDateTimeString()])
            ->with(['category:id,name', 'activeLinks.ledgerEntry.category:id,name'])
            ->orderBy('expected_on')->orderBy('id')
            ->paginate(20)->withQueryString()->through(function (ReceiptForecast $forecast) use ($request, $progressCalculator, $today): array {
                $progress = $progressCalculator->calculate($request->user(), $forecast);

                return [
                    'id' => $forecast->id,
                    'category_id' => $forecast->category_id,
                    'version' => $forecast->version,
                    'category_name' => $forecast->category->name,
                    'amount' => $forecast->amount,
                    'expected_on' => $forecast->expected_on->toDateString(),
                    'status' => $forecast->status->value,
                    'is_recurring' => $forecast->series_id !== null,
                    'has_receipts' => $forecast->activeLinks->isNotEmpty(),
                    'received' => $progress['received'],
                    'pending' => $forecast->status === ReceiptForecastStatus::Cancelled ? '0.00' : $progress['pending'],
                    'excess' => $progress['excess'],
                    'is_overdue' => $forecast->status === ReceiptForecastStatus::Expected && $forecast->expected_on->toDateString() < $today,
                    'receipts' => $forecast->activeLinks->map(fn ($link): array => [
                        'id' => $link->id,
                        'amount' => $link->ledgerEntry?->amount,
                        'occurred_at' => $link->ledgerEntry?->occurred_at?->toDateString(),
                        'category_name' => $link->ledgerEntry?->category?->name,
                    ])->filter(fn (array $receipt): bool => $receipt['amount'] !== null)->values(),
                ];
            });

        return Inertia::render('FinancialSettings/Edit', [
            'month' => $month,
            'activeTab' => $request->input('tab', 'protection'),
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
            'receiptForecasts' => $forecasts,
            'receiptCategories' => Category::query()->whereBelongsTo($request->user())->where('type', CategoryType::Income)
                ->where('status', RecordStatus::Active)->orderBy('name')->orderBy('id')->get(['id', 'name']),
            'receiptAccounts' => Account::query()->whereBelongsTo($request->user())
                ->where('status', RecordStatus::Active)->orderBy('name')->orderBy('id')->get(['id', 'name']),
            'availableReceipts' => LedgerEntry::query()->whereBelongsTo($request->user())
                ->where('type', 'income')->whereNull('reversal_of_operation_id')
                ->whereDoesntHave('receiptForecastLink', fn ($query) => $query->whereNull('unlinked_at'))
                ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('ledger_entries as reversals')
                    ->whereColumn('reversals.user_id', 'ledger_entries.user_id')
                    ->whereColumn('reversals.reversal_of_operation_id', 'ledger_entries.operation_id'))
                ->with(['category:id,name', 'reference'])
                ->orderByDesc('occurred_at')->orderByDesc('id')->limit(100)->get()
                ->map(fn (LedgerEntry $entry): array => [
                    'id' => $entry->id,
                    'amount' => $entry->amount,
                    'occurred_at' => $entry->occurred_at->toDateString(),
                    'category_name' => $entry->category?->name,
                    'account_name' => $entry->reference?->name,
                    'description' => $entry->description,
                ]),
        ]);
    }

    public function update(UpdateFinancialSettingsRequest $request, SaveFinancialSettings $save): RedirectResponse
    {
        $save->handle($request->user(), $request->validated());

        $params = ['month' => $request->validated('month')];
        if (in_array($request->query('tab'), ['protection', 'essentials'], true)) {
            $params['tab'] = $request->query('tab');
        }

        return to_route('financial-settings.edit', $params)
            ->with('success', '🤝 Boa, meu parceiro! Configuração do mês salva.');
    }

    public function storeCategory(Request $request, CreateCategory $create): RedirectResponse
    {
        $data = $request->validate([
            'month' => ['required', 'date_format:Y-m', 'regex:/^[1-9]\d{3}-(0[1-9]|1[0-2])$/'],
            'name' => ['required', 'string', 'max:255'],
        ], ['name.required' => 'Informe o nome da categoria.', 'name.max' => 'Use no máximo 255 caracteres.']);
        $create->handle($request->user(), $data['name'], CategoryType::Expense);

        $params = ['month' => $data['month']];
        if ($request->query('tab') === 'essentials') {
            $params['tab'] = 'essentials';
        }

        return to_route('financial-settings.edit', $params)->with('success', 'Categoria de despesa criada.');
    }
}
