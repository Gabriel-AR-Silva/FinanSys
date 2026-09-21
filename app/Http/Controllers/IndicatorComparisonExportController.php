<?php

namespace App\Http\Controllers;

use App\Enums\LedgerEntryType;
use App\Models\CardAdvance;
use App\Models\CardCharge;
use App\Models\CardInstallment;
use App\Models\ExpenseRefund;
use App\Models\LedgerEntry;
use App\Models\MonthlyFinancialSetting;
use App\Models\ReceiptForecast;
use App\Queries\FinancialOverviewQuery;
use App\Queries\FinancialPlanningOverviewQuery;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Exportação temporária, somente leitura, para uma conferência externa independente. */
class IndicatorComparisonExportController extends Controller
{
    private const LIMIT = 100;

    public function __invoke(Request $request, FinancialPlanningOverviewQuery $planning, FinancialOverviewQuery $overview): JsonResponse
    {
        $user = $request->user();
        $now = CarbonImmutable::now('America/Sao_Paulo');
        $monthStart = $now->startOfMonth();
        $monthEnd = $now->endOfMonth();
        // Abrange simultaneamente o mês do planejamento e os últimos 30 dias do dashboard.
        $periodStart = $now->startOfDay()->subDays(29);
        $from = $periodStart->lessThan($monthStart) ? $periodStart : $monthStart;
        $fromUtc = $from->utc();
        $untilUtc = $monthEnd->utc();

        $entries = LedgerEntry::query()->whereBelongsTo($user)
            ->whereBetween('occurred_at', [$fromUtc, $untilUtc])
            ->orderBy('occurred_at')->orderBy('id')->limit(self::LIMIT + 1)->get();
        if ($entries->count() > self::LIMIT) {
            return $this->tooMany('lançamentos no intervalo', $entries->count());
        }

        $installments = CardInstallment::query()->whereBelongsTo($user)
            ->whereDate('due_on', '<=', $monthEnd->toDateString())
            ->with(['purchase', 'allocations.payment'])
            ->orderBy('due_on')->orderBy('id')->limit(self::LIMIT + 1)->get();
        if ($installments->count() > self::LIMIT) {
            return $this->tooMany('parcelas até o mês consultado', $installments->count());
        }

        $charges = CardCharge::query()->whereBelongsTo($user)
            ->whereDate('due_on', '<=', $monthEnd->toDateString())
            ->with('allocations.payment')->orderBy('due_on')->orderBy('id')
            ->limit(self::LIMIT + 1)->get();
        if ($charges->count() > self::LIMIT) {
            return $this->tooMany('encargos de cartão', $charges->count());
        }

        $advances = CardAdvance::query()->whereBelongsTo($user)
            ->whereBetween('advanced_on', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->with('allocations')->orderBy('id')->limit(self::LIMIT + 1)->get();
        if ($advances->count() > self::LIMIT) {
            return $this->tooMany('antecipações no mês', $advances->count());
        }

        $forecasts = ReceiptForecast::query()->whereBelongsTo($user)
            ->whereBetween('expected_on', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->with('links')->orderBy('expected_on')->orderBy('id')
            ->limit(self::LIMIT + 1)->get();
        if ($forecasts->count() > self::LIMIT) {
            return $this->tooMany('previsões do mês', $forecasts->count());
        }

        $refunds = ExpenseRefund::query()->whereBelongsTo($user)
            ->where(function ($query) use ($fromUtc, $untilUtc): void {
                $query->whereHas('expenseEntry', fn ($entries) => $entries->whereBetween('occurred_at', [$fromUtc, $untilUtc]))
                    ->orWhereHas('refundEntry', fn ($entries) => $entries->whereBetween('occurred_at', [$fromUtc, $untilUtc]));
            })->orderBy('id')->limit(self::LIMIT + 1)->get();
        if ($refunds->count() > self::LIMIT) {
            return $this->tooMany('vínculos de reembolso', $refunds->count());
        }

        $setting = MonthlyFinancialSetting::query()->whereBelongsTo($user)
            ->where('month', $now->format('Y-m'))->with('essentials')->first();
        $dashboard = $overview->forUser($user, 30);
        $reported = $planning->forUser($user, $now);

        $positive = [LedgerEntryType::OpeningBalance->value, LedgerEntryType::Income->value,
            LedgerEntryType::Refund->value, LedgerEntryType::TransferIn->value];
        $placeholders = implode(', ', array_fill(0, count($positive), '?'));
        $openingBalance = LedgerEntry::query()->whereBelongsTo($user)
            ->where('occurred_at', '<', $fromUtc)
            ->selectRaw("COALESCE(SUM(CASE WHEN type IN ({$placeholders}) THEN amount ELSE -amount END), 0) AS balance", $positive)
            ->value('balance');

        $payload = [
            'schema' => 'finansys-indicator-comparison-v1',
            'generated_at' => $now->toIso8601String(),
            'timezone' => 'America/Sao_Paulo',
            'month' => $now->format('Y-m'),
            'dashboard_period_days' => 30,
            'range' => ['from' => $from->toDateString(), 'through' => $monthEnd->toDateString()],
            'complete' => true,
            'opening_balance_before_range' => (string) $openingBalance,
            'settings' => $setting === null ? null : [
                'protection_type' => $setting->protection_type->value,
                'protection_value' => $setting->protection_value,
                'essentials' => $setting->essentials->map(fn ($essential): array => [
                    'category_id' => $essential->category_id, 'amount' => $essential->amount,
                ])->values()->all(),
            ],
            'ledger_entries' => $entries->map(fn (LedgerEntry $entry): array => [
                'id' => $entry->id,
                'category_id' => $entry->category_id,
                'reference_type' => $entry->reference_type,
                'reference_id' => $entry->reference_id,
                'type' => $entry->type->value,
                'planning_type' => $entry->planning_type?->value,
                'amount' => $entry->amount,
                'operation_id' => $entry->operation_id,
                'reversal_of_operation_id' => $entry->reversal_of_operation_id,
                'occurred_at' => $entry->occurred_at->toIso8601String(),
            ])->all(),
            'card_installments' => $installments->map(fn ($installment): array => [
                'id' => $installment->id,
                'purchase_id' => $installment->card_purchase_id,
                'category_id' => $installment->purchase?->category_id,
                'planning_type' => $installment->purchase?->planning_type?->value,
                'purchase_gross' => $installment->purchase?->gross_amount,
                'purchased_on' => $installment->purchase?->purchased_on?->toDateString(),
                'gross' => $installment->gross_amount,
                'paid' => $installment->paid_amount,
                'due_on' => $installment->due_on->toDateString(),
                'status' => $installment->status->value,
                'allocations' => $installment->allocations->filter(fn ($allocation) => $allocation->payment?->user_id === $user->id)
                    ->map(fn ($allocation): array => ['amount' => $allocation->amount, 'paid_on' => $allocation->payment->paid_on->toDateString()])->values()->all(),
            ])->all(),
            'card_charges' => $charges->map(fn ($charge): array => [
                'id' => $charge->id, 'category_id' => $charge->category_id,
                'planning_type' => $charge->planning_type?->value,
                'type' => $charge->type->value, 'amount' => $charge->amount,
                'paid' => $charge->paid_amount, 'due_on' => $charge->due_on->toDateString(),
                'allocations' => $charge->allocations->filter(fn ($allocation) => $allocation->payment?->user_id === $user->id)
                    ->map(fn ($allocation): array => ['amount' => $allocation->amount, 'paid_on' => $allocation->payment->paid_on->toDateString()])->values()->all(),
            ])->all(),
            'card_advances' => $advances->map(fn ($advance): array => [
                'id' => $advance->id, 'advanced_on' => $advance->advanced_on->toDateString(),
                'gross' => $advance->gross_amount, 'discount' => $advance->discount_amount, 'net' => $advance->net_amount,
                'allocations' => $advance->allocations->map(fn ($allocation): array => [
                    'installment_id' => $allocation->card_installment_id,
                    'gross' => $allocation->gross_amount, 'discount' => $allocation->discount_amount,
                    'net' => $allocation->net_amount,
                ])->all(),
            ])->all(),
            'receipt_forecasts' => $forecasts->map(fn ($forecast): array => [
                'id' => $forecast->id, 'amount' => $forecast->amount,
                'expected_on' => $forecast->expected_on->toDateString(),
                'status' => $forecast->status->value,
                'links' => $forecast->links->filter(fn ($link) => $link->user_id === $user->id)
                    ->map(fn ($link): array => [
                        'ledger_entry_id' => $link->ledger_entry_id,
                        'unlinked_at' => $link->unlinked_at?->toIso8601String(),
                    ])->values()->all(),
            ])->all(),
            'expense_refunds' => $refunds->map(fn ($refund): array => [
                'expense_ledger_entry_id' => $refund->expense_ledger_entry_id,
                'refund_ledger_entry_id' => $refund->refund_ledger_entry_id,
            ])->all(),
            'reported_indicators' => [
                'general_balance' => $dashboard['general_balance'],
                'accounts_balance' => $dashboard['accounts_balance'],
                'pockets_balance' => $dashboard['pockets_balance'],
                'period_summary' => $dashboard['period_summary'],
                'monthly_income' => $dashboard['monthly_income'],
                'monthly_expense' => $dashboard['monthly_expense'],
                'planning' => $reported,
            ],
            'notes' => ['Sem nomes, descrições, e-mails, dados bancários ou credenciais.',
                'Saldos anteriores ao intervalo estão agregados; o JSON não contém dados suficientes para auditar cada lançamento histórico.',
                'Indicadores exibidos são referência para comparação, não entrada para o recálculo independente.',
                'Dias sem lançamento não comprovam gasto zero nem check-in na V2.'],
        ];

        return response()->json($payload, 200, [
            'Content-Disposition' => 'attachment; filename="finansys-indicadores-'.$now->format('Y-m-d').'.json"',
            'Cache-Control' => 'private, no-store, max-age=0',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_PRESERVE_ZERO_FRACTION);
    }

    private function tooMany(string $kind, int $atLeast): JsonResponse
    {
        return response()->json([
            'message' => "Exportação não gerada: há pelo menos {$atLeast} {$kind} (limite de ".self::LIMIT.'). Nenhum conjunto parcial foi exportado.',
        ], 422, ['Cache-Control' => 'private, no-store']);
    }
}
