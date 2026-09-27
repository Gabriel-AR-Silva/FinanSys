<?php

namespace App\Actions;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ResetOperationalFinancialData
{
    /** @return list<string> */
    public function allGroupKeys(): array
    {
        return [
            'ledger_entries',
            'receipt_forecasts',
            'future_commitments',
            'card_operations',
            'ofx_imports',
            'daily_planning_records',
            'financial_goals',
            'derived_records',
            'financial_settings',
            'patrimonial_assets',
            'credit_cards',
            'pockets',
            'accounts',
            'categories',
        ];
    }

    /** @return list<string> */
    public function defaultSelectedGroups(): array
    {
        return $this->allGroupKeys();
    }

    /**
     * Backward-compatible scope for clients that do not submit selected_groups.
     *
     * @return list<string>
     */
    public function legacyDefaultGroups(): array
    {
        return [
            'ledger_entries',
            'receipt_forecasts',
            'future_commitments',
            'card_operations',
            'ofx_imports',
            'daily_planning_records',
            'financial_goals',
            'derived_records',
        ];
    }

    /** @return array<string,list<string>> */
    public function dependencies(): array
    {
        return [
            'accounts' => [
                'pockets',
                'ledger_entries',
                'card_operations',
                'ofx_imports',
                'future_commitments',
                'derived_records',
            ],
            'pockets' => [
                'financial_goals',
                'ledger_entries',
                'derived_records',
            ],
            'credit_cards' => [
                'card_operations',
                'derived_records',
            ],
            'categories' => [
                'receipt_forecasts',
                'future_commitments',
                'card_operations',
                'financial_settings',
                'derived_records',
            ],
            'ledger_entries' => ['derived_records'],
            'receipt_forecasts' => ['derived_records'],
            'future_commitments' => ['derived_records'],
            'card_operations' => ['derived_records'],
            'ofx_imports' => ['derived_records'],
            'daily_planning_records' => ['derived_records'],
            'financial_goals' => ['derived_records'],
            'financial_settings' => ['derived_records'],
            'patrimonial_assets' => ['derived_records'],
        ];
    }

    /** @param list<string> $groups @return list<string> */
    public function resolveGroups(array $groups): array
    {
        $allowed = $this->allGroupKeys();
        $resolved = array_values(array_unique(array_filter(
            $groups,
            fn (mixed $group): bool => is_string($group) && in_array($group, $allowed, true),
        )));
        $dependencies = $this->dependencies();

        do {
            $changed = false;

            foreach ($resolved as $group) {
                foreach ($dependencies[$group] ?? [] as $dependency) {
                    if (in_array($dependency, $resolved, true) === false) {
                        $resolved[] = $dependency;
                        $changed = true;
                    }
                }
            }
        } while ($changed);

        return array_values(array_filter(
            $allowed,
            fn (string $group): bool => in_array($group, $resolved, true),
        ));
    }

    /** @return array<string,int> */
    public function preview(User $user): array
    {
        $userId = $user->getKey();

        return [
            'ledger_entries' => $this->countTable('ledger_entries', $userId),
            'receipt_forecasts' => $this->countTable('receipt_forecasts', $userId),
            'future_commitments' => $this->countTable('expense_commitments', $userId)
                + $this->countTable('expense_commitment_payments', $userId),
            'card_operations' => $this->cardOperationsCount($userId),
            'ofx_imports' => $this->countTable('bank_statement_imports', $userId),
            'daily_planning_records' => $this->countTable('daily_budget_versions', $userId)
                + $this->countTable('daily_financial_check_ins', $userId),
            'financial_goals' => $this->countTable('financial_goals', $userId),
            'derived_records' => $this->countTable('financial_evaluations', $userId)
                + $this->countTable('internal_alerts', $userId),
            'financial_settings' => $this->countTable('monthly_financial_settings', $userId)
                + $this->countTable('essential_budgets', $userId),
            'patrimonial_assets' => $this->countTable('patrimonial_assets', $userId),
            'credit_cards' => $this->countTable('credit_cards', $userId),
            'pockets' => $this->countTable('pockets', $userId),
            'accounts' => $this->countTable('accounts', $userId),
            'categories' => $this->countTable('categories', $userId),
        ];
    }

    /** @return list<array{key:string,label:string,description:string,count:int}> */
    public function groupCatalog(User $user): array
    {
        $counts = $this->preview($user);
        $labels = [
            'ledger_entries' => ['Lançamentos', 'Receitas, despesas, transferências, estornos e reembolsos ligados ao razão.'],
            'receipt_forecasts' => ['Recebimentos previstos', 'Previsões, vínculos e operações de recebimento.'],
            'future_commitments' => ['Compromissos futuros', 'Contas futuras e pagamentos já vinculados a elas.'],
            'card_operations' => ['Operações de cartão e Pix no Crédito', 'Compras, parcelas, cobranças, pagamentos, adiantamentos, créditos, estornos e Pix no Crédito.'],
            'ofx_imports' => ['Importações OFX', 'Extratos importados e itens de revisão associados.'],
            'daily_planning_records' => ['Planejamento diário', 'Orçamentos diários e check-ins financeiros.'],
            'financial_goals' => ['Metas financeiras', 'Metas e vínculos com caixinhas.'],
            'derived_records' => ['Análises e avisos', 'Avaliações e alertas derivados dos demais dados.'],
            'financial_settings' => ['Configurações financeiras', 'Configurações mensais e orçamentos essenciais.'],
            'patrimonial_assets' => ['Patrimônio', 'Bens e valores patrimoniais cadastrados.'],
            'credit_cards' => ['Cartões', 'Cadastros estruturais dos cartões de crédito.'],
            'pockets' => ['Caixinhas', 'Cadastros de caixinhas vinculadas às contas.'],
            'accounts' => ['Contas', 'Cadastros das contas financeiras.'],
            'categories' => ['Categorias', 'Categorias de receitas e despesas.'],
        ];

        return array_map(
            fn (string $key): array => [
                'key' => $key,
                'label' => $labels[$key][0],
                'description' => $labels[$key][1],
                'count' => $counts[$key] ?? 0,
            ],
            $this->allGroupKeys(),
        );
    }

    /**
     * @param list<string>|null $groups
     * @return array{counts:array<string,int>,deleted_groups:list<string>,preserved_groups:list<string>}
     */
    public function handle(User $user, ?array $groups = null): array
    {
        $selectedGroups = $this->resolveGroups($groups ?? $this->legacyDefaultGroups());
        $preview = $this->preview($user);
        $preservedGroups = array_values(array_diff($this->allGroupKeys(), $selectedGroups));

        DB::transaction(function () use ($user, $selectedGroups, $preservedGroups): void {
            User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();
            $userId = $user->getKey();

            foreach ($this->deletionPlan() as $step) {
                if ($this->shouldDeleteForGroups($step['groups'], $selectedGroups) === false) {
                    continue;
                }

                if (Schema::hasTable($step['table']) === false) {
                    continue;
                }

                DB::table($step['table'])->where('user_id', $userId)->delete();
            }

            $auditTypes = $this->auditTypesForGroups($selectedGroups);
            if ($auditTypes !== []) {
                DB::table('audit_logs')
                    ->where('user_id', $userId)
                    ->whereIn('auditable_type', $auditTypes)
                    ->delete();
            }

            DB::table('audit_logs')
                ->where('user_id', $userId)
                ->where('auditable_type', 'user')
                ->where('auditable_id', $userId)
                ->where('action', AuditAction::Purged->value)
                ->delete();

            AuditLog::query()->create([
                'user_id' => $userId,
                'action' => AuditAction::Purged->value,
                'auditable_type' => $user->getMorphClass(),
                'auditable_id' => $userId,
                'before' => null,
                'after' => [
                    'scope' => 'selective_financial_data_reset',
                    'deleted_groups' => $selectedGroups,
                    'preserved_groups' => array_merge(['identity'], $preservedGroups),
                ],
                'created_at' => now(),
            ]);
        }, 3);

        return [
            'counts' => array_intersect_key($preview, array_flip($selectedGroups)),
            'deleted_groups' => $selectedGroups,
            'preserved_groups' => $preservedGroups,
        ];
    }

    private function countTable(string $table, int $userId): int
    {
        if (Schema::hasTable($table) === false) {
            return 0;
        }

        return DB::table($table)->where('user_id', $userId)->count();
    }

    private function cardOperationsCount(int $userId): int
    {
        return collect([
            'card_purchases',
            'card_installments',
            'card_payments',
            'card_payment_allocations',
            'card_charges',
            'card_charge_payment_allocations',
            'card_advances',
            'card_advance_allocations',
            'card_purchase_reversals',
            'card_credits',
            'card_credit_allocations',
        ])->sum(fn (string $table): int => $this->countTable($table, $userId));
    }

    /** @param list<string> $requiredGroups @param list<string> $selectedGroups */
    private function shouldDeleteForGroups(array $requiredGroups, array $selectedGroups): bool
    {
        return array_intersect($requiredGroups, $selectedGroups) !== [];
    }

    /** @param list<string> $groups @return list<string> */
    private function auditTypesForGroups(array $groups): array
    {
        $types = [
            'ledger_entries' => ['ledger_entry', 'expense_refund'],
            'receipt_forecasts' => ['receipt_forecast', 'receipt_forecast_link'],
            'future_commitments' => ['expense_commitment', 'expense_commitment_payment'],
            'card_operations' => [
                'card_purchase',
                'card_installment',
                'card_advance',
                'card_advance_allocation',
                'card_charge',
                'card_charge_payment_allocation',
                'card_payment',
                'card_payment_allocation',
                'card_purchase_reversal',
                'card_credit',
                'card_credit_allocation',
            ],
            'daily_planning_records' => ['daily_budget_version', 'daily_financial_check_in'],
            'financial_goals' => ['financial_goal'],
            'financial_settings' => ['monthly_financial_setting', 'essential_budget'],
            'patrimonial_assets' => ['patrimonial_asset'],
            'credit_cards' => ['credit_card'],
            'pockets' => ['pocket'],
            'accounts' => ['account'],
            'categories' => ['category'],
        ];

        return array_values(array_unique(array_merge(...array_map(
            fn (string $group): array => $types[$group] ?? [],
            $groups,
        ))));
    }

    /** @return list<array{table:string,groups:list<string>}> */
    private function deletionPlan(): array
    {
        return [
            ['table' => 'expense_commitment_payments', 'groups' => ['future_commitments']],
            ['table' => 'expense_commitments', 'groups' => ['future_commitments']],
            ['table' => 'daily_financial_check_ins', 'groups' => ['daily_planning_records']],
            ['table' => 'daily_budget_versions', 'groups' => ['daily_planning_records']],
            ['table' => 'financial_goals', 'groups' => ['financial_goals']],
            ['table' => 'bank_statement_import_items', 'groups' => ['ofx_imports']],
            ['table' => 'bank_statement_imports', 'groups' => ['ofx_imports']],
            ['table' => 'card_credit_allocations', 'groups' => ['card_operations']],
            ['table' => 'card_credits', 'groups' => ['card_operations']],
            ['table' => 'card_purchase_reversals', 'groups' => ['card_operations']],
            ['table' => 'card_charge_payment_allocations', 'groups' => ['card_operations']],
            ['table' => 'card_payment_allocations', 'groups' => ['card_operations']],
            ['table' => 'card_advance_allocations', 'groups' => ['card_operations']],
            ['table' => 'card_advances', 'groups' => ['card_operations']],
            ['table' => 'card_payments', 'groups' => ['card_operations']],
            ['table' => 'card_charges', 'groups' => ['card_operations']],
            ['table' => 'card_installments', 'groups' => ['card_operations']],
            ['table' => 'card_purchases', 'groups' => ['card_operations']],
            ['table' => 'receipt_forecast_link_operations', 'groups' => ['receipt_forecasts', 'ledger_entries']],
            ['table' => 'receipt_forecast_links', 'groups' => ['receipt_forecasts', 'ledger_entries']],
            ['table' => 'expense_refunds', 'groups' => ['ledger_entries']],
            ['table' => 'internal_alerts', 'groups' => ['derived_records']],
            ['table' => 'financial_evaluations', 'groups' => ['derived_records']],
            ['table' => 'receipt_forecasts', 'groups' => ['receipt_forecasts']],
            ['table' => 'ledger_entries', 'groups' => ['ledger_entries']],
            ['table' => 'essential_budgets', 'groups' => ['financial_settings']],
            ['table' => 'monthly_financial_settings', 'groups' => ['financial_settings']],
            ['table' => 'patrimonial_assets', 'groups' => ['patrimonial_assets']],
            ['table' => 'credit_cards', 'groups' => ['credit_cards']],
            ['table' => 'pockets', 'groups' => ['pockets']],
            ['table' => 'accounts', 'groups' => ['accounts']],
            ['table' => 'categories', 'groups' => ['categories']],
        ];
    }
}
