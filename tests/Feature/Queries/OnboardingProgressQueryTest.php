<?php

namespace Tests\Feature\Queries;

use App\Enums\ExpensePlanningType;
use App\Enums\LedgerEntryType;
use App\Models\Account;
use App\Models\LedgerEntry;
use App\Models\User;
use App\Queries\OnboardingProgressQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OnboardingProgressQueryTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_user_is_not_ready_for_use(): void
    {
        $user = User::factory()->create();

        $progress = app(OnboardingProgressQuery::class)->forUser($user);
        $accountStep = collect($progress['essentialSteps'])->firstWhere('key', 'account');

        $this->assertFalse($progress['progress']['essentialReady']);
        $this->assertTrue($progress['initiallyOpen']);
        $this->assertFalse($accountStep['completed']);
        $this->assertSame('Criar primeira conta', $accountStep['cta']['label']);
    }

    public function test_account_completes_essential_setup_automatically(): void
    {
        $user = User::factory()->create();
        Account::factory()->for($user)->create();

        $progress = app(OnboardingProgressQuery::class)->forUser($user);
        $accountStep = collect($progress['essentialSteps'])->firstWhere('key', 'account');

        $this->assertTrue($progress['progress']['essentialReady']);
        $this->assertFalse($progress['initiallyOpen']);
        $this->assertTrue($accountStep['completed']);
        $this->assertNull($accountStep['cta']);
    }

    public function test_reversed_fixed_expense_does_not_complete_fixed_commitment_step(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $expense = LedgerEntry::factory()->for($user)->create([
            'reference_type' => $account->getMorphClass(),
            'reference_id' => $account->getKey(),
            'type' => LedgerEntryType::Expense,
            'planning_type' => ExpensePlanningType::Fixed,
        ]);
        LedgerEntry::factory()->for($user)->create([
            'reference_type' => $account->getMorphClass(),
            'reference_id' => $account->getKey(),
            'type' => LedgerEntryType::Income,
            'reversal_of_operation_id' => $expense->operation_id,
        ]);

        $progress = app(OnboardingProgressQuery::class)->forUser($user);
        $fixedCommitmentStep = collect($progress['recommendedSteps'])->firstWhere('key', 'fixed_commitments');

        $this->assertFalse($fixedCommitmentStep['completed']);
        $this->assertSame('Registrar compromisso fixo', $fixedCommitmentStep['cta']['label']);
    }

    public function test_other_users_data_does_not_complete_onboarding(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        Account::factory()->for($otherUser)->create();

        $progress = app(OnboardingProgressQuery::class)->forUser($user);

        $this->assertFalse($progress['progress']['essentialReady']);
    }
}
