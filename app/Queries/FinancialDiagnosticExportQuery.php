<?php

namespace App\Queries;

use App\Models\CreditCard;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class FinancialDiagnosticExportQuery
{
    public function __construct(private FinancialOverviewQuery $overview, private FinancialPlanningOverviewQuery $planning, private MonthlyDailyPlanningDashboardQuery $dailyPlanning, private FinancialGoalPlanningQuery $goals, private CreditCardLimitQuery $cardLimits, private OnboardingProgressQuery $onboarding) {}

    /** @return array<string, mixed> */
    public function forUser(User $user): array
    {
        $generatedAt = CarbonImmutable::now('America/Sao_Paulo');
        $raw = [];

        foreach ($this->tables() as $table) {
            $raw[$table] = DB::table($table)
                ->where('user_id', $user->getKey())
                ->orderBy('id')
                ->get()
                ->map(fn (object $row): array => $this->sanitizeRow((array) $row))
                ->values()
                ->all();
        }

        $cards = CreditCard::query()
            ->whereBelongsTo($user)
            ->orderBy('id')
            ->get();

        return [
            'meta' => [
                'schema' => 'finansys-financial-diagnostic-export',
                'version' => 1,
                'generated_at' => $generatedAt->toIso8601String(),
                'timezone' => 'America/Sao_Paulo',
                'currency' => 'BRL',
                'contains_credentials' => false,
                'purpose' => 'Recalcular e comparar indicadores financeiros sem depender da interface.',
            ],
            'derived' => [
                'overview' => collect([7, 15, 30, 60, 365])
                    ->mapWithKeys(fn (int $period): array => [(string) $period => $this->overview->forUser($user, $period)])
                    ->all(),
                'planning' => $this->planning->forUser($user, $generatedAt),
                'daily_planning' => $this->dailyPlanning->forUser($user, $generatedAt),
                'goals' => $this->goals->forUser($user, $generatedAt),
                'card_limits' => $cards->map(fn (CreditCard $card): array => [
                    'credit_card_id' => (int) $card->getKey(),
                    'name' => $card->name,
                    'deleted_at' => $card->deleted_at?->toIso8601String(),
                    ...$this->cardLimits->forCard($card),
                ])->values()->all(),
                'onboarding' => $this->onboarding->forUser($user),
            ],
            'raw' => $raw,
        ];
    }

    /** @return list<string> */
    private function tables(): array
    {
        return [
            'categories',
            'accounts',
            'pockets',
            'credit_cards',
            'monthly_financial_settings',
            'essential_budgets',
            'ledger_entries',
            'expense_refunds',
            'receipt_forecasts',
            'receipt_forecast_links',
            'receipt_forecast_link_operations',
            'expense_commitments',
            'expense_commitment_payments',
            'card_purchases',
            'card_installments',
            'card_charges',
            'card_payments',
            'card_payment_allocations',
            'card_charge_payment_allocations',
            'card_advances',
            'card_advance_allocations',
            'card_purchase_reversals',
            'card_credits',
            'card_credit_allocations',
            'daily_budget_versions',
            'daily_financial_check_ins',
            'financial_goals',
            'financial_evaluations',
            'internal_alerts',
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function sanitizeRow(array $row): array
    {
        unset($row['user_id']);

        return $row;
    }
}
