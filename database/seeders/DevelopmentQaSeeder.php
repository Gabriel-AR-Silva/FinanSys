<?php

namespace Database\Seeders;

use App\Models\CardInstallment;
use App\Models\CardPurchase;
use App\Models\CreditCard;
use App\Models\DailyBudgetVersion;
use App\Models\DailyFinancialCheckIn;
use App\Models\ExpenseCommitment;
use App\Models\FinancialGoal;
use App\Models\PatrimonialAsset;
use App\Models\ReceiptForecast;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class DevelopmentQaSeeder extends Seeder
{
    private const CARD_OPERATION = 'dd2c89f7-1491-48e8-bf5e-4c623f5d7b11';
    private const PURCHASE_OPERATION = '4a6c3b67-f1e1-44ee-a0b0-80f55c5ad0ac';
    private const RECEIPT_OPERATION = '5e1d8981-5f9a-4f4a-a4ae-38ab2d08f28a';
    private const COMMITMENT_OPERATION = '9c12c7fe-b93f-4f51-852f-b5d26e71e22f';
    private const GOAL_OPERATION = 'd21a07e8-b89f-4df5-af07-3f2f49c95904';
    private const BUDGET_OPERATION = '9fa90977-2bb1-47eb-b571-33847490e201';

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('DevelopmentQaSeeder só pode executar em local ou testing.');
        }

        $user = User::query()->where('email', config('development.user.email'))->first();

        if (! $user) {
            throw new RuntimeException('Crie primeiro o usuário local com DevelopmentSeeder.');
        }

        $this->call(DevelopmentFinancialSeeder::class);

        $user->refresh();
        $account = $user->accounts()->where('name', 'Conta principal')->firstOrFail();
        $pocket = $user->pockets()->where('name', 'Viagem')->firstOrFail();
        $expenseCategory = $user->categories()->where('type', 'expense')->where('name', 'Alimentação')->firstOrFail();
        $incomeCategory = $user->categories()->where('type', 'income')->where('name', 'Freelance')->firstOrFail();

        DB::transaction(function () use ($user, $account, $pocket, $expenseCategory, $incomeCategory): void {
            $card = CreditCard::query()->whereBelongsTo($user)->where('operation_id', self::CARD_OPERATION)->first();

            if (! $card) {
                $card = CreditCard::factory()->for($user)->create([
                    'name' => 'Cartão QA',
                    'closing_day' => 5,
                    'due_day' => 12,
                    'credit_limit' => '4500.00',
                    'operation_id' => self::CARD_OPERATION,
                ]);
            }

            $purchase = CardPurchase::query()->whereBelongsTo($user)->where('operation_id', self::PURCHASE_OPERATION)->first();

            if (! $purchase) {
                $purchase = CardPurchase::factory()->for($user)->create([
                    'credit_card_id' => $card->id,
                    'category_id' => $expenseCategory->id,
                    'description' => 'Notebook parcelado QA',
                    'planning_type' => 'ordinary',
                    'gross_amount' => '1800.00',
                    'purchased_on' => now('America/Sao_Paulo')->subMonth()->toDateString(),
                    'installments_count' => 6,
                    'operation_id' => self::PURCHASE_OPERATION,
                ]);

                foreach (range(1, 6) as $number) {
                    $due = CarbonImmutable::now('America/Sao_Paulo')->startOfMonth()->addMonths($number - 1)->day(12);
                    CardInstallment::factory()->for($user)->for($purchase, 'purchase')->create([
                        'installment_number' => $number,
                        'gross_amount' => '300.00',
                        'paid_amount' => $number === 1 ? '300.00' : '0.00',
                        'due_on' => $due->toDateString(),
                        'original_due_on' => $due->toDateString(),
                        'status' => $number === 1 ? 'paid' : 'pending',
                    ]);
                }
            }

            if (! ReceiptForecast::query()->whereBelongsTo($user)->where('operation_id', self::RECEIPT_OPERATION)->exists()) {
                ReceiptForecast::factory()->for($user)->create([
                    'category_id' => $incomeCategory->id,
                    'amount' => '1250.00',
                    'expected_on' => now('America/Sao_Paulo')->addDays(8)->toDateString(),
                    'status' => 'expected',
                    'operation_id' => self::RECEIPT_OPERATION,
                ]);
            }

            if (! ExpenseCommitment::query()->whereBelongsTo($user)->where('operation_id', self::COMMITMENT_OPERATION)->exists()) {
                ExpenseCommitment::factory()->for($user)->create([
                    'account_id' => $account->id,
                    'category_id' => $expenseCategory->id,
                    'description' => 'Compromisso futuro QA',
                    'amount' => '420.00',
                    'paid_amount' => '120.00',
                    'due_on' => now('America/Sao_Paulo')->addDays(14)->toDateString(),
                    'planning_type' => 'fixed',
                    'status' => 'pending',
                    'operation_id' => self::COMMITMENT_OPERATION,
                ]);
            }

            if (! FinancialGoal::query()->whereBelongsTo($user)->where('operation_id', self::GOAL_OPERATION)->exists()) {
                FinancialGoal::factory()->for($user)->create([
                    'pocket_id' => $pocket->id,
                    'name' => 'Meta de viagem QA',
                    'target_amount' => '5000.00',
                    'target_date' => now('America/Sao_Paulo')->addMonths(6)->toDateString(),
                    'operation_id' => self::GOAL_OPERATION,
                ]);
            }

            PatrimonialAsset::query()->firstOrCreate(
                ['user_id' => $user->id, 'name' => 'Moto QA'],
                PatrimonialAsset::factory()->raw([
                    'user_id' => $user->id,
                    'name' => 'Moto QA',
                    'category' => 'Veículo',
                    'estimated_value' => '18000.00',
                    'debt_balance' => '6500.00',
                    'valued_on' => now('America/Sao_Paulo')->toDateString(),
                ]),
            );

            $budget = DailyBudgetVersion::query()->whereBelongsTo($user)->where('operation_id', self::BUDGET_OPERATION)->first();

            if (! $budget) {
                $budget = DailyBudgetVersion::factory()->create([
                    'user_id' => $user->id,
                    'actor_id' => $user->id,
                    'amount' => '80.00',
                    'effective_at' => now('UTC')->subDays(14)->startOfDay(),
                    'recorded_at' => now('UTC')->subDays(14),
                    'origin' => 'manual',
                    'reason' => 'Cenário QA',
                    'operation_id' => self::BUDGET_OPERATION,
                ]);
            }

            foreach (range(1, 10) as $offset) {
                $localDate = CarbonImmutable::now('America/Sao_Paulo')->subDays($offset)->toDateString();

                if (DailyFinancialCheckIn::query()->whereBelongsTo($user)->where('local_date', $localDate)->where('revision', 1)->exists()) {
                    continue;
                }

                $spent = 35 + (($offset * 9) % 70);
                $margin = 80 - $spent;

                DailyFinancialCheckIn::factory()->create([
                    'user_id' => $user->id,
                    'actor_id' => $user->id,
                    'local_date' => $localDate,
                    'daily_budget_version_id' => $budget->id,
                    'budget_amount' => '80.00',
                    'eligible_spent' => number_format($spent, 2, '.', ''),
                    'margin' => number_format($margin, 2, '.', ''),
                    'confirmed_at' => CarbonImmutable::parse($localDate, 'America/Sao_Paulo')->endOfDay()->utc(),
                    'reason' => 'Cenário QA',
                    'operation_id' => (string) Str::uuid(),
                ]);
            }
        });

        $this->command?->info('Cenário QA local criado: cartões, recebíveis, compromissos, meta, patrimônio e histórico diário.');
    }
}
