<?php

namespace App\Queries;

use App\Enums\CategoryType;
use App\Enums\ExpensePlanningType;
use App\Enums\LedgerEntryType;
use App\Enums\ReceiptForecastStatus;
use App\Enums\RecordStatus;
use App\Models\Account;
use App\Models\Category;
use App\Models\CreditCard;
use App\Models\DailyBudgetVersion;
use App\Models\EssentialBudget;
use App\Models\FinancialGoal;
use App\Models\LedgerEntry;
use App\Models\MonthlyFinancialSetting;
use App\Models\PatrimonialAsset;
use App\Models\ReceiptForecast;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

class OnboardingProgressQuery
{
    /** @return array<string, mixed> */
    public function forUser(User $user): array
    {
        $month = now('America/Sao_Paulo')->format('Y-m');

        $hasAccount = Account::query()
            ->whereBelongsTo($user)
            ->where('status', RecordStatus::Active)
            ->exists();

        $hasIncomeCategory = Category::query()
            ->whereBelongsTo($user)
            ->where('type', CategoryType::Income)
            ->where('status', RecordStatus::Active)
            ->exists();

        $hasExpenseCategory = Category::query()
            ->whereBelongsTo($user)
            ->where('type', CategoryType::Expense)
            ->where('status', RecordStatus::Active)
            ->exists();

        $hasReceivedIncome = LedgerEntry::query()
            ->whereBelongsTo($user)
            ->where('type', LedgerEntryType::Income)
            ->whereNull('reversal_of_operation_id')
            ->whereNotExists(fn ($query) => $query
                ->selectRaw('1')
                ->from('ledger_entries as reversals')
                ->whereColumn('reversals.user_id', 'ledger_entries.user_id')
                ->whereColumn('reversals.reversal_of_operation_id', 'ledger_entries.operation_id')
                ->whereNull('reversals.deleted_at'))
            ->exists();

        $hasReceiptForecast = ReceiptForecast::query()
            ->whereBelongsTo($user)
            ->where('status', '!=', ReceiptForecastStatus::Cancelled)
            ->exists();

        $hasFixedCommitment = LedgerEntry::query()
            ->whereBelongsTo($user)
            ->where('type', LedgerEntryType::Expense)
            ->where('planning_type', ExpensePlanningType::Fixed)
            ->whereNull('reversal_of_operation_id')
            ->whereNotExists(fn ($query) => $query
                ->selectRaw('1')
                ->from('ledger_entries as reversals')
                ->whereColumn('reversals.user_id', 'ledger_entries.user_id')
                ->whereColumn('reversals.reversal_of_operation_id', 'ledger_entries.operation_id')
                ->whereNull('reversals.deleted_at'))
            ->exists();

        $hasPlanning = MonthlyFinancialSetting::query()
            ->whereBelongsTo($user)
            ->where('month', $month)
            ->exists();

        $hasEssentials = EssentialBudget::query()
            ->whereBelongsTo($user)
            ->whereHas('monthlyFinancialSetting', fn ($query) => $query->where('month', $month))
            ->exists();

        $hasCard = CreditCard::query()
            ->whereBelongsTo($user)
            ->where('status', RecordStatus::Active)
            ->exists();

        $hasDailyBudget = DailyBudgetVersion::query()
            ->whereBelongsTo($user)
            ->exists();

        $hasGoal = FinancialGoal::query()
            ->whereBelongsTo($user)
            ->exists();

        $hasPatrimony = Schema::hasTable('patrimonial_assets')
            && PatrimonialAsset::query()
                ->whereBelongsTo($user)
                ->exists();

        $essentialSteps = [
            $this->step('foundation', 'Base do sistema', 'Moeda BRL e calendário de Brasília já estão definidos.', true, null),
            $this->step(
                'account',
                'Primeira conta e saldo inicial',
                $hasAccount ? 'Sua estrutura financeira já pode receber movimentações.' : 'A conta é a base para registrar receitas, despesas e caixinhas.',
                $hasAccount,
                $hasAccount ? null : ['label' => 'Criar primeira conta', 'href' => route('accounts.index', ['create' => 1, 'from' => 'onboarding'])],
            ),
        ];

        $recommendedSteps = [
            $this->step(
                'income',
                'Renda ou recebimentos',
                $hasReceivedIncome || $hasReceiptForecast ? 'Já existe renda recebida ou prevista para orientar o mês.' : 'Receita prevista ajuda no planejamento, mas só a recebida vira saldo.',
                $hasReceivedIncome || $hasReceiptForecast,
                $this->incomeCta($hasAccount, $hasIncomeCategory),
            ),
            $this->step(
                'fixed_commitments',
                'Compromissos fixos',
                $hasFixedCommitment ? 'Há ao menos um gasto fixo conhecido.' : 'Cadastre compromissos conhecidos para não tratar verba comprometida como livre.',
                $hasFixedCommitment,
                $this->expenseCta($hasAccount, $hasExpenseCategory, true),
            ),
            $this->step(
                'essentials',
                'Categorias essenciais',
                $hasEssentials ? 'O mês possui ao menos uma reserva essencial.' : 'Essenciais como alimentação podem reservar parte da verba sem inventar gasto.',
                $hasEssentials,
                $hasExpenseCategory
                    ? ['label' => 'Configurar essenciais', 'href' => route('financial-settings.edit', ['month' => $month, 'from' => 'onboarding'])]
                    : ['label' => 'Criar categoria de despesa', 'href' => route('categories.index', ['create' => 'expense', 'from' => 'onboarding'])],
            ),
            $this->step(
                'planning',
                'Planejamento do mês',
                $hasPlanning ? 'Proteção e premissas deste mês foram confirmadas.' : 'Sem configuração, o sistema não interpreta ausência como zero confirmado.',
                $hasPlanning,
                $hasPlanning ? null : ['label' => 'Configurar este mês', 'href' => route('financial-settings.edit', ['month' => $month, 'from' => 'onboarding'])],
            ),
            $this->step(
                'daily_budget',
                'Orçamento diário',
                $hasDailyBudget ? 'Seu planejamento diário já possui uma referência voluntária.' : 'Defina quanto pretende gastar por dia para liberar check-ins e comparações da V2.',
                $hasDailyBudget,
                $hasDailyBudget ? null : ['label' => 'Definir orçamento diário', 'href' => route('daily-budgets.edit', ['from' => 'onboarding'])],
            ),
            $this->step(
                'card',
                'Cartão de crédito',
                $hasCard ? 'Ao menos um cartão está pronto para registrar compras.' : 'Opcional: cadastre somente se você realmente usa cartão.',
                $hasCard,
                $hasCard ? null : ['label' => 'Acessar cartões', 'href' => route('credit-cards.index', ['from' => 'onboarding'])],
            ),
            $this->step(
                'patrimony',
                'Patrimônio estimado',
                $hasPatrimony ? 'Você já acompanha ao menos um bem patrimonial.' : 'Opcional: registre bens relevantes pelo valor estimado atual e dívida vinculada.',
                $hasPatrimony,
                $hasPatrimony ? null : ['label' => 'Adicionar patrimônio', 'href' => route('patrimony.index', ['create' => 1, 'from' => 'onboarding'])],
            ),
            $this->step(
                'goal',
                'Meta financeira',
                $hasGoal ? 'Você já acompanha ao menos uma meta.' : 'Opcional: defina um objetivo e, se quiser, vincule uma caixinha real para acompanhar a reserva.',
                $hasGoal,
                $hasGoal ? null : ['label' => 'Criar uma meta', 'href' => route('dashboard', ['view' => 'goals', 'from' => 'onboarding'])],
            ),
        ];

        $allSteps = [...$essentialSteps, ...$recommendedSteps];
        $completed = count(array_filter($allSteps, fn (array $step): bool => $step['completed']));

        return [
            'essentialSteps' => $essentialSteps,
            'recommendedSteps' => $recommendedSteps,
            'progress' => [
                'completed' => $completed,
                'total' => count($allSteps),
                'percent' => (int) floor(($completed / count($allSteps)) * 100),
                'essentialReady' => $hasAccount,
            ],
            'initiallyOpen' => ! $hasAccount,
        ];
    }

    /** @param array{label:string,href:string}|null $cta */
    private function step(string $key, string $title, string $description, bool $completed, ?array $cta): array
    {
        return compact('key', 'title', 'description', 'completed', 'cta');
    }

    /** @return array{label:string,href:string}|null */
    private function incomeCta(bool $hasAccount, bool $hasIncomeCategory): ?array
    {
        if (! $hasAccount) {
            return ['label' => 'Criar conta primeiro', 'href' => route('accounts.index', ['create' => 1, 'from' => 'onboarding'])];
        }

        if (! $hasIncomeCategory) {
            return ['label' => 'Criar categoria de receita', 'href' => route('categories.index', ['create' => 'income', 'from' => 'onboarding'])];
        }

        return ['label' => 'Registrar receita', 'href' => route('ledger-entries.index', ['create' => 'income', 'from' => 'onboarding'])];
    }

    /** @return array{label:string,href:string} */
    private function expenseCta(bool $hasAccount, bool $hasExpenseCategory, bool $fixed = false): array
    {
        if (! $hasAccount) {
            return ['label' => 'Criar conta primeiro', 'href' => route('accounts.index', ['create' => 1, 'from' => 'onboarding'])];
        }

        if (! $hasExpenseCategory) {
            return ['label' => 'Criar categoria de despesa', 'href' => route('categories.index', ['create' => 'expense', 'from' => 'onboarding'])];
        }

        if ($fixed) {
            return [
                'label' => 'Registrar compromisso fixo',
                'href' => route('expense-commitments.index', ['from' => 'onboarding']),
            ];
        }

        return [
            'label' => 'Registrar despesa',
            'href' => route('ledger-entries.index', ['create' => 'expense', 'from' => 'onboarding']),
        ];
    }
}
