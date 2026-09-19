<?php

namespace App\Http\Controllers;

use App\Actions\CancelReceiptForecast;
use App\Actions\CreateReceiptForecast;
use App\Actions\RecalculateReceiptForecast;
use App\Actions\UpdateReceiptForecast;
use App\Enums\CategoryType;
use App\Enums\ReceiptForecastStatus;
use App\Enums\RecordStatus;
use App\Http\Requests\StoreReceiptForecastRequest;
use App\Http\Requests\UpdateReceiptForecastRequest;
use App\Models\Category;
use App\Models\LedgerEntry;
use App\Models\ReceiptForecast;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReceiptForecastController extends Controller
{
    public function index(Request $request, RecalculateReceiptForecast $progressCalculator): Response
    {
        $request->validate(['month' => ['sometimes', 'required', 'date_format:Y-m', 'regex:/^[1-9]\d{3}-(0[1-9]|1[0-2])$/']]);
        $today = now('America/Sao_Paulo')->toDateString();
        $month = $request->input('month', substr($today, 0, 7));
        $start = CarbonImmutable::createFromFormat('!Y-m', $month, 'America/Sao_Paulo');
        $forecasts = ReceiptForecast::query()->whereBelongsTo($request->user())
            ->whereBetween('expected_on', [$start->toDateString(), $start->endOfMonth()->toDateTimeString()])
            ->with(['category:id,name', 'activeLinks.ledgerEntry.category:id,name'])
            ->orderBy('expected_on')->orderBy('id')
            ->paginate(20)->withQueryString()->through(function (ReceiptForecast $forecast) use ($progressCalculator, $request, $today): array {
                $progress = $progressCalculator->calculate($request->user(), $forecast);

                return [
                    'id' => $forecast->id,
                    'category_id' => $forecast->category_id,
                    'version' => $forecast->version,
                    'series_id' => $forecast->series_id,
                    'series_position' => $forecast->series_position,
                    'is_recurring' => $forecast->series_id !== null,
                    'has_receipts' => $forecast->activeLinks->isNotEmpty(),
                    'category_name' => $forecast->category->name,
                    'amount' => $forecast->amount,
                    'expected_on' => $forecast->expected_on->toDateString(),
                    'status' => $forecast->status->value,
                    'received' => $progress['received'],
                    'pending' => $forecast->status === ReceiptForecastStatus::Cancelled ? '0.00' : $progress['pending'],
                    'excess' => $progress['excess'],
                    'is_overdue' => $forecast->status === ReceiptForecastStatus::Expected && $forecast->expected_on->toDateString() < $today,
                    'receipts' => $forecast->activeLinks->map(fn ($link): array => [
                        'id' => $link->id,
                        'ledger_entry_id' => $link->ledger_entry_id,
                        'amount' => $link->ledgerEntry?->amount,
                        'occurred_at' => $link->ledgerEntry?->occurred_at?->toDateString(),
                        'category_name' => $link->ledgerEntry?->category?->name,
                    ])->filter(fn (array $receipt): bool => $receipt['amount'] !== null)->values(),
                ];
            });

        return Inertia::render('ReceiptForecasts/Index', [
            'month' => $month,
            'forecasts' => $forecasts,
            'categories' => Category::query()->whereBelongsTo($request->user())
                ->where('type', CategoryType::Income)->where('status', RecordStatus::Active)
                ->orderBy('name')->orderBy('id')->get(['id', 'name']),
            'availableReceipts' => LedgerEntry::query()
                ->whereBelongsTo($request->user())
                ->where('type', 'income')
                ->whereNull('reversal_of_operation_id')
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

    public function store(StoreReceiptForecastRequest $request, CreateReceiptForecast $create): RedirectResponse
    {
        $forecast = $create->handle($request->user(), $request->validated());

        return $this->returnAfterMutation($request, $forecast, '🤝 Previsão salva! O saldo só muda quando o dinheiro entrar.');
    }

    public function cancel(Request $request, int $forecast, CancelReceiptForecast $cancel): RedirectResponse
    {
        $data = $request->validate(['version' => ['required', 'integer', 'min:1']], ['version.*' => 'Recarregue a previsão antes de cancelar.']);
        $cancelled = $cancel->handle($request->user(), $forecast, (int) $data['version']);

        return $this->returnAfterMutation($request, $cancelled, '🤝 Previsão cancelada. Seu histórico continua aqui.');
    }

    public function update(UpdateReceiptForecastRequest $request, int $forecast, UpdateReceiptForecast $update): RedirectResponse
    {
        $updated = $update->handle($request->user(), $forecast, $request->validated());

        return $this->returnAfterMutation($request, $updated, '🤝 Previsão atualizada. O histórico foi preservado.');
    }

    private function returnAfterMutation(Request $request, ReceiptForecast $forecast, string $message): RedirectResponse
    {
        $params = ['month' => $forecast->expected_on->format('Y-m')];
        if ($request->boolean('from_settings')) {
            $params['tab'] = 'receipts';
        }

        return to_route($request->boolean('from_settings') ? 'financial-settings.edit' : 'receipt-forecasts.index', $params)
            ->with('success', $message);
    }
}
